<?php

namespace Database\Factories;

use App\Models\WorkflowStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<WorkflowStatus> */
class WorkflowStatusFactory extends Factory
{
    protected $model = WorkflowStatus::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'key' => Str::slug($name, '_'),
            'name' => Str::ucfirst($name),
            'color' => 'gray',
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
