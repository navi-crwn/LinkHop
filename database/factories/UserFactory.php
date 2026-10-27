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
            'email_verified_at' => now(),
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

    /** The HOP owner (what SUPERADMIN_EMAIL used to be). */
    public function owner(): static
    {
        return $this->afterCreating(fn (User $u) => $u->roles()->create(['role' => Role::OWNER]));
    }

    /** A HOP admin (every app), or admin of one app when $app is given. */
    public function admin(?string $app = null): static
    {
        return $this->afterCreating(fn (User $u) => $u->roles()->create(['role' => Role::ADMIN, 'app_slug' => $app]));
    }
}
