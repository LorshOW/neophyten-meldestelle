<?php

namespace App\Services\Mail;

use App\Models\NotificationRule;
use App\Models\OutgoingEmail;
use App\Models\User;
use App\Models\Vorgang;
use App\Models\WorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Turns a workflow event into the emails the configured rules ask for.
 *
 * Every attempt is written to outgoing_emails, successful or not, so the office
 * can always show what a reporter was told and when.
 */
class NotificationDispatcher
{
    public function __construct(private readonly TemplateRenderer $renderer)
    {
    }

    public function dispatchFor(
        string $event,
        Vorgang $vorgang,
        ?WorkflowStatus $status = null,
        array $extra = [],
    ): void {
        $rules = NotificationRule::query()
            ->active()
            ->forEvent($event)
            ->with('template')
            ->get()
            ->filter(function (NotificationRule $rule) use ($status): bool {
                // A status-specific rule only fires for its own status.
                return $rule->status_id === null || $rule->status_id === $status?->getKey();
            });

        foreach ($rules as $rule) {
            if (! $rule->template?->is_active) {
                continue;
            }

            foreach ($this->recipients($rule, $vorgang) as $recipient) {
                $this->queue($rule, $vorgang, $recipient, $extra);
            }
        }
    }

    /** @return Collection<int, string> */
    private function recipients(NotificationRule $rule, Vorgang $vorgang): Collection
    {
        return match ($rule->recipient_type) {
            NotificationRule::RECIPIENT_REPORTER => $this->reporterRecipient($vorgang),

            NotificationRule::RECIPIENT_ASSIGNEE => collect([$vorgang->assignedTo?->email])->filter()->values(),

            NotificationRule::RECIPIENT_ROLE => User::active()
                ->withRole((string) $rule->recipient_value)
                ->pluck('email')
                ->filter()
                ->values(),

            NotificationRule::RECIPIENT_FIXED => collect(
                preg_split('/[,;]/', (string) $rule->recipient_value) ?: []
            )->map(fn ($v) => trim($v))->filter()->values(),

            default => collect(),
        };
    }

    /**
     * The reporter is only ever contacted when they left an address AND agreed
     * to be contacted. The published Datenschutzerklärung permits their contact
     * data to be used for nothing else.
     *
     * @return Collection<int, string>
     */
    private function reporterRecipient(Vorgang $vorgang): Collection
    {
        return $vorgang->canBeEmailed()
            ? collect([$vorgang->reporter_email])
            : collect();
    }

    private function queue(NotificationRule $rule, Vorgang $vorgang, string $recipient, array $extra): void
    {
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $rendered = $this->renderer->render($rule->template, $vorgang, $extra);

        $log = OutgoingEmail::create([
            'recipient' => $recipient,
            'subject' => $rendered['subject'],
            'body_html' => $rendered['body'],
            'email_template_id' => $rule->email_template_id,
            'notification_rule_id' => $rule->getKey(),
            'vorgang_id' => $vorgang->getKey(),
            'status' => OutgoingEmail::STATUS_QUEUED,
        ]);

        \App\Jobs\SendTemplatedEmail::dispatch($log->getKey());

        Log::info('E-Mail eingeplant.', [
            'rule' => $rule->name,
            'vorgang' => $vorgang->local_nr,
            'log_id' => $log->getKey(),
        ]);
    }
}
