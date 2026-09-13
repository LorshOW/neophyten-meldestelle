<?php

namespace App\Services\Kobo;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the KoboToolbox KPI API.
 *
 * The EU deployment splits the two hosts: the public form is served by Enketo
 * (ee-eu.kobotoolbox.org) while the API lives on eu.kobotoolbox.org. Only the
 * API needs the token.
 */
class KoboApiClient
{
    public function __construct(
        private readonly ?string $token = null,
        private readonly ?string $baseUrl = null,
        private readonly ?string $assetUid = null,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            token: config('kobo.api_token'),
            baseUrl: config('kobo.api_base'),
            assetUid: config('kobo.asset_uid'),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->token) && filled($this->assetUid);
    }

    public function assetUid(): ?string
    {
        return $this->assetUid;
    }

    /** The XLSForm definition, used to discover the real question names. */
    public function formDefinition(): array
    {
        return $this->request()
            ->get($this->url("/api/v2/assets/{$this->requireAssetUid()}/"))
            ->throw()
            ->json();
    }

    /**
     * One page of submissions.
     *
     * @param  array<string, mixed>  $query
     */
    public function submissions(array $query = []): array
    {
        return $this->request()
            ->get($this->url("/api/v2/assets/{$this->requireAssetUid()}/data/"), $query)
            ->throw()
            ->json();
    }

    /**
     * The REST Services (webhooks) KoboToolbox has registered for this asset.
     *
     * @return list<array<string, mixed>>
     */
    public function restServices(): array
    {
        $response = $this->request()
            ->get($this->url("/api/v2/assets/{$this->requireAssetUid()}/hooks/"))
            ->throw()
            ->json();

        return $response['results'] ?? [];
    }

    /**
     * Downloads an attachment. Kobo returns absolute URLs in `_attachments`,
     * which may point at a different host than the API base, so the URL is used
     * as given and only the token is attached.
     */
    public function downloadAttachment(string $url): Response
    {
        return $this->request()->withOptions(['stream' => false])->get($url)->throw();
    }

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->timeout(60)
            ->retry(3, 500, throw: false);

        if (filled($this->token)) {
            // Kobo uses the DRF token scheme, not Bearer.
            $request = $request->withHeaders(['Authorization' => 'Token '.$this->token]);
        }

        return $request;
    }

    private function url(string $path): string
    {
        return rtrim((string) $this->baseUrl, '/').$path;
    }

    private function requireAssetUid(): string
    {
        if (blank($this->assetUid)) {
            throw new RuntimeException(
                'KOBO_ASSET_UID ist nicht gesetzt. Ohne Asset-UID kann die Kobo-API nicht verwendet werden.'
            );
        }

        return $this->assetUid;
    }
}
