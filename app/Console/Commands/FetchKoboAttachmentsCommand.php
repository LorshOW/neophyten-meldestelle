<?php

namespace App\Console\Commands;

use App\Services\Kobo\KoboSyncService;
use Illuminate\Console\Command;

/**
 * Downloads the photos that are still sitting in KoboToolbox.
 *
 * The webhook and `kobo:sync` only queue the download, so without a running
 * worker the photos never arrive. This does it directly.
 */
class FetchKoboAttachmentsCommand extends Command
{
    protected $signature = 'kobo:fetch-attachments {--limit= : Höchstens so viele Meldungen bearbeiten}';

    protected $description = 'Lädt fehlende Fotos aus KoboToolbox herunter';

    public function handle(KoboSyncService $sync): int
    {
        if (! $sync->isConfigured()) {
            $this->error('KOBO_API_TOKEN und KOBO_ASSET_UID müssen gesetzt sein.');

            return self::FAILURE;
        }

        $status = $sync->attachmentStatus();

        if ($status['missing'] === 0) {
            $this->info("Alle {$status['expected']} Fotos sind bereits übertragen.");

            return self::SUCCESS;
        }

        $this->info("{$status['missing']} von {$status['expected']} Fotos fehlen – werden geladen …");

        $limit = $this->option('limit');
        $result = $sync->fetchMissingAttachments($limit !== null ? (int) $limit : null);

        $this->info(sprintf(
            '%d Meldungen bearbeitet, %d Fotos geladen%s.',
            $result['submissions'],
            $result['downloaded'],
            $result['failed'] > 0 ? ", {$result['failed']} fehlgeschlagen" : '',
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
