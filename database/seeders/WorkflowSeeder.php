<?php

namespace Database\Seeders;

use App\Models\WorkflowStatus;
use App\Models\WorkflowTransition;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/**
 * The workflow of the reference monitor application.
 *
 * Statuses, their order and the two "done" states are taken verbatim from
 * invasive-pflanzen-monitor.html (STATUS_OPTIONS, STATUS_RANK, STATUS_DONE,
 * STATUS_HEX). The transitions between them are new: the prototype let an
 * editor pick any status from a dropdown, which a multi-role system cannot do
 * safely, so the same moves are expressed as explicit, permission-checked
 * transitions.
 */
class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            [
                'key' => 'neu',
                'name' => 'Neu',
                'description' => 'Meldung ist eingegangen und noch ungeprüft.',
                'color' => 'gray',
                'is_initial' => true,
            ],
            [
                'key' => 'inpruefung',
                'name' => 'In Prüfung',
                'description' => 'Der Innendienst prüft Art, Plausibilität und Zuständigkeit.',
                'color' => 'info',
            ],
            [
                'key' => 'bestaetigt',
                'name' => 'Bestätigt',
                'description' => 'Das Vorkommen ist bestätigt.',
                'color' => 'warning',
                'visible_to_aussendienst' => true,
            ],
            [
                'key' => 'inbearbeitung',
                'name' => 'In Bearbeitung',
                'description' => 'Kontrolle bzw. Maßnahme läuft.',
                'color' => 'info',
                'requires_assignee' => true,
                'visible_to_aussendienst' => true,
            ],
            [
                'key' => 'bekaempft',
                'name' => 'Bekämpft',
                'description' => 'Die Maßnahme ist durchgeführt, der Vorgang ist abgeschlossen.',
                'color' => 'success',
                'is_terminal' => true,
                'visible_to_aussendienst' => true,
            ],
            [
                'key' => 'keinhandlungsbedarf',
                'name' => 'Kein Handlungsbedarf',
                'description' => 'Es ist nichts zu veranlassen, der Vorgang ist abgeschlossen.',
                'color' => 'gray',
                'is_terminal' => true,
            ],
        ];

        foreach ($statuses as $index => $attributes) {
            WorkflowStatus::updateOrCreate(
                ['key' => $attributes['key']],
                $attributes + ['sort_order' => ($index + 1) * 10, 'is_active' => true],
            );
        }

        $byKey = WorkflowStatus::query()->pluck('id', 'key');

        $office = Permissions::VORGANG_TRANSITION;
        $field = Permissions::VORGANG_TRANSITION_FIELD;

        $transitions = [
            // Forward along STATUS_RANK.
            ['neu', 'inpruefung', 'In Prüfung nehmen', $office, false],
            ['inpruefung', 'bestaetigt', 'Vorkommen bestätigen', $office, false],
            ['bestaetigt', 'inbearbeitung', 'Kontrolle starten', $field, false],
            ['inbearbeitung', 'bekaempft', 'Als bekämpft abschließen', $field, false],

            // The Außendienst may also report back without a measure.
            ['inbearbeitung', 'bestaetigt', 'An Innendienst zurückgeben', $field, true],

            // Backwards correction inside the office.
            ['bestaetigt', 'inpruefung', 'Zurück in die Prüfung', $office, true],
            ['inpruefung', 'neu', 'Zurücksetzen', $office, true],

            // "Kein Handlungsbedarf" is reachable from every working stage -
            // the prototype allowed it from anywhere via the status dropdown.
            [null, 'keinhandlungsbedarf', 'Kein Handlungsbedarf', $office, true],

            // Reopening a closed case.
            ['keinhandlungsbedarf', 'inpruefung', 'Wieder aufnehmen', $office, true],
            ['bekaempft', 'inbearbeitung', 'Wieder aufnehmen', $office, true],
        ];

        foreach ($transitions as $index => [$from, $to, $label, $permission, $requiresNote]) {
            WorkflowTransition::updateOrCreate(
                [
                    'from_status_id' => $from ? $byKey[$from] : null,
                    'to_status_id' => $byKey[$to],
                ],
                [
                    'label' => $label,
                    'required_permission' => $permission,
                    'requires_note' => $requiresNote,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ],
            );
        }
    }
}
