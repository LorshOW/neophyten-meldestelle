<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\NotificationRule;
use App\Models\WorkflowStatus;
use App\Support\Roles;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /** Placeholders every template may use. */
    private const PLACEHOLDERS = [
        'melde_id', 'status', 'art', 'ort', 'vorname',
        'gemeldet_am', 'bearbeiter', 'ergebnis', 'app_name',
    ];

    public function run(): void
    {
        $templates = [
            [
                'key' => 'eingang_bestaetigung',
                'name' => 'Eingangsbestätigung / Dankesmail',
                'description' => 'Geht an die meldende Person, sobald die Meldung eingegangen ist.',
                'subject' => 'Danke für deine Meldung ({{ melde_id }})',
                'body_html' => <<<'HTML'
                    <p>Hallo {{ vorname }},</p>
                    <p>vielen Dank für deine Meldung einer invasiven Pflanze. Sie ist bei uns
                    eingegangen und wird geprüft.</p>
                    <p><strong>Meldenummer:</strong> {{ melde_id }}<br>
                    <strong>Eingegangen am:</strong> {{ gemeldet_am }}<br>
                    <strong>Ort:</strong> {{ ort }}</p>
                    <p>Mit deiner Meldung hilfst du, die Ausbreitung invasiver Arten in unserer
                    Region besser zu dokumentieren.</p>
                    <p>Viele Grüße<br>{{ app_name }}</p>
                    HTML,
            ],
            [
                'key' => 'zuweisung_aussendienst',
                'name' => 'Zuweisung an den Außendienst',
                'description' => 'Geht an die Person, der ein Vorgang zugewiesen wurde.',
                'subject' => 'Neuer Vorgang zugewiesen: {{ melde_id }}',
                'body_html' => <<<'HTML'
                    <p>Hallo {{ bearbeiter }},</p>
                    <p>dir wurde ein neuer Vorgang zur Vor-Ort-Kontrolle zugewiesen.</p>
                    <p><strong>Meldenummer:</strong> {{ melde_id }}<br>
                    <strong>Gemeldete Art:</strong> {{ art }}<br>
                    <strong>Ort:</strong> {{ ort }}</p>
                    <p>Die Details findest du in deinem Außendienst-Bereich.</p>
                    HTML,
            ],
            [
                'key' => 'status_update',
                'name' => 'Statusänderung',
                'description' => 'Allgemeine Statusmitteilung, standardmäßig nicht aktiv verknüpft.',
                'subject' => 'Deine Meldung {{ melde_id }}: neuer Stand',
                'body_html' => <<<'HTML'
                    <p>Hallo {{ vorname }},</p>
                    <p>der Stand deiner Meldung {{ melde_id }} hat sich geändert.</p>
                    <p><strong>Aktueller Status:</strong> {{ status }}</p>
                    <p>Viele Grüße<br>{{ app_name }}</p>
                    HTML,
            ],
            [
                'key' => 'kontrolle_abgeschlossen',
                'name' => 'Vor-Ort-Kontrolle abgeschlossen',
                'description' => 'Interne Information an den Innendienst.',
                'subject' => 'Kontrolle abgeschlossen: {{ melde_id }}',
                'body_html' => <<<'HTML'
                    <p>Die Vor-Ort-Kontrolle zu {{ melde_id }} wurde abgeschlossen.</p>
                    <p><strong>Bearbeiter:in:</strong> {{ bearbeiter }}<br>
                    <strong>Ergebnis:</strong> {{ ergebnis }}</p>
                    HTML,
            ],
            [
                'key' => 'vorgang_abgeschlossen',
                'name' => 'Vorgang abgeschlossen',
                'description' => 'Abschlussmitteilung an die meldende Person.',
                'subject' => 'Deine Meldung {{ melde_id }} ist abgeschlossen',
                'body_html' => <<<'HTML'
                    <p>Hallo {{ vorname }},</p>
                    <p>deine Meldung {{ melde_id }} wurde bearbeitet und ist jetzt abgeschlossen.</p>
                    <p><strong>Ergebnis:</strong> {{ ergebnis }}</p>
                    <p>Danke, dass du dir die Zeit genommen hast.</p>
                    <p>Viele Grüße<br>{{ app_name }}</p>
                    HTML,
            ],
        ];

        foreach ($templates as $attributes) {
            EmailTemplate::updateOrCreate(
                ['key' => $attributes['key']],
                $attributes + [
                    'available_placeholders' => self::PLACEHOLDERS,
                    'locale' => 'de',
                    'is_active' => true,
                ],
            );
        }

        $this->seedRules();
    }

    /**
     * Default automation. Only rules that are safe under the published
     * Datenschutzerklärung are active: the reporter gets a confirmation and a
     * closing note, both gated on explicit consent by the dispatcher.
     */
    private function seedRules(): void
    {
        $templates = EmailTemplate::query()->pluck('id', 'key');
        $statuses = WorkflowStatus::query()->pluck('id', 'key');

        $rules = [
            [
                'name' => 'Eingangsbestätigung an meldende Person',
                'trigger_event' => NotificationRule::EVENT_VORGANG_CREATED,
                'status_id' => null,
                'email_template_id' => $templates['eingang_bestaetigung'],
                'recipient_type' => NotificationRule::RECIPIENT_REPORTER,
                'recipient_value' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Zuweisung an Außendienst',
                'trigger_event' => NotificationRule::EVENT_VORGANG_ASSIGNED,
                'status_id' => null,
                'email_template_id' => $templates['zuweisung_aussendienst'],
                'recipient_type' => NotificationRule::RECIPIENT_ASSIGNEE,
                'recipient_value' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Kontrolle abgeschlossen an Innendienst',
                'trigger_event' => NotificationRule::EVENT_INSPECTION_COMPLETED,
                'status_id' => null,
                'email_template_id' => $templates['kontrolle_abgeschlossen'],
                'recipient_type' => NotificationRule::RECIPIENT_ROLE,
                'recipient_value' => Roles::INNENDIENST,
                'is_active' => true,
            ],
        ];

        // One closing notice per terminal status. A status_changed rule with a
        // null status_id would fire on every single transition, so each rule
        // must name the status it belongs to.
        foreach (['bekaempft' => 'bekämpft', 'keinhandlungsbedarf' => 'kein Handlungsbedarf'] as $key => $label) {
            if (! isset($statuses[$key])) {
                continue;
            }

            $rules[] = [
                'name' => "Abschlussmitteilung an meldende Person ({$label})",
                'trigger_event' => NotificationRule::EVENT_STATUS_CHANGED,
                'status_id' => $statuses[$key],
                'email_template_id' => $templates['vorgang_abgeschlossen'],
                'recipient_type' => NotificationRule::RECIPIENT_REPORTER,
                'recipient_value' => null,
                'is_active' => true,
            ];
        }

        foreach ($rules as $rule) {
            NotificationRule::updateOrCreate(
                ['name' => $rule['name']],
                $rule,
            );
        }
    }
}
