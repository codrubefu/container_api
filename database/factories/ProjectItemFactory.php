<?php

namespace Database\Factories;

use App\Models\ProjectContainer;
use App\Models\ProjectItem;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectItem>
 */
class ProjectItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_container_id' => ProjectContainer::factory(),
            'section_id' => Section::factory(),
            'material_id' => fake()->numberBetween(1, 30000),
            'material_code' => 'sarma sudura 1.0 mm',
            'material_name' => 'sarma sudura 1.0 mm',
            'material_unit' => 'KGR',
            'planned_qty' => 10,
            'status' => ProjectItem::STATUS_NOT_STARTED,
        ];
    }
}
