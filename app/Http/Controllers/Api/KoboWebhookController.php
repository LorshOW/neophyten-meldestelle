<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessKoboSubmission;
use App\Models\KoboSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives KoboToolbox REST Service calls.
 *
 * Does as little as possible: authenticate, store the payload verbatim, queue
 * the processing, answer. Kobo retries on non-2xx, so the duplicate path must
 * also answer 200 - otherwise a submission that already arrived would be
 * retried forever.
 */
class KoboWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->authorizeRequest($request)) {
            Log::warning('Kobo-Webhook abgelehnt.', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            return response()->json(['message' => 'Leerer oder ungültiger Payload.'], Response::HTTP_BAD_REQUEST);
        }

        $assetUid = $this->assetUid($payload);
        $koboId = isset($payload['_id']) && is_numeric($payload['_id']) ? (int) $payload['_id'] : null;

        // (asset_uid, kobo_id) is the idempotency key. A retry of a submission
        // we already stored must not create a second Vorgang.
        $existing = $koboId !== null
            ? KoboSubmission::query()
                ->where('asset_uid', $assetUid)
                ->where('kobo_id', $koboId)
                ->first()
            : null;

        if ($existing !== null) {
            return response()->json([
                'message' => 'Bereits empfangen.',
                'submission_id' => $existing->getKey(),
                'duplicate' => true,
            ]);
        }

        $submission = KoboSubmission::create([
            'asset_uid' => $assetUid,
            'kobo_id' => $koboId,
            'kobo_uuid' => $this->stringOrNull($payload['_uuid'] ?? null),
            'instance_id' => $this->stringOrNull($payload['meta/instanceID'] ?? null),
            'payload' => $payload,
            'headers' => $this->safeHeaders($request),
            'received_at' => now(),
            'processing_status' => KoboSubmission::STATUS_PENDING,
        ]);

        ProcessKoboSubmission::dispatch($submission->getKey());

        return response()->json([
            'message' => 'Empfangen.',
            'submission_id' => $submission->getKey(),
            'duplicate' => false,
        ], Response::HTTP_ACCEPTED);
    }

    private function authorizeRequest(Request $request): bool
    {
        $secret = config('kobo.webhook.secret');

        // Refuse to run unauthenticated: an open endpoint would let anyone
        // inject cases.
        if (blank($secret)) {
            Log::error('KOBO_WEBHOOK_SECRET ist nicht gesetzt - Webhook ist deaktiviert.');

            return false;
        }

        $provided = (string) $request->header(config('kobo.webhook.header', 'X-Kobo-Secret'), '');

        if (! hash_equals((string) $secret, $provided)) {
            return false;
        }

        $allowlist = (array) config('kobo.webhook.ip_allowlist', []);

        return $allowlist === [] || in_array($request->ip(), $allowlist, true);
    }

    /** @param array<string, mixed> $payload */
    private function assetUid(array $payload): ?string
    {
        return $this->stringOrNull($payload['_xform_id_string'] ?? null)
            ?? config('kobo.asset_uid');
    }

    /**
     * Headers are kept for troubleshooting, minus anything that authenticates.
     *
     * @return array<string, mixed>
     */
    private function safeHeaders(Request $request): array
    {
        $secretHeader = strtolower((string) config('kobo.webhook.header', 'X-Kobo-Secret'));

        return collect($request->headers->all())
            ->except(['authorization', 'cookie', $secretHeader])
            ->map(fn (array $values) => count($values) === 1 ? $values[0] : $values)
            ->all();
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
