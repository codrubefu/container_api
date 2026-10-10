<?php

namespace Tests\Feature\Users;

use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * `work` on a section = may change status / actual quantity of the section's project items.
 */
class SectionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_executant_can_work_on_an_assigned_section(): void
    {
        $executant = User::factory()->executant()->create();
        $welding = Section::query()->findOrFail(1);
        $executant->sections()->attach($welding);

        $this->assertTrue(Gate::forUser($executant)->allows('work', $welding));
    }

    public function test_executant_cannot_work_on_an_unassigned_section(): void
    {
        $executant = User::factory()->executant()->create();
        $executant->sections()->attach(1);

        $this->assertTrue(Gate::forUser($executant)->denies('work', Section::query()->findOrFail(2)));
    }

    public function test_section_assignment_alone_is_not_enough_without_the_work_permission(): void
    {
        $designer = User::factory()->designer()->create();
        $designer->sections()->attach(1);

        $this->assertTrue(Gate::forUser($designer)->denies('work', Section::query()->findOrFail(1)));
    }

    public function test_admin_can_work_on_any_section(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('work', Section::query()->findOrFail(3)));
    }

    public function test_permission_slugs_resolve_through_the_gate(): void
    {
        $executant = User::factory()->executant()->create();

        $this->assertTrue(Gate::forUser($executant)->allows('project_items.work'));
        $this->assertTrue(Gate::forUser($executant)->denies('project_items.manage'));
    }
}
