<?php

namespace App\Console\Commands;

use App\Services\Kobo\KoboSyncService;
use Illuminate\Console\Command;

/**
 * Pulls submissions from the Kobo API.
 *
 * Two jobs: the initial import of everything that arrived before the webhook
 * existed, and recovery whenever a webhook delivery was missed. Idempotent -
 * a submission already stored is skipped on its (asset_uid, kobo_id) pair.
 */
class SyncKoboSubmissions extends Command
{
    protected $signature = 'kobo:sync
                            {--since= : Nur Meldungen ab diesem Datum (YYYY-MM-DD)}
                            {--process : Neu importierte Datensätze zu Vorgängen verarbeiten}
                            {--now : Sofort verarbeiten statt über die Warteschlange}';

    protected $description = 'Holt Meldungen aus KoboToolbox nach';

    public function handle(KoboSyncService $sync): int
    {
        if (! $sync->isConfigured()) {
            $this->error('KOBO_API_TOKEN und KOBO_ASSET_UID müssen gesetzt sein.');
            $this->line('<comment>Mit `php artisan kobo:check` lässt sich die Verbindung prüfen.</comment>');

            return self::FAILURE;
        }

        $this->info('Meldungen werden aus KoboToolbox geholt …');

        $result = $sync->sync(
            since: $this->option('since'),
            process: (bool) $this->option('process'),
            queue: ! $this->option('now'),
        );

        if ($result->failed()) {
            $this->error($result->summary());

            return self::FAILURE;
        }

        $this->info($result->summary());

        if ($result->imported > 0 && ! $this->option('process')) {
            $this->comment('Mit --process werden die Rohdaten direkt zu Vorgängen verarbeitet.');
        }

        if ($result->imported > 0 && $this->option('process') && ! $this->option('now')) {
            $this->comment('Die Warteschlange muss laufen: php artisan queue:work');
        }

        return self::SUCCESS;
    }
}
