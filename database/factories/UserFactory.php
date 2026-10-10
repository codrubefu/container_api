<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+40 7'.fake()->numerify('## ### ###'),
            'email_verified_at' => now(),
            'is_active' => true,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has been deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function admin(): static
    {
        return $this->withRole(Role::ADMIN);
    }

    public function designer(): static
    {
        return $this->withRole(Role::DESIGNER);
    }

    public function executant(): static
    {
        return $this->withRole(Role::EXECUTANT);
    }

    /**
     * Attach an existing (seeded) role to the created user.
     */
    public function withRole(string $slug): static
    {
        return $this->afterCreating(function (User $user) use ($slug) {
            $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail());
        });
    }
}
