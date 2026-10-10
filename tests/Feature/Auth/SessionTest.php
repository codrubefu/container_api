<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_401_without_a_token(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('ui')->plainTextToken;
        $otherToken = $user->createToken('inventar')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();
        $this->forgetAuthentication();

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
        $this->forgetAuthentication();
        $this->withToken($otherToken)->getJson('/api/auth/me')->assertOk();
    }

    public function test_expired_token_returns_401(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('ui', ['*'], now()->addHours(12))->plainTextToken;

        $this->travel(12)->hours();
        $this->travel(1)->seconds();

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_deactivated_user_token_returns_401_and_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('ui')->plainTextToken;
        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('ui')->plainTextToken;

        $this->withToken($token)->patchJson('/api/auth/me/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    }

    public function test_password_change_updates_the_password_and_revokes_other_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('ui')->plainTextToken;
        $otherToken = $user->createToken('inventar')->plainTextToken;

        $this->withToken($token)->patchJson('/api/auth/me/password', [
            'current_password' => 'password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('new-secret-123', $user->fresh()->password));
        $this->forgetAuthentication();
        $this->withToken($otherToken)->getJson('/api/auth/me')->assertUnauthorized();
        $this->forgetAuthentication();
        $this->withToken($token)->getJson('/api/auth/me')->assertOk();
    }

    public function test_password_change_requires_confirmation(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('ui')->plainTextToken)->patchJson('/api/auth/me/password', [
            'current_password' => 'password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'something-else',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
