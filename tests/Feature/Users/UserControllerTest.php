<?php

namespace Tests\Feature\Users;

use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_is_paginated(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        User::factory()->count(3)->executant()->create();

        $this->getJson('/api/users?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_list_filters_by_role_section_search_and_status(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin']);
        Sanctum::actingAs($admin);
        $welder = User::factory()->executant()->create(['name' => 'Vasile Sudor']);
        $welder->sections()->attach(Section::query()->where('name', 'Sudură')->first());
        $painter = User::factory()->executant()->create(['name' => 'Pictor']);
        $painter->sections()->attach(Section::query()->where('name', 'Vopsitorie')->first());
        $designer = User::factory()->designer()->inactive()->create(['name' => 'Proiectant']);

        $this->getJson('/api/users?role=executant')->assertJsonPath('data.*.id', [$painter->id, $welder->id]);
        $this->getJson('/api/users?section_id=1')->assertJsonPath('data.*.id', [$welder->id]);
        $this->getJson('/api/users?search=sudor')->assertJsonPath('data.*.id', [$welder->id]);
        $this->getJson('/api/users?is_active=0')->assertJsonPath('data.*.id', [$designer->id]);
    }

    public function test_list_rejects_an_unknown_role_filter(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/users?role=nope')->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_store_creates_the_user_with_role_and_sections(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->postJson('/api/users', [
            'name' => 'Ana Pop',
            'email' => 'ana.pop@example.com',
            'phone' => '+40 711 222 333',
            'password' => 'secret-pass-1',
            'role' => 'executant',
            'section_ids' => [1, 3],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'executant')
            ->assertJsonPath('data.sectionIds', [1, 3])
            ->assertJsonPath('data.is_active', true);
        $user = User::query()->where('email', 'ana.pop@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));
        $this->assertSame('+40 711 222 333', $user->phone);
    }

    public function test_store_validates_the_payload(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $existing = User::factory()->create();

        $this->postJson('/api/users', ['email' => $existing->email, 'role' => 'boss', 'section_ids' => [99]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'password', 'role', 'section_ids.0']);
    }

    public function test_update_changes_the_details_and_revokes_tokens_on_password_change(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $user = User::factory()->executant()->create();
        $user->createToken('ui');

        $this->patchJson("/api/users/{$user->id}", ['name' => 'Nume Nou', 'password' => 'new-secret-123'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nume Nou');

        $this->assertTrue(Hash::check('new-secret-123', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_destroy_deactivates_the_user_and_revokes_tokens(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $user = User::factory()->executant()->create();
        $user->createToken('ui');

        $this->deleteJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$admin->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Nu vă puteți dezactiva propriul cont.');
        $this->patchJson("/api/users/{$admin->id}", ['is_active' => false])->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_sync_sections_replaces_the_assigned_sections(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $user = User::factory()->executant()->create();
        $user->sections()->attach(1);

        $this->putJson("/api/users/{$user->id}/sections", ['section_ids' => [2, 4]])
            ->assertOk()
            ->assertJsonPath('data.sectionIds', [2, 4]);

        $this->putJson("/api/users/{$user->id}/sections", ['section_ids' => []])
            ->assertOk()
            ->assertJsonPath('data.sectionIds', []);
    }

    public function test_change_role_replaces_the_role_and_its_permissions(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $user = User::factory()->executant()->create();

        $this->putJson("/api/users/{$user->id}/role", ['role' => 'designer'])
            ->assertOk()
            ->assertJsonPath('data.role', 'designer')
            ->assertJsonPath('data.permissions', [
                'comments.create', 'project_items.manage', 'projects.close',
                'projects.create', 'projects.view', 'sections.view',
            ]);
        $this->assertSame(['designer'], $user->roles()->pluck('slug')->all());
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson("/api/users/{$admin->id}/role", ['role' => 'executant'])->assertForbidden();
    }
}
