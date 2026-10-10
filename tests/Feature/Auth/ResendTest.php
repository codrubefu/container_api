<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResendTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_429_with_retry_after_during_the_cooldown(): void
    {
        $this->freezeSecond();
        $sms = $this->fakeSms();
        $user = User::factory()->create();
        $challengeId = $this->startLogin($user)->json('challenge_id');

        $this->travel(20)->seconds();

        $this->postJson('/api/auth/resend', ['challenge_id' => $challengeId])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '40');
        $sms->assertSentCount(1);
    }

    public function test_sends_a_new_code_after_the_cooldown_and_invalidates_the_old_one(): void
    {
        $this->freezeSecond();
        $sms = $this->fakeSms();
        $user = User::factory()->create();
        $challengeId = $this->startLogin($user)->json('challenge_id');
        $oldCode = $sms->lastCodeFor($user->phone);

        $this->travel(61)->seconds();

        $this->postJson('/api/auth/resend', ['challenge_id' => $challengeId])
            ->assertOk()
            ->assertJsonPath('challenge_id', $challengeId)
            ->assertJsonPath('expires_at', now()->addMinutes(5)->toIso8601String());
        $sms->assertSentCount(2);
        $newCode = $sms->lastCodeFor($user->phone);

        if ($newCode !== $oldCode) {
            $this->postJson('/api/auth/verify', ['challenge_id' => $challengeId, 'code' => $oldCode])->assertUnprocessable();
        }

        $this->postJson('/api/auth/verify', ['challenge_id' => $challengeId, 'code' => $newCode])->assertOk();
    }

    public function test_consumed_challenge_cannot_be_resent(): void
    {
        $sms = $this->fakeSms();
        $user = User::factory()->create();
        $challengeId = $this->startLogin($user)->json('challenge_id');
        $this->postJson('/api/auth/verify', ['challenge_id' => $challengeId, 'code' => $sms->lastCodeFor($user->phone)])->assertOk();

        $this->travel(61)->seconds();

        $this->postJson('/api/auth/resend', ['challenge_id' => $challengeId])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge_id');
        $sms->assertSentCount(1);
    }

    public function test_unknown_challenge_returns_422(): void
    {
        $this->postJson('/api/auth/resend', ['challenge_id' => '9b0f1c1e-0000-4000-8000-000000000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenge_id');
    }
}
