<?php

namespace App\Services\Kobo;

use App\Jobs\FetchKoboAttachments;
use App\Jobs\ProcessKoboSubmission;
use App\Models\Attachment;
use App\Models\KoboSubmission;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls submissions from the Kobo API instead of waiting for the webhook.
 *
 * Needed whenever the webhook is not (or not yet) delivering: no REST Service
 * configured, the application not reachable from the internet, or a delivery
 * that failed. Safe to run at any time - a submission already stored is
 * recognised by its (asset_uid, kobo_id) pair and skipped.
 */
class KoboSyncService
{
    /** Kobo caps a single page; stay well under it and page through. */
    private const PAGE_SIZE = 200;

    public function __construct(private readonly KoboApiClient $client)
    {
    }

    public static function fromConfig(): self
    {
        return new self(KoboApiClient::fromConfig());
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * @param  string|null  $since  YYYY-MM-DD, limits the run to newer submissions
     * @param  bool  $process  map the raw rows to Vorgänge as well
     * @param  bool  $queue  false maps them immediately instead of queueing, so
     *                       the caller sees finished Vorgänge even when no queue
     *                       worker is running
     */
    public function sync(
        ?string $since = null,
        bool $process = true,
        ?int $max = null,
        bool $queue = true,
    ): KoboSyncResult {
        $result = new KoboSyncResult;

        if (! $this->isConfigured()) {
            $result->error = 'KOBO_API_TOKEN und KOBO_ASSET_UID sind nicht gesetzt.';

            return $result;
        }

        $start = 0;

        do {
            $query = ['limit' => self::PAGE_SIZE, 'start' => $start, 'sort' => '{"_id":1}'];

            if (filled($since)) {
                $query['query'] = json_encode(['_submission_time' => ['$gte' => $since]]);
            }

            try {
                $response = $this->client->submissions($query);
            } catch (Throwable $e) {
                $result->error = $e->getMessage();

                Log::error('Kobo-Synchronisierung fehlgeschlagen.', ['error' => $e->getMessage()]);

                return $result;
            }

            $page = $response['results'] ?? [];

            foreach ($page as $payload) {
                $result->fetched++;

                $this->importOne($payload, $process, $queue, $result);

                if ($max !== null && $result->imported >= $max) {
                    return $result;
                }
            }

            $start += count($page);
            $total = (int) ($response['count'] ?? 0);
        } while ($page !== [] && $start < $total);

        return $result;
    }

    /** @param array<string, mixed> $payload */
    private function importOne(array $payload, bool $process, bool $queue, KoboSyncResult $result): void
    {
        $koboId = isset($payload['_id']) && is_numeric($payload['_id']) ? (int) $payload['_id'] : null;

        if ($koboId === null) {
            $result->invalid++;

            return;
        }

        $assetUid = $payload['_xform_id_string'] ?? $this->client->assetUid();

        if (KoboSubmission::where('asset_uid', $assetUid)->where('kobo_id', $koboId)->exists()) {
            $result->skipped++;

            return;
        }

        $submission = KoboSubmission::create([
            'asset_uid' => $assetUid,
            'kobo_id' => $koboId,
            'kobo_uuid' => $payload['_uuid'] ?? null,
            'instance_id' => $payload['meta/instanceID'] ?? null,
            'payload' => $payload,
            'received_at' => now(),
            'processing_status' => KoboSubmission::STATUS_PENDING,
        ]);

        if ($process) {
            $queue
                ? ProcessKoboSubmission::dispatch($submission->getKey())
                : ProcessKoboSubmission::dispatchSync($submission->getKey());
        }

        $result->imported++;
    }

    /**
     * Downloads the photos that are still missing, without going through the
     * queue.
     *
     * The webhook and the sync only queue the download, so on an installation
     * without a running worker the photos stay in KoboToolbox indefinitely.
     * This is the manual way out.
     *
     * @return array{submissions: int, downloaded: int, failed: int}
     */
    public function fetchMissingAttachments(?int $maxSubmissions = null): array
    {
        $before = Attachment::count();
        $handled = 0;
        $failed = 0;

        $pending = KoboSubmission::query()
            ->whereNotNull('vorgang_id')
            ->latest('kobo_id')
            ->get()
            ->filter(fn (KoboSubmission $s) => $this->hasMissingAttachments($s));

        if ($maxSubmissions !== null) {
            $pending = $pending->take($maxSubmissions);
        }

        foreach ($pending as $submission) {
            try {
                FetchKoboAttachments::dispatchSync($submission->getKey());
                $handled++;
            } catch (Throwable $e) {
                $failed++;

                Log::warning('Anhänge konnten nicht geladen werden.', [
                    'submission_id' => $submission->getKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'submissions' => $handled,
            'downloaded' => Attachment::count() - $before,
            'failed' => $failed,
        ];
    }

    /** Does Kobo list attachments that are not stored here yet? */
    private function hasMissingAttachments(KoboSubmission $submission): bool
    {
        $ids = array_values(array_filter(array_map(
            fn (array $a) => isset($a['id']) && is_numeric($a['id']) ? (int) $a['id'] : null,
            $submission->attachmentDescriptors(),
        )));

        if ($ids === []) {
            return false;
        }

        return Attachment::whereIn('kobo_attachment_id', $ids)->count() < count($ids);
    }

    /**
     * How many photos Kobo lists versus how many are stored here.
     *
     * @return array{expected: int, stored: int, missing: int}
     */
    public function attachmentStatus(): array
    {
        $expected = KoboSubmission::all()
            ->sum(fn (KoboSubmission $s) => count($s->attachmentDescriptors()));

        $stored = Attachment::where('source', Attachment::SOURCE_KOBO)->count();

        return [
            'expected' => $expected,
            'stored' => $stored,
            'missing' => max(0, $expected - $stored),
        ];
    }

    /**
     * How many submissions exist in Kobo versus how many are stored here.
     *
     * @return array{remote: ?int, local: int, missing: ?int, error: ?string}
     */
    public function status(): array
    {
        $local = KoboSubmission::count();

        if (! $this->isConfigured()) {
            return ['remote' => null, 'local' => $local, 'missing' => null,
                'error' => 'KOBO_API_TOKEN und KOBO_ASSET_UID sind nicht gesetzt.'];
        }

        try {
            $remote = (int) ($this->client->submissions(['limit' => 1])['count'] ?? 0);
        } catch (Throwable $e) {
            return ['remote' => null, 'local' => $local, 'missing' => null, 'error' => $e->getMessage()];
        }

        return [
            'remote' => $remote,
            'local' => $local,
            'missing' => max(0, $remote - $local),
            'error' => null,
        ];
    }
}
