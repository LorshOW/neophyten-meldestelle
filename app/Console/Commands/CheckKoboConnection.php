<?php

namespace App\Console\Commands;

use App\Services\Kobo\KoboDiagnostics;
use Illuminate\Console\Command;

/**
 * Diagnoses the KoboToolbox connection, in particular why the webhook is not
 * delivering anything.
 */
class CheckKoboConnection extends Command
{
    protected $signature = 'kobo:check';

    protected $description = 'Prüft die Verbindung zu KoboToolbox und den Webhook';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>Verbindung zu KoboToolbox</>');
        $this->newLine();

        $checks = KoboDiagnostics::fromConfig()->run();
        $failed = 0;

        foreach ($checks as $check) {
            [$icon, $style] = match ($check['status']) {
                KoboDiagnostics::OK => ['✔', 'info'],
                KoboDiagnostics::WARN => ['!', 'comment'],
                default => ['✖', 'error'],
            };

            if ($check['status'] === KoboDiagnostics::FAIL) {
                $failed++;
            }

            $this->line("  <{$style}>{$icon}</{$style}>  <options=bold>{$check['label']}</>");
            $this->line('     '.$check['detail']);

            if ($check['hint']) {
                $this->line("     <comment>→ {$check['hint']}</comment>");
            }

            $this->newLine();
        }

        if ($failed > 0) {
            $this->error("  {$failed} Prüfung(en) fehlgeschlagen.");
            $this->line('  <comment>Solange der Webhook nicht liefert, holt `php artisan kobo:sync --process`</comment>');
            $this->line('  <comment>die Meldungen direkt über die API.</comment>');
            $this->newLine();

            return self::FAILURE;
        }

        $this->info('  Alle Prüfungen bestanden.');
        $this->newLine();

        return self::SUCCESS;
    }
}
