<?php

namespace Database\Seeders;

use App\Models\Species;
use Illuminate\Database\Seeder;

/**
 * Transcribed from the NEOPHYTEN array of the original static application so
 * the public plant overview and the case system share one source of truth.
 *
 * kobo_value is left null: the choice values of the live Kobo form are not
 * derivable from the static app and must be filled in once the form definition
 * has been inspected (php artisan kobo:inspect-form).
 */
class SpeciesSeeder extends Seeder
{
    public function run(): void
    {
        $species = [
            [
                'name_de' => 'Riesen-Bärenklau',
                'name_latin' => 'Heracleum mantegazzianum',
                'wiki_title' => 'Riesen-Bärenklau',
                'habitat' => 'Bachufer, Wegränder, feuchte Wiesen',
                'warning_text' => 'Pflanzensaft kann bei Sonnenlicht schwere Hautverbrennungen verursachen – nicht berühren!',
            ],
            [
                'name_de' => 'Japanischer Staudenknöterich',
                'name_latin' => 'Reynoutria japonica',
                'wiki_title' => 'Japanischer Staudenknöterich',
                'habitat' => 'Flussufer, Bahndämme, Brachflächen',
                'image_url' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/5/50/Fallopia_japonica_20250620_203130.jpg/1280px-Fallopia_japonica_20250620_203130.jpg',
            ],
            [
                'name_de' => 'Drüsiges Springkraut',
                'name_latin' => 'Impatiens glandulifera',
                'wiki_title' => 'Drüsiges Springkraut',
                'habitat' => 'Feuchte Wälder, Bachufer, Gräben',
            ],
            [
                'name_de' => 'Kleinblütiges Springkraut',
                'name_latin' => 'Impatiens parviflora',
                'wiki_title' => 'Kleinblütiges Springkraut',
                'habitat' => 'Schattige Wälder, Wegränder, Parks',
            ],
            [
                'name_de' => 'Kanadische Goldrute',
                'name_latin' => 'Solidago canadensis',
                'wiki_title' => 'Kanadische Goldrute',
                'habitat' => 'Brachflächen, Wegränder, trockene Wiesen',
            ],
            [
                'name_de' => 'Einjähriges Berufkraut',
                'name_latin' => 'Erigeron annuus',
                'wiki_title' => 'Einjähriges Berufkraut',
                'habitat' => 'Wegränder, Brachflächen, Bahngelände',
            ],
            [
                'name_de' => 'Götterbaum',
                'name_latin' => 'Ailanthus altissima',
                'wiki_title' => 'Götterbaum',
                'habitat' => 'Bahngelände, Stadtränder, Mauerritzen',
            ],
            [
                'name_de' => 'Essigbaum',
                'name_latin' => 'Rhus typhina',
                'wiki_title' => 'Essigbaum',
                'habitat' => 'Gärten, Böschungen, Waldränder',
            ],
            [
                'name_de' => 'Sommerflieder (Schmetterlingsflieder)',
                'name_latin' => 'Buddleja davidii',
                'wiki_title' => 'Sommerflieder',
                'habitat' => 'Bahndämme, Schutt, Ruderalflächen',
            ],
            [
                'name_de' => 'Vielblättrige Lupine',
                'name_latin' => 'Lupinus polyphyllus',
                'wiki_title' => 'Vielblättrige Lupine',
                'habitat' => 'Wegränder, Wiesen, Böschungen, Waldlichtungen',
            ],
        ];

        foreach ($species as $index => $attributes) {
            Species::updateOrCreate(
                ['name_latin' => $attributes['name_latin']],
                $attributes + ['sort_order' => ($index + 1) * 10, 'is_active' => true],
            );
        }
    }
}
