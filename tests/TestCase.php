<?php

namespace Tests;

use App\Contracts\SmsSender;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeSmsSender;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests using RefreshDatabase get roles, permissions and sections (no demo users outside local).
     * The in-memory database is migrated and seeded once per run, so all tests share this seeder.
     */
    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    /**
     * Replace the SMS driver with an in-memory fake that captures the codes.
     */
    protected function fakeSms(): FakeSmsSender
    {
        $sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $sms);

        return $sms;
    }

    /**
     * Run the first login step for the user (factory password: "password").
     *
     * @param  array<string, mixed>  $extra
     */
    protected function startLogin(User $user, string $password = 'password', array $extra = []): TestResponse
    {
        return $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => $password, ...$extra]);
    }

    /**
     * Forget the user resolved by the auth guards, so the next request authenticates from scratch
     * (as a real, separate HTTP request would).
     */
    protected function forgetAuthentication(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
