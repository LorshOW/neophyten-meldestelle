<?php

namespace App\Console\Commands;

use App\Models\Vorgang;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes closed cases once the configured retention period has passed.
 *
 * The static application had no deletion at all ("Eine automatische Löschfrist
 * ist derzeit nicht festgelegt"), so this only runs when an administrator sets
 * a retention period.
 */
class PurgeVorgaenge extends Command
{
    protected $signature = 'vorgaenge:purge {--dry-run : Nur anzeigen, nichts löschen}';

    protected $description = 'Löscht abgeschlossene Vorgänge nach Ablauf der Aufbewahrungsfrist';

    public function handle(): int
    {
        $days = config('meldestelle.retention_days');

        if (blank($days) || (int) $days < 1) {
            $this->info('Keine Aufbewahrungsfrist konfiguriert – es wird nichts gelöscht.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays((int) $days);

        $vorgaenge = Vorgang::query()
            ->whereNotNull('closed_at')
            ->where('closed_at', '<', $cutoff)
            ->with('attachments')
            ->get();

        if ($vorgaenge->isEmpty()) {
            $this->info('Keine Vorgänge älter als '.$days.' Tage nach Abschluss.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['Nummer', 'Abgeschlossen am', 'Anhänge'],
                $vorgaenge->map(fn (Vorgang $v) => [
                    $v->local_nr,
                    $v->closed_at?->format('d.m.Y'),
                    $v->attachments->count(),
                ])->all(),
            );

            $this->comment("Probelauf: {$vorgaenge->count()} Vorgänge würden gelöscht.");

            return self::SUCCESS;
        }

        $files = 0;

        foreach ($vorgaenge as $vorgang) {
            // Remove the stored photos too - leaving personal data behind
            // would defeat the purpose of the retention period.
            foreach ($vorgang->attachments as $attachment) {
                if (Storage::disk($attachment->disk)->exists($attachment->path)) {
                    Storage::disk($attachment->disk)->delete($attachment->path);
                    $files++;
                }
            }

            $vorgang->attachments()->delete();
            $vorgang->forceDelete();
        }

        $this->info("{$vorgaenge->count()} Vorgänge und {$files} Dateien gelöscht.");

        return self::SUCCESS;
    }
}
