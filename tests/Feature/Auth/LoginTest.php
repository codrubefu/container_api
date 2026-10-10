<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_a_challenge_and_send_an_sms_code_without_a_token(): void
    {
        $this->freezeSecond();
        $sms = $this->fakeSms();
        $user = User::factory()->executant()->create(['phone' => '+40 744 556 213']);

        $response = $this->startLogin($user);

        $response->assertOk()
            ->assertExactJsonStructure(['challenge_id', 'phone_masked', 'expires_at', 'resend_available_at'])
            ->assertJsonPath('phone_masked', '+40 7•• ••• 213')
            ->assertJsonPath('expires_at', now()->addMinutes(5)->toIso8601String());
        $this->assertMatchesRegularExpression('/^\d{6}$/', $sms->lastCodeFor('+40 744 556 213'));
        $this->assertDatabaseHas('login_challenges', [
            'id' => $response->json('challenge_id'),
            'user_id' => $user->id,
            'attempts' => 0,
            'device_name' => 'ui',
        ]);
    }

    public function test_the_code_is_stored_only_as_a_hash(): void
    {
        $sms = $this->fakeSms();
        $user = User::factory()->create();

        $challengeId = $this->startLogin($user)->json('challenge_id');

        $code = $sms->lastCodeFor($user->phone);
        $this->assertDatabaseMissing('login_challenges', ['id' => $challengeId, 'code_hash' => $code]);
    }

    public function test_device_name_is_stored_on_the_challenge(): void
    {
        $this->fakeSms();
        $user = User::factory()->create();

        $challengeId = $this->startLogin($user, extra: ['device_name' => 'inventar'])->json('challenge_id');

        $this->assertDatabaseHas('login_challenges', ['id' => $challengeId, 'device_name' => 'inventar']);
    }

    public function test_rejects_unknown_device_name_with_422(): void
    {
        $this->fakeSms();
        $user = User::factory()->create();

        $this->startLogin($user, extra: ['device_name' => 'altceva'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_name');
    }

    public function test_wrong_password_returns_422_with_a_generic_message(): void
    {
        $sms = $this->fakeSms();
        $user = User::factory()->create();

        $this->startLogin($user, 'wrong-password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Datele de autentificare sunt incorecte.']);
        $sms->assertNothingSent();
        $this->assertDatabaseCount('login_challenges', 0);
    }

    public function test_unknown_email_returns_the_same_generic_message(): void
    {
        $sms = $this->fakeSms();

        $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Datele de autentificare sunt incorecte.']);
        $sms->assertNothingSent();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $sms = $this->fakeSms();
        $user = User::factory()->inactive()->create();

        $this->startLogin($user)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Datele de autentificare sunt incorecte.']);
        $sms->assertNothingSent();
    }

    public function test_missing_fields_return_422(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_returns_429_after_five_attempts_for_the_same_email_and_ip(): void
    {
        $this->fakeSms();
        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->startLogin($user, 'wrong-password')->assertUnprocessable();
        }

        $this->startLogin($user)->assertTooManyRequests();
    }
}
