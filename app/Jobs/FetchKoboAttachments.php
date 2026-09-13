<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Models\KoboSubmission;
use App\Services\Kobo\KoboApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Downloads the photos belonging to a submission into local storage.
 *
 * Kobo attachment URLs require the API token, so the files cannot simply be
 * linked from the admin UI. They land on a private disk and are served through
 * a signed route - they are personal data and often show private property.
 */
class FetchKoboAttachments implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public readonly int $submissionId)
    {
    }

    public function handle(): void
    {
        $submission = KoboSubmission::with('vorgang')->find($this->submissionId);

        if ($submission === null || $submission->vorgang === null) {
            return;
        }

        $client = KoboApiClient::fromConfig();

        if (! $client->isConfigured()) {
            // Without credentials the photos stay in Kobo. This is a
            // configuration gap, not a submission error, so do not retry.
            Log::warning('Kobo-Anhänge übersprungen: KOBO_API_TOKEN/KOBO_ASSET_UID fehlen.', [
                'submission_id' => $submission->getKey(),
            ]);

            return;
        }

        $disk = config('meldestelle.attachment_disk', 'local');

        foreach ($submission->attachmentDescriptors() as $descriptor) {
            $this->store($submission, $descriptor, $client, $disk);
        }
    }

    /** @param array<string, mixed> $descriptor */
    private function store(KoboSubmission $submission, array $descriptor, KoboApiClient $client, string $disk): void
    {
        $url = $descriptor['download_url'] ?? null;
        $koboId = isset($descriptor['id']) && is_numeric($descriptor['id']) ? (int) $descriptor['id'] : null;

        if (! is_string($url) || $url === '') {
            return;
        }

        // Already downloaded (this job retries, and a submission may be replayed).
        if ($koboId !== null && Attachment::where('kobo_attachment_id', $koboId)->exists()) {
            return;
        }

        try {
            $response = $client->downloadAttachment($url);
        } catch (Throwable $e) {
            Log::warning('Kobo-Anhang konnte nicht geladen werden.', [
                'submission_id' => $submission->getKey(),
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $body = $response->body();
        $original = $this->filename($descriptor, $url);
        $path = sprintf(
            'meldungen/%s/%s-%s',
            $submission->vorgang->melde_id,
            Str::random(8),
            $original,
        );

        Storage::disk($disk)->put($path, $body);

        Attachment::create([
            'attachable_type' => $submission->vorgang->getMorphClass(),
            'attachable_id' => $submission->vorgang->getKey(),
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $original,
            'mime' => $descriptor['mimetype'] ?? $response->header('Content-Type') ?: null,
            'size' => strlen($body),
            'source' => Attachment::SOURCE_KOBO,
            'kobo_attachment_id' => $koboId,
            'kobo_download_url' => $url,
            'checksum' => hash('sha256', $body),
        ]);
    }

    /** @param array<string, mixed> $descriptor */
    private function filename(array $descriptor, string $url): string
    {
        $raw = $descriptor['filename'] ?? basename(parse_url($url, PHP_URL_PATH) ?: 'anhang');

        // Kobo returns a path like "user/attachments/uuid/photo.jpg".
        $name = basename((string) $raw);

        // Never trust a filename from a public submission as a storage path.
        $sanitised = Str::of($name)
            ->replaceMatches('/[^A-Za-z0-9._-]/', '_')
            ->limit(120, '')
            ->toString();

        return $sanitised !== '' ? $sanitised : 'anhang';
    }
}
