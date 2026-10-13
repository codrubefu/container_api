<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => fake()->unique()->numberBetween(1, 999999),
            'contract_number' => fake()->numerify('### - ORAS'),
            'client_name' => fake()->company(),
            'name' => fake()->company(),
            'deadline' => null,
            'status' => Project::STATUS_DRAFT,
        ];
    }
}
