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

class ProjectControllerTest extends TestCase
{
    use FakeErpDatabase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeErpDatabase();

        $erp = DB::connection('erp');
        $erp->table('client')->insert(['idcl' => 34160, 'den' => 'ROM CONTAINER RENT & SALE SRL   ']);
        $erp->table('trzctr')->insert([
            ['idtrzctr' => 15194, 'nrcontr' => '116 - BUCURESTI   ', 'tipctr' => 'INCHIRIERE', 'idcl' => 34160],
            ['idtrzctr' => 15300, 'nrcontr' => '130 - FARA CONTAINERE', 'tipctr' => 'INCHIRIERE', 'idcl' => 34160],
        ]);
        $erp->table('trzdetctr')->insert([
            ['idtrzdetctr' => 47278, 'idtrzctr' => 15194, 'clasa' => 'CONTAINERS', 'grupa' => '20DV  ', 'art' => 'Inchiriere MSCU6041657'],
            ['idtrzdetctr' => 47281, 'idtrzctr' => 15194, 'clasa' => 'OFFICE UNITS', 'grupa' => 'OFFICE 20', 'art' => 'Inchiriere CMAU0073545'],
            ['idtrzdetctr' => 47290, 'idtrzctr' => 15194, 'clasa' => 'COMPONENTS', 'grupa' => 'AUXILIARE', 'art' => 'Inchiriere Scara Acces'],
            ['idtrzdetctr' => 48100, 'idtrzctr' => 15300, 'clasa' => 'SERVICES', 'grupa' => 'TRANSPORT', 'art' => 'Transport'],
        ]);
    }

    public function test_creates_a_draft_project_with_a_snapshot_of_the_contract_and_its_containers(): void
    {
        $designer = User::factory()->designer()->create();
        Sanctum::actingAs($designer);

        $response = $this->postJson('/api/projects', ['contract_id' => 15194, 'name' => 'ROM Container — Lot A', 'deadline' => '2026-11-30'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'ROM Container — Lot A')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.deadline', '2026-11-30')
            ->assertJsonPath('data.contract', ['id' => 15194, 'number' => '116 - BUCURESTI', 'client' => 'ROM CONTAINER RENT & SALE SRL'])
            ->assertJsonPath('data.items', []);

        $this->assertSame(
            [['code' => 'MSCU6041657', 'type' => '20DV'], ['code' => 'CMAU0073545', 'type' => 'OFFICE 20']],
            collect($response->json('data.containers'))->map(fn (array $c) => ['code' => $c['code'], 'type' => $c['type']])->all()
        );
        $this->assertSame($designer->id, Project::query()->sole()->created_by);
    }

    public function test_rejects_a_second_project_for_the_same_contract_and_contracts_without_containers(): void
    {
        Sanctum::actingAs(User::factory()->designer()->create());
        Project::factory()->create(['contract_id' => 15194]);

        $this->postJson('/api/projects', ['contract_id' => 15194, 'name' => 'Duplicat'])->assertJsonValidationErrors('contract_id');
        $this->postJson('/api/projects', ['contract_id' => 15300, 'name' => 'Fara containere'])->assertJsonValidationErrors('contract_id');
        $this->postJson('/api/projects', ['contract_id' => 99999, 'name' => 'Inexistent'])->assertJsonValidationErrors('contract_id');
    }

    public function test_lists_projects_with_containers_items_and_comments(): void
    {
        $item = ProjectItem::factory()->create(['section_id' => 2, 'planned_qty' => 45.5]);
        $item->comments()->create(['author_name' => 'Mihai Radu', 'body' => 'Strat suplimentar.']);
        Sanctum::actingAs(User::factory()->executant()->create());

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.containers.0.id', $item->project_container_id)
            ->assertJsonPath('data.0.items.0.container_id', $item->project_container_id)
            ->assertJsonPath('data.0.items.0.section_id', 2)
            ->assertJsonPath('data.0.items.0.planned_qty', 45.5)
            ->assertJsonPath('data.0.items.0.actual_qty', null)
            ->assertJsonPath('data.0.items.0.material.name', 'sarma sudura 1.0 mm')
            ->assertJsonPath('data.0.items.0.comments.0.author', 'Mihai Radu')
            ->assertJsonPath('data.0.items.0.comments.0.body', 'Strat suplimentar.');
    }

    public function test_closes_and_reopens_a_project(): void
    {
        Sanctum::actingAs(User::factory()->designer()->create());
        $container = ProjectContainer::factory()->create();
        $project = $container->project;

        $this->postJson("/api/projects/{$project->id}/close")->assertOk()->assertJsonPath('data.status', 'finalizat');
        $this->postJson("/api/projects/{$project->id}/reopen")->assertOk()->assertJsonPath('data.status', 'draft');

        ProjectItem::factory()->for($container, 'container')->create(['section_id' => 1]);
        $this->postJson("/api/projects/{$project->id}/close")->assertOk();
        $this->postJson("/api/projects/{$project->id}/reopen")->assertOk()->assertJsonPath('data.status', 'in_productie');
    }

    public function test_requires_the_project_permissions(): void
    {
        $project = Project::factory()->create();

        $this->getJson('/api/projects')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->executant()->create());
        $this->getJson('/api/projects')->assertOk();
        $this->postJson('/api/projects', ['contract_id' => 15194, 'name' => 'X'])->assertForbidden();
        $this->postJson("/api/projects/{$project->id}/close")->assertForbidden();
    }
}
