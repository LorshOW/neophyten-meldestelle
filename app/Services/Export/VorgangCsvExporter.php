<?php

namespace App\Services\Export;

use App\Models\Vorgang;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export in the exact column order the reference monitor produced, so the
 * office's existing spreadsheets keep working.
 *
 * Semicolon separated with a UTF-8 BOM, which is what German Excel expects.
 */
class VorgangCsvExporter
{
    private const HEADER = [
        'Nr', 'Datum', 'Art', 'PLZ', 'Ort', 'Kreis', 'Standort', 'Größe', 'Typ',
        'Priorität', 'Status', 'Interne Notiz', 'Bestätigt', 'Besucht',
        'Besuchsdatum', 'Bearbeiter', 'Handlungsbedarf', 'Maßnahme',
        'Breitengrad', 'Längengrad',
    ];

    public function stream(Builder $query, ?string $filename = null): StreamedResponse
    {
        $filename ??= 'invasive-pflanzen-uebersicht-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');

            // BOM so Excel detects UTF-8 instead of mangling the umlauts.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, self::HEADER, ';', '"', '');

            $query->with(['status', 'assignedTo'])->chunk(200, function ($chunk) use ($handle): void {
                foreach ($chunk as $vorgang) {
                    fputcsv($handle, $this->row($vorgang), ';', '"', '');
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return list<string> */
    private function row(Vorgang $v): array
    {
        return [
            (string) $v->local_nr,
            $v->submitted_at?->format('Y-m-d H:i:s') ?? '',
            (string) $v->reported_species_raw,
            (string) $v->geo_plz,
            (string) $v->geo_ort,
            (string) $v->geo_kreis,
            (string) $v->location_summary,
            (string) $v->size_category,
            match ($v->geometry_type) {
                'polygon' => 'Fläche',
                'point' => 'Punkt',
                default => 'Unbekannt',
            },
            $v->effectivePriority(),
            (string) $v->status?->name,
            (string) $v->editor_note,
            $v->confirmed ? 'Ja' : 'Nein',
            $v->visited ? 'Ja' : 'Nein',
            $v->visit_date?->format('Y-m-d') ?? '',
            (string) ($v->assignedTo?->name ?: $v->editor_name),
            (string) $v->action_needed,
            (string) $v->measure,
            $v->latitude !== null ? (string) $v->latitude : '',
            $v->longitude !== null ? (string) $v->longitude : '',
        ];
    }
}
