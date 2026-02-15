<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'avatar' => null,
            'is_active' => true,
            'system_role' => 'workspace_user',
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

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the user should be an active and verified account.
     */
    public function verifiedActive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Semantic state for trainer accounts.
     */
    public function trainer(): static
    {
        return $this->verifiedActive();
    }

    /**
     * Semantic state for owner/admin accounts.
     */
    public function ownerAdmin(): static
    {
        return $this->verifiedActive();
    }

    /**
     * Semantic state for platform admins.
     */
    public function platformAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'email_verified_at' => now(),
            'system_role' => 'platform_admin',
        ]);
    }
}
