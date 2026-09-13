<?php

namespace Database\Factories;

use App\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Species> */
class SpeciesFactory extends Factory
{
    protected $model = Species::class;

    public function definition(): array
    {
        return [
            'name_de' => $this->faker->unique()->word(),
            'name_latin' => $this->faker->unique()->words(2, true),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
