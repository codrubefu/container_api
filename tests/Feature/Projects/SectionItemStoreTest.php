<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\ProjectContainer;
use App\Models\ProjectItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeErpDatabase;
use Tests\TestCase;

class SectionItemStoreTest extends TestCase
{
    use FakeErpDatabase, RefreshDatabase;

    private User $executant;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeErpDatabase();
        DB::connection('erp')->table('genmp')->insert([
            ['idmp' => 237, 'clasa' => 'Productie Continere', 'grupa' => 'Materiale Consumab', 'art' => 'sarma sudura 1.0 mm', 'desc1' => '', 'um' => 'KGR', 'activ' => true],
            ['idmp' => 901, 'clasa' => 'Manopera', 'grupa' => 'Manopera', 'art' => 'ora manopera', 'desc1' => 'ora manopera', 'um' => 'ORA', 'activ' => true],
        ]);

        $this->executant = User::factory()->executant()->create(['name' => 'Vasile Ionescu']);
        $this->executant->sections()->sync([1]);

        $this->project = Project::factory()->create(['status' => Project::STATUS_IN_PRODUCTION]);
        ProjectContainer::factory()->count(2)->for($this->project)->create();
    }

    private function url(int $sectionId, ?Project $project = null): string
    {
        return "/api/work/sections/{$sectionId}/projects/".($project ?? $this->project)->id.'/items';
    }

    public function test_operators_add_a_material_to_their_section_and_are_recorded_as_its_author(): void
    {
        Sanctum::actingAs($this->executant);
        $containerIds = $this->project->containers->pluck('id')->all();

        $this->postJson($this->url(1), ['container_ids' => $containerIds, 'material_id' => 237, 'planned_qty' => 3])
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.section_id', 1)
            ->assertJsonPath('data.0.container_id', $containerIds[0])
            ->assertJsonPath('data.1.container_id', $containerIds[1])
            ->assertJsonPath('data.0.material.code', 'sarma sudura 1.0 mm')
            ->assertJsonPath('data.0.status', 'neinceput')
            ->assertJsonPath('data.0.created_by', ['id' => $this->executant->id, 'name' => 'Vasile Ionescu'])
            ->assertJsonPath('data.0.container.id', $containerIds[0]);

        $this->assertSame(2, ProjectItem::query()->where('section_id', 1)->where('created_by', $this->executant->id)->count());
    }

    public function test_the_section_comes_only_from_the_url(): void
    {
        Sanctum::actingAs($this->executant);
        $containerId = $this->project->containers->first()->id;

        $this->postJson($this->url(1), ['container_ids' => [$containerId], 'material_id' => 237, 'planned_qty' => 1, 'section_id' => 3])
            ->assertCreated()
            ->assertJsonPath('data.0.section_id', 1);
    }

    public function test_operators_cannot_add_to_sections_they_are_not_assigned_to(): void
    {
        Sanctum::actingAs($this->executant);
        $containerId = $this->project->containers->first()->id;

        $this->postJson($this->url(2), ['container_ids' => [$containerId], 'material_id' => 237, 'planned_qty' => 1])
            ->assertForbidden();
        $this->assertSame(0, ProjectItem::query()->count());
    }

    public function test_rejects_foreign_containers_non_production_materials_and_closed_projects(): void
    {
        Sanctum::actingAs($this->executant);
        $foreign = ProjectContainer::factory()->create();
        $payload = ['container_ids' => [$this->project->containers->first()->id], 'material_id' => 237, 'planned_qty' => 1];

        $this->postJson($this->url(1), [...$payload, 'container_ids' => [$foreign->id]])->assertJsonValidationErrors('container_ids.0');
        $this->postJson($this->url(1), [...$payload, 'material_id' => 901])->assertJsonValidationErrors('material_id');
        $this->postJson($this->url(1), [...$payload, 'planned_qty' => 0])->assertJsonValidationErrors('planned_qty');

        $this->project->update(['status' => Project::STATUS_CLOSED]);
        $this->postJson($this->url(1), $payload)->assertJsonValidationErrors('project');
        $this->assertSame(0, ProjectItem::query()->count());
    }

    public function test_designers_cannot_use_the_operator_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->designer()->create());

        $this->postJson($this->url(1), ['container_ids' => [$this->project->containers->first()->id], 'material_id' => 237, 'planned_qty' => 1])
            ->assertForbidden();
    }

    public function test_operators_can_search_the_production_catalogue(): void
    {
        Sanctum::actingAs($this->executant);

        $this->getJson('/api/work/materials')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 237);
        $this->getJson('/api/work/material-categories')->assertOk()->assertExactJson(['data' => ['Materiale Consumab']]);
    }

    public function test_items_list_includes_the_projects_containers_and_who_changed_each_item(): void
    {
        $item = ProjectItem::factory()->for($this->project->containers->first(), 'container')->create(['section_id' => 1]);
        Sanctum::actingAs($this->executant);

        $this->patchJson("/api/work/items/{$item->id}", ['status' => 'in_lucru'])
            ->assertOk()
            ->assertJsonPath('data.updated_by', ['id' => $this->executant->id, 'name' => 'Vasile Ionescu']);

        $this->getJson($this->url(1))
            ->assertOk()
            ->assertJsonCount(2, 'project.containers')
            ->assertJsonPath('project.containers.0.id', $this->project->containers->first()->id)
            ->assertJsonPath('data.0.created_by', null)
            ->assertJsonPath('data.0.updated_by.name', 'Vasile Ionescu');
    }
}
