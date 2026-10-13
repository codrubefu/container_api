<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\ProjectContainer;
use App\Models\ProjectItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SectionWorkTest extends TestCase
{
    use RefreshDatabase;

    private User $executant;

    private ProjectContainer $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->executant = User::factory()->executant()->create(['name' => 'Vasile Ionescu']);
        $this->executant->sections()->sync([1, 2]);

        $project = Project::factory()->create(['name' => 'ROM Container', 'contract_number' => '116 - BUCURESTI', 'status' => Project::STATUS_IN_PRODUCTION]);
        $this->container = ProjectContainer::factory()->for($project)->create(['code' => 'MSCU6041657', 'type' => '20DV']);
    }

    private function item(int $sectionId, string $status = ProjectItem::STATUS_NOT_STARTED, ?ProjectContainer $container = null): ProjectItem
    {
        return ProjectItem::factory()->for($container ?? $this->container, 'container')->create(['section_id' => $sectionId, 'status' => $status]);
    }

    public function test_lists_the_operators_sections_with_unfinished_items_of_open_projects(): void
    {
        $this->item(1);
        $this->item(1, ProjectItem::STATUS_IN_PROGRESS);
        $this->item(1, ProjectItem::STATUS_DONE);
        $this->item(3);
        $closed = ProjectContainer::factory()->for(Project::factory()->create(['status' => Project::STATUS_CLOSED]))->create();
        $this->item(1, container: $closed);
        Sanctum::actingAs($this->executant);

        $this->getJson('/api/work/sections')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => 1, 'name' => 'Sudură', 'pending_count' => 2],
                ['id' => 2, 'name' => 'Vopsitorie', 'pending_count' => 0],
            ]]);
    }

    public function test_admins_see_every_section(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/work/sections')->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_lists_open_projects_with_work_in_the_section(): void
    {
        $this->item(1);
        $this->item(1, ProjectItem::STATUS_DONE);
        $this->item(2);
        $other = ProjectContainer::factory()->for(Project::factory()->create(['status' => Project::STATUS_IN_PRODUCTION]))->create();
        $this->item(2, container: $other);
        $closed = ProjectContainer::factory()->for(Project::factory()->create(['status' => Project::STATUS_CLOSED]))->create();
        $this->item(1, container: $closed);
        Sanctum::actingAs($this->executant);

        $this->getJson('/api/work/sections/1/projects')
            ->assertOk()
            ->assertExactJson(['data' => [[
                'id' => $this->container->project_id,
                'name' => 'ROM Container',
                'code' => '116 - BUCURESTI',
                'client' => $this->container->project->client_name,
                'deadline' => null,
                'items_count' => 2,
                'pending_count' => 1,
            ]]]);
    }

    public function test_lists_the_sections_items_of_a_project_with_container_and_comments(): void
    {
        $item = $this->item(1);
        $item->addComment($this->executant, 'Lipsă stoc.');
        $this->item(2);
        Sanctum::actingAs($this->executant);

        $this->getJson("/api/work/sections/1/projects/{$this->container->project_id}/items")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('data.0.container', ['id' => $this->container->id, 'code' => 'MSCU6041657', 'type' => '20DV'])
            ->assertJsonPath('data.0.comments.0.body', 'Lipsă stoc.')
            ->assertJsonPath('project.name', 'ROM Container')
            ->assertJsonPath('project.code', '116 - BUCURESTI');
    }

    public function test_operators_cannot_read_sections_they_are_not_assigned_to(): void
    {
        Sanctum::actingAs($this->executant);

        $this->getJson('/api/work/sections/3/projects')->assertForbidden();
        $this->getJson("/api/work/sections/3/projects/{$this->container->project_id}/items")->assertForbidden();
    }

    public function test_designers_have_no_access_to_section_work(): void
    {
        Sanctum::actingAs(User::factory()->designer()->create());

        $this->getJson('/api/work/sections')->assertForbidden();
    }

    public function test_updates_an_item_and_returns_only_that_item(): void
    {
        $item = $this->item(2);
        Sanctum::actingAs($this->executant);

        $this->patchJson("/api/work/items/{$item->id}", ['status' => 'in_lucru'])
            ->assertOk()
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.status', 'in_lucru')
            ->assertJsonPath('data.worker_id', $this->executant->id)
            ->assertJsonPath('data.container.code', 'MSCU6041657')
            ->assertJsonMissingPath('data.items');

        $this->patchJson("/api/work/items/{$item->id}", ['status' => 'finalizat', 'actual_qty' => 11])
            ->assertOk()
            ->assertJsonPath('data.actual_qty', 11);
    }

    public function test_operators_update_and_comment_only_on_their_sections(): void
    {
        $foreign = $this->item(4);
        Sanctum::actingAs($this->executant);

        $this->patchJson("/api/work/items/{$foreign->id}", ['status' => 'in_lucru'])->assertForbidden();
        $this->postJson("/api/work/items/{$foreign->id}/comments", ['body' => 'x'])->assertForbidden();
    }

    public function test_comments_on_an_item_of_their_section(): void
    {
        $item = $this->item(1);
        Sanctum::actingAs($this->executant);

        $this->postJson("/api/work/items/{$item->id}/comments", ['body' => ' Gata sudura. '])
            ->assertCreated()
            ->assertJsonPath('data.comments.0.author', 'Vasile Ionescu')
            ->assertJsonPath('data.comments.0.body', 'Gata sudura.');
    }
}
