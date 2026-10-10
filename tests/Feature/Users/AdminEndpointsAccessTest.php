<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every administration endpoint: 401 without a token, 403 without the permission, 2xx with it.
 */
class AdminEndpointsAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, array<string, mixed>, string, string, int}>
     */
    public static function endpoints(): array
    {
        return [
            'list users' => ['GET', '/api/users', [], 'designer', 'admin', 200],
            'create user' => ['POST', '/api/users', [
                'name' => 'Ana Pop', 'email' => 'ana.pop@example.com', 'phone' => '+40 711 222 333',
                'password' => 'secret-pass-1', 'role' => 'executant', 'section_ids' => [1],
            ], 'designer', 'admin', 201],
            'show user' => ['GET', '/api/users/{user}', [], 'designer', 'admin', 200],
            'update user' => ['PATCH', '/api/users/{user}', ['name' => 'Nume Nou'], 'designer', 'admin', 200],
            'deactivate user' => ['DELETE', '/api/users/{user}', [], 'designer', 'admin', 200],
            'sync user sections' => ['PUT', '/api/users/{user}/sections', ['section_ids' => [2]], 'designer', 'admin', 200],
            'change user role' => ['PUT', '/api/users/{user}/role', ['role' => 'designer'], 'designer', 'admin', 200],
            'list roles' => ['GET', '/api/roles', [], 'designer', 'admin', 200],
            'sync role permissions' => ['PUT', '/api/roles/designer/permissions', ['permissions' => ['projects.view']], 'executant', 'admin', 200],
            'list sections (executant)' => ['GET', '/api/sections', [], 'none', 'executant', 200],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('endpoints')]
    public function test_returns_401_without_a_token(string $method, string $uri, array $payload, string $deniedRole, string $allowedRole, int $status): void
    {
        $target = User::factory()->executant()->create();

        $this->json($method, str_replace('{user}', (string) $target->id, $uri), $payload)
            ->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('endpoints')]
    public function test_returns_403_without_the_permission(string $method, string $uri, array $payload, string $deniedRole, string $allowedRole, int $status): void
    {
        $target = User::factory()->executant()->create();
        Sanctum::actingAs($deniedRole === 'none' ? User::factory()->create() : User::factory()->withRole($deniedRole)->create());

        $this->json($method, str_replace('{user}', (string) $target->id, $uri), $payload)
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('endpoints')]
    public function test_succeeds_with_the_permission(string $method, string $uri, array $payload, string $deniedRole, string $allowedRole, int $status): void
    {
        $target = User::factory()->executant()->create();
        Sanctum::actingAs(User::factory()->withRole($allowedRole)->create());

        $this->json($method, str_replace('{user}', (string) $target->id, $uri), $payload)
            ->assertStatus($status);
    }
}
