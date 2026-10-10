<?php

namespace Tests\Feature\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_roles_with_their_permissions(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/roles')
            ->assertOk()
            ->assertJsonPath('data.*.slug', ['admin', 'designer', 'executant'])
            ->assertJsonPath('data.2.permissions', ['comments.create', 'project_items.work', 'projects.view', 'sections.view'])
            ->assertJsonCount(10, 'data.0.permissions')
            ->assertJsonCount(10, 'meta.permissions');
    }

    public function test_replaces_role_permissions_and_affects_its_users(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $executant = User::factory()->executant()->create();

        $this->putJson('/api/roles/executant/permissions', ['permissions' => ['projects.view', 'sections.view']])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['projects.view', 'sections.view']);

        $this->assertFalse($executant->fresh()->hasPermission('project_items.work'));
    }

    public function test_rejects_unknown_permissions(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/roles/executant/permissions', ['permissions' => ['projects.delete']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('permissions.0');
    }

    public function test_admin_role_permissions_cannot_be_changed(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/roles/admin/permissions', ['permissions' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permissions' => 'Permisiunile rolului de administrator nu pot fi modificate.']);
        $this->assertCount(10, Role::query()->where('slug', 'admin')->first()->permissions);
    }
}
