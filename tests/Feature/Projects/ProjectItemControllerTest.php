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

class ProjectItemControllerTest extends TestCase
{
    use FakeErpDatabase, RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeErpDatabase();
        DB::connection('erp')->table('genmp')->insert([
            ['idmp' => 237, 'clasa' => 'Productie Continere', 'grupa' => 'Materiale Consumab', 'art' => 'sarma sudura 1.0 mm', 'desc1' => '', 'um' => 'KGR', 'activ' => true],
            ['idmp' => 901, 'clasa' => 'Manopera', 'grupa' => 'Manopera', 'art' => 'ora manopera', 'desc1' => 'ora manopera', 'um' => 'ORA', 'activ' => true],
        ]);

        $this->project = Project::factory()->create();
        ProjectContainer::factory()->count(3)->for($this->project)->create();
    }

    public function test_adds_a_material_to_several_containers_with_one_item_each(): void
    {
        Sanctum::actingAs(User::factory()->designer()->create());
        [$first, , $third] = $this->project->containers->pluck('id')->all();

        $this->postJson("/api/projects/{$this->project->id}/items", [
            'container_ids' => [$first, $third], 'material_id' => 237, 'planned_qty' => 45, 'section_id' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'in_productie')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.container_id', $first)
            ->assertJsonPath('data.items.1.container_id', $third)
            ->assertJsonPath('data.items.1.material', ['id' => 237, 'code' => 'sarma sudura 1.0 mm', 'name' => 'sarma sudura 1.0 mm', 'unit' => 'KGR'])
            ->assertJsonPath('data.items.1.planned_qty', 45)
            ->assertJsonPath('data.items.1.status', 'neinceput');
    }

    public function test_records_who_added_and_who_last_changed_an_item(): void
    {
        $designer = User::factory()->designer()->create(['name' => 'Ana Proiectant']);
        Sanctum::actingAs($designer);
        $containerId = $this->project->containers->first()->id;

        $this->postJson("/api/projects/{$this->project->id}/items", [
            'container_ids' => [$containerId], 'material_id' => 237, 'planned_qty' => 5, 'section_id' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.created_by', ['id' => $designer->id, 'name' => 'Ana Proiectant'])
            ->assertJsonPath('data.items.0.updated_by', null);

        $admin = User::factory()->admin()->create(['name' => 'Ion Admin']);
        Sanctum::actingAs($admin);
        $item = ProjectItem::query()->sole();

        $this->patchJson("/api/project-items/{$item->id}", ['status' => 'in_lucru'])
            ->assertOk()
            ->assertJsonPath('data.items.0.created_by.id', $designer->id)
            ->assertJsonPath('data.items.0.updated_by', ['id' => $admin->id, 'name' => 'Ion Admin']);
    }

    public function test_rejects_containers_of_other_projects_unknown_materials_and_closed_projects(): void
    {
        Sanctum::actingAs(User::factory()->designer()->create());
        $foreignContainer = ProjectContainer::factory()->create();
        $containerId = $this->project->containers->first()->id;
        $payload = ['container_ids' => [$containerId], 'material_id' => 237, 'planned_qty' => 1, 'section_id' => 1];

        $this->postJson("/api/projects/{$this->project->id}/items", [...$payload, 'container_ids' => [$foreignContainer->id]])
            ->assertJsonValidationErrors('container_ids.0');
        $this->postJson("/api/projects/{$this->project->id}/items", [...$payload, 'material_id' => 901])
            ->assertJsonValidationErrors('material_id');

        $this->project->update(['status' => Project::STATUS_CLOSED]);
        $this->postJson("/api/projects/{$this->project->id}/items", $payload)->assertJsonValidationErrors('project');
        $this->assertSame(0, ProjectItem::query()->count());
    }

    public function test_removes_an_item(): void
    {
        Sanctum::actingAs(User::factory()->designer()->create());
        $item = ProjectItem::factory()->for($this->project->containers->first(), 'container')->create(['section_id' => 1]);

        $this->deleteJson("/api/project-items/{$item->id}")->assertOk()->assertJsonPath('data.items', []);
        $this->assertModelMissing($item);
    }

    public function test_executant_works_only_on_items_of_their_sections(): void
    {
        $executant = User::factory()->executant()->create();
        $executant->sections()->sync([1]);
        Sanctum::actingAs($executant);
        $container = $this->project->containers->first();
        $own = ProjectItem::factory()->for($container, 'container')->create(['section_id' => 1]);
        $other = ProjectItem::factory()->for($container, 'container')->create(['section_id' => 2]);

        $this->patchJson("/api/project-items/{$own->id}", ['status' => 'in_lucru'])
            ->assertOk()
            ->assertJsonPath('data.items.0.status', 'in_lucru')
            ->assertJsonPath('data.items.0.worker_id', $executant->id);
        $this->patchJson("/api/project-items/{$own->id}", ['status' => 'finalizat', 'actual_qty' => 12.5])
            ->assertOk()
            ->assertJsonPath('data.items.0.actual_qty', 12.5);

        $this->patchJson("/api/project-items/{$other->id}", ['status' => 'in_lucru'])->assertForbidden();
        $this->deleteJson("/api/project-items/{$own->id}")->assertForbidden();
    }

    public function test_resetting_an_item_to_not_started_clears_the_worker_and_actual_quantity(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $item = ProjectItem::factory()->for($this->project->containers->first(), 'container')->create([
            'section_id' => 3, 'status' => 'finalizat', 'actual_qty' => 9, 'worker_id' => $admin->id,
        ]);

        $this->patchJson("/api/project-items/{$item->id}", ['status' => 'neinceput'])
            ->assertOk()
            ->assertJsonPath('data.items.0.worker_id', null)
            ->assertJsonPath('data.items.0.actual_qty', null);
    }

    public function test_adds_a_comment_with_the_author_name(): void
    {
        $executant = User::factory()->executant()->create(['name' => 'Vasile Ionescu']);
        Sanctum::actingAs($executant);
        $item = ProjectItem::factory()->for($this->project->containers->first(), 'container')->create(['section_id' => 4]);

        $this->postJson("/api/project-items/{$item->id}/comments", ['body' => '  Lipsă material pe stoc.  '])
            ->assertCreated()
            ->assertJsonPath('data.items.0.comments.0.author', 'Vasile Ionescu')
            ->assertJsonPath('data.items.0.comments.0.body', 'Lipsă material pe stoc.');

        $this->postJson("/api/project-items/{$item->id}/comments", ['body' => ''])->assertJsonValidationErrors('body');
    }
}
