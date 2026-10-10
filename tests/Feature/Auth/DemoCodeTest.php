<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DemoCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_code_is_accepted_in_the_local_environment(): void
    {
        config(['auth.two_factor.demo_code' => '123456']);
        $this->app->detectEnvironment(fn () => 'local');
        $this->fakeSms();
        $user = User::factory()->create();
        $challengeId = $this->startLogin($user)->json('challenge_id');

        $this->postJson('/api/auth/verify', ['challenge_id' => $challengeId, 'code' => '123456'])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    #[TestWith(['production'])]
    #[TestWith(['testing'])]
    #[TestWith(['staging'])]
    public function test_demo_code_is_rejected_outside_the_local_environment(string $environment): void
    {
        config(['auth.two_factor.demo_code' => '123456']);
        $this->app->detectEnvironment(fn () => $environment);
        $sms = $this->fakeSms();
        $user = User::factory()->create();
        $challengeId = $this->startLogin($user)->json('challenge_id');

        if ($sms->lastCodeFor($user->phone) === '123456') {
            $this->markTestSkipped('The random code happened to equal the demo code.');
        }

        $this->postJson('/api/auth/verify', ['challenge_id' => $challengeId, 'code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
