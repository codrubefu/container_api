<?php

namespace Tests\Feature\Auth;

use App\Models\LoginChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\FakeSmsSender;
use Tests\TestCase;

class VerifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_code_returns_a_token_and_the_user(): void
    {
        $this->freezeSecond();
        $sms = $this->fakeSms();
        $user = User::factory()->executant()->create();
        $challengeId = $this->startLogin($user, extra: ['device_name' => 'inventar'])->json('challenge_id');

        $response = $this->postJson('/api/auth/verify', [
            'challenge_id' => $challengeId,
            'code' => $sms->lastCodeFor($user->phone),
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_at', now()->addHours(12)->toIso8601String())
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'executant');
        $token = PersonalAccessToken::findToken($response->json('token'));
        $this->assertSame('inventar', $token->name);
        $this->assertTrue($token->tokenable->is($user));
        $this->assertTrue($token->expires_at->equalTo(now()->addHours(12)));
        $this->assertNotNull(LoginChallenge::find($challengeId)->consumed_at);
        $this->assertTrue($user->fresh()->last_login_at->equalTo(now()));
    }

    public function test_issued_token_authenticates_api_requests(): void
    {
        $sms = $this->fakeSms();
        $user = User::factory()->create();

        $token = $this->completeLogin($user, $sms);

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_wrong_code_returns_422_and_counts_the_attempt(): void
    {
        $sms = $this->fakeSms();
        $user = User::factory()->create();
        $challengeId = $this->startLogin($user)->json('challenge_id');

        $this->postJson('/api/auth/verify', ['challenge_id' => $challengeId, 'code' => $this->wrongCode($sms, $user)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'Codul este incorect. Mai aveți 4 încercări.']);
        $this->assertSame(1, LoginChallenge::find($challengeId)->attempts);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_expired_challenge_is_rejected_even_with_the_correct_code(): void
    {
        $sms = $this->fakeSms();
        $user = User::factory()->create();
        $challengeId = $this->startLogin($user)->json('challenge_id');

        $this->travel(5)->minutes();
        $this->travel(1)->seconds();

        $this->postJson('/api/auth/verify', ['challenge_id' => $challengeId, 'code' => $sms->lastCodeFor($user->phone)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['challenge_id' => 'Codul a expirat sau nu mai este valid. Autentificați-vă din nou.']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_challenge_is_invalidated_after_five_wrong_codes(): void
    {
        $sms = $this->fakeSms();
        $user = User::factory()->create();
        $challengeId = $this->startLogin($user)->json('challenge_id');
        $wrongCode = $this->wrongCode($sms, $user);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/auth/verify', ['challenge_id' => $challengeId, 'code' => $wrongCode])->assertUnprocessable();
        }

        $this->postJson('/api/auth/verify', ['challenge_id' => $challengeId, 'code' => $wrongCode])
            ->assertJsonValidationErrors(['code' => 'Prea multe încercări greșite. Autentificați-vă din nou.']);

        $this->postJson('/api/auth/verify', ['challenge_id' => $challengeId, 'code' => $sms->lastCodeFor($user->phone)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge_id');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_consumed_challenge_cannot_be_reused(): void
    {
        $sms = $this->fakeSms();
        $user = User::factory()->create();
        $challengeId = $this->startLogin($user)->json('challenge_id');
        $code = $sms->lastCodeFor($user->phone);

        $this->postJson('/api/auth/verify', ['challenge_id' => $challengeId, 'code' => $code])->assertOk();

        $this->postJson('/api/auth/verify', ['challenge_id' => $challengeId, 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge_id');
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_user_deactivated_between_steps_does_not_receive_a_token(): void
    {
        $sms = $this->fakeSms();
        $user = User::factory()->create();
        $challengeId = $this->startLogin($user)->json('challenge_id');

        $user->update(['is_active' => false]);

        $this->postJson('/api/auth/verify', ['challenge_id' => $challengeId, 'code' => $sms->lastCodeFor($user->phone)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge_id');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unknown_challenge_returns_422(): void
    {
        $this->postJson('/api/auth/verify', ['challenge_id' => '9b0f1c1e-0000-4000-8000-000000000000', 'code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge_id');
    }

    public function test_malformed_input_returns_422(): void
    {
        $this->postJson('/api/auth/verify', ['challenge_id' => 'not-a-uuid', 'code' => '12ab'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['challenge_id', 'code']);
    }

    private function completeLogin(User $user, FakeSmsSender $sms): string
    {
        $challengeId = $this->startLogin($user)->json('challenge_id');

        return $this->postJson('/api/auth/verify', [
            'challenge_id' => $challengeId,
            'code' => $sms->lastCodeFor($user->phone),
        ])->json('token');
    }

    private function wrongCode(FakeSmsSender $sms, User $user): string
    {
        return $sms->lastCodeFor($user->phone) === '000000' ? '111111' : '000000';
    }
}
