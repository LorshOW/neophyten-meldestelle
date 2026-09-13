<?php

namespace Database\Factories;

use App\Models\Vorgang;
use App\Models\WorkflowStatus;
use App\Support\Priority;
use App\Support\RiskAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vorgang> */
class VorgangFactory extends Factory
{
    protected $model = Vorgang::class;

    public function definition(): array
    {
        $latitude = $this->faker->randomFloat(6, 50.55, 50.85);
        $longitude = $this->faker->randomFloat(6, 12.70, 13.20);

        $riskFlags = array_fill_keys(RiskAssessment::options(), false);

        return [
            'local_nr' => null,
            'melde_id' => Vorgang::generateMeldeId(),
            'status_id' => WorkflowStatus::query()->where('is_initial', true)->value('id')
                ?? WorkflowStatus::factory(),

            'reported_species_raw' => 'Kanadische Goldrute',
            'knows_species' => 'Ja, ich kenne die Art',
            'location_summary' => 'Straßen- oder Wegrand',
            'size_category' => 'Mittel – ca. 5–20 m² (etwa PKW-Stellplatz)',
            'citizen_note' => $this->faker->optional()->sentence(),

            'risk_flags' => $riskFlags,
            'editor_risk' => [],
            'priority' => Priority::NORMAL,

            'geometry_type' => 'point',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'geojson' => ['type' => 'Point', 'coordinates' => [$longitude, $latitude]],

            'geo_ort' => $this->faker->city(),
            'geo_kreis' => 'Erzgebirgskreis',

            'reporter_name' => $this->faker->firstName(),
            'reporter_contact_raw' => $email = $this->faker->safeEmail(),
            'reporter_email' => $email,
            'reporter_contact_consent' => true,

            'submitted_at' => now()->subDays($this->faker->numberBetween(0, 30)),
            'captured_offline' => false,
        ];
    }

    public function inStatus(string $key): static
    {
        return $this->state(fn () => [
            'status_id' => WorkflowStatus::query()->where('key', $key)->value('id'),
        ]);
    }

    public function withoutConsent(): static
    {
        return $this->state(fn () => [
            'reporter_contact_consent' => false,
        ]);
    }

    public function withoutContact(): static
    {
        return $this->state(fn () => [
            'reporter_name' => null,
            'reporter_contact_raw' => null,
            'reporter_email' => null,
            'reporter_contact_consent' => false,
        ]);
    }

    /** Risk assessment that makes computePriority() return "Hoch". */
    public function highRisk(): static
    {
        return $this->state(fn () => [
            'editor_risk' => [RiskAssessment::HUMAN],
        ]);
    }

    public function numbered(): static
    {
        return $this->state(fn () => ['local_nr' => Vorgang::nextLocalNr()]);
    }
}
