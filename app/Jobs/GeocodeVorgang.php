<?php

namespace App\Jobs;

use App\Models\Vorgang;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Reverse geocodes one Vorgang through Nominatim, filling PLZ, Ort and Kreis.
 *
 * Mirrors runGeocodeQueue() from the reference monitor application, including
 * its one-request-per-1.1s pacing: Nominatim's usage policy allows at most one
 * request per second, and ignoring that gets the deployment blocked.
 */
class GeocodeVorgang implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $vorgangId)
    {
    }

    /** Only one geocoding job runs at a time, so the pacing below holds. */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('nominatim'))->releaseAfter(5)->expireAfter(120)];
    }

    public function handle(): void
    {
        $vorgang = Vorgang::find($this->vorgangId);

        if ($vorgang === null || $vorgang->latitude === null || $vorgang->longitude === null) {
            return;
        }

        // Already resolved - nothing to do.
        if (filled($vorgang->geo_label)) {
            return;
        }

        // Nominatim: max one request per second. Stay just under it.
        $executed = RateLimiter::attempt('nominatim', 1, fn () => true, 2);

        if (! $executed) {
            $this->release(2);

            return;
        }

        try {
            $response = Http::withHeaders([
                // Nominatim requires an identifying User-Agent.
                'User-Agent' => config('app.name').' (Neophyten-Meldestelle)',
            ])
                ->timeout(20)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $vorgang->latitude,
                    'lon' => $vorgang->longitude,
                    'zoom' => 16,
                    'addressdetails' => 1,
                    'accept-language' => 'de',
                ])
                ->throw();
        } catch (Throwable $e) {
            Log::warning('Reverse-Geocoding fehlgeschlagen.', [
                'vorgang_id' => $vorgang->getKey(),
                'error' => $e->getMessage(),
            ]);

            // Mark as failed only once the retries are exhausted, so a passing
            // outage does not permanently blank the location.
            if ($this->attempts() >= $this->tries) {
                $vorgang->forceFill(['geo_failed' => true])->save();
            }

            throw $e;
        }

        $address = (array) ($response->json('address') ?? []);

        $plz = $this->pick($address, ['postcode']);
        $ort = $this->pick($address, ['city', 'town', 'village', 'municipality', 'suburb']);
        $kreis = $this->pick($address, ['county', 'state_district', 'district']);

        $label = collect([$plz, $ort, $kreis])->filter()->implode(' | ');

        $vorgang->forceFill([
            'geo_plz' => $plz,
            'geo_ort' => $ort,
            'geo_kreis' => $kreis,
            'geo_label' => $label !== '' ? $label : 'Unbekannt',
            'geo_failed' => false,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $address
     * @param  list<string>  $keys
     */
    private function pick(array $address, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (filled($address[$key] ?? null)) {
                return (string) $address[$key];
            }
        }

        return null;
    }
}
