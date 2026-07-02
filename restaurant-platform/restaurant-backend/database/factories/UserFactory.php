<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
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
            'role' => UserRole::Customer,
            'is_active' => true,
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
     * A generic admin account — defaults to the most privileged role.
     * Prefer the specific role states below when a test cares which one.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::SuperAdmin,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::SuperAdmin]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Manager]);
    }

    public function kitchen(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Kitchen]);
    }

    public function cashier(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Cashier]);
    }

    public function support(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Support]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
