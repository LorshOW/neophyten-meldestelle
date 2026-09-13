<?php

namespace App\Jobs;

use App\Mail\TemplatedMail;
use App\Models\OutgoingEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTemplatedEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $outgoingEmailId)
    {
    }

    public function handle(): void
    {
        $log = OutgoingEmail::find($this->outgoingEmailId);

        if ($log === null || $log->status === OutgoingEmail::STATUS_SENT) {
            return;
        }

        try {
            Mail::to($log->recipient)->send(new TemplatedMail(
                subjectLine: $log->subject,
                bodyHtml: (string) $log->body_html,
            ));
        } catch (Throwable $e) {
            $log->markFailed($e->getMessage());

            throw $e;
        }

        $log->markSent();
    }
}
