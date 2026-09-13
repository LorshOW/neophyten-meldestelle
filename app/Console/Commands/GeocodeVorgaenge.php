<?php

namespace App\Console\Commands;

use App\Jobs\GeocodeVorgang;
use App\Models\Vorgang;
use Illuminate\Console\Command;

/**
 * Queues reverse geocoding for every case that still has no place name.
 *
 * The jobs themselves respect Nominatim's one-request-per-second policy.
 */
class GeocodeVorgaenge extends Command
{
    protected $signature = 'geocode:vorgaenge {--retry : Auch zuvor fehlgeschlagene erneut versuchen}';

    protected $description = 'Ermittelt PLZ, Ort und Kreis zu den Koordinaten der Vorgänge';

    public function handle(): int
    {
        if ($this->option('retry')) {
            $reset = Vorgang::where('geo_failed', true)->update(['geo_failed' => false]);
            $this->info("{$reset} zuvor fehlgeschlagene Vorgänge zurückgesetzt.");
        }

        $pending = Vorgang::awaitingGeocoding()->pluck('id');

        if ($pending->isEmpty()) {
            $this->info('Für alle Vorgänge mit Koordinaten liegt bereits ein Ort vor.');

            return self::SUCCESS;
        }

        foreach ($pending as $id) {
            GeocodeVorgang::dispatch($id);
        }

        $this->info("{$pending->count()} Vorgänge zur Ortsermittlung eingeplant.");
        $this->comment('Die Warteschlange muss laufen: php artisan queue:work');

        return self::SUCCESS;
    }
}
