<?php

namespace Tests\Feature\Users;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The user format is a contract with the UI.
 */
class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_the_ui_contract_without_the_full_phone(): void
    {
        $user = User::factory()->executant()->create([
            'name' => 'Vasile Ionescu',
            'email' => 'vasile.ionescu@befu-containere.ro',
            'phone' => '+40 744 556 213',
        ]);
        $user->sections()->attach(1);
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $user->id,
                'name' => 'Vasile Ionescu',
                'email' => 'vasile.ionescu@befu-containere.ro',
                'phone_masked' => '+40 7•• ••• 213',
                'role' => 'executant',
                'sectionIds' => [1],
                'permissions' => ['comments.create', 'project_items.work', 'projects.view', 'sections.view'],
                'is_active' => true,
            ]]);
    }

    public function test_includes_the_full_phone_for_users_with_users_manage(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $user = User::factory()->executant()->create(['phone' => '+40 744 556 213']);

        $this->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.phone', '+40 744 556 213')
            ->assertJsonPath('data.phone_masked', '+40 7•• ••• 213');
    }

    public function test_hides_the_full_phone_in_lists_for_users_without_users_manage(): void
    {
        $viewer = User::factory()->executant()->create();
        $viewer->roles()->first()->permissions()->attach(
            Permission::query()->where('slug', 'users.view')->value('id'),
        );
        Sanctum::actingAs($viewer);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonMissingPath('data.0.phone')
            ->assertJsonPath('data.0.phone_masked', $viewer->maskedPhone());
    }
}
