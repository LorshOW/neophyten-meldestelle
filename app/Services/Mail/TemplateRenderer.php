<?php

namespace App\Services\Mail;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Models\Vorgang;
use Illuminate\Support\Str;

/**
 * Fills the {{ placeholder }} slots of an email template.
 *
 * Deliberately NOT Blade: template bodies are edited by administrators through
 * a web form, and compiling that as Blade would turn the template editor into
 * remote code execution. This does a literal, escaped replacement instead.
 */
class TemplateRenderer
{
    /**
     * @param  array<string, string|null>  $extra
     * @return array{subject: string, body: string}
     */
    public function render(EmailTemplate $template, ?Vorgang $vorgang = null, array $extra = []): array
    {
        $context = array_merge($this->context($vorgang), $extra);

        return [
            'subject' => $this->replace($template->subject, $context, escape: false),
            'body' => $this->replace($template->body_html, $context, escape: true),
        ];
    }

    /** @return array<string, string> */
    public function context(?Vorgang $vorgang): array
    {
        $context = [
            'app_name' => (string) config('app.name'),
            'melde_id' => '',
            'nummer' => '',
            'status' => '',
            'art' => '',
            'ort' => '',
            'vorname' => '',
            'gemeldet_am' => '',
            'bearbeiter' => '',
            'ergebnis' => '',
        ];

        if ($vorgang === null) {
            return $context;
        }

        return array_merge($context, [
            'melde_id' => (string) ($vorgang->melde_id ?: $vorgang->local_nr),
            'nummer' => (string) $vorgang->local_nr,
            'status' => (string) $vorgang->status?->name,
            'art' => (string) ($vorgang->reported_species_raw ?: 'unbekannt'),
            'ort' => (string) ($vorgang->geo_label ?: 'nicht ermittelt'),
            // Falls back to a neutral greeting rather than an empty gap.
            'vorname' => (string) ($vorgang->reporter_name ?: 'zusammen'),
            'gemeldet_am' => $vorgang->submitted_at?->format('d.m.Y') ?? '',
            'bearbeiter' => (string) ($vorgang->assignedTo?->name ?: $vorgang->editor_name ?: ''),
            'ergebnis' => (string) ($vorgang->measure ?: $vorgang->action_needed ?: '–'),
        ]);
    }

    /**
     * @param  array<string, string|null>  $context
     */
    private function replace(string $text, array $context, bool $escape): string
    {
        foreach ($context as $key => $value) {
            $value = (string) ($value ?? '');
            $value = $escape ? e($value) : $value;

            // Tolerate {{key}}, {{ key }} and any spacing in between.
            $text = preg_replace(
                '/\{\{\s*'.preg_quote($key, '/').'\s*\}\}/u',
                str_replace('$', '\\$', $value),
                $text,
            ) ?? $text;
        }

        // Anything left unresolved would leak template syntax to the reader.
        return Str::of($text)->replaceMatches('/\{\{\s*[\w.]+\s*\}\}/u', '')->toString();
    }
}
