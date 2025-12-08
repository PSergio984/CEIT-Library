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
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            // Email must end with @plv.edu.ph for validation compatibility
            'email' => fake()->unique()->userName.'@plv.edu.ph',
            'email_verified_at' => now(),
            'password' => Hash::make(fake()->password(8, 12)), // Generate random password between 8-12 characters
            'remember_token' => Str::random(10),
            'role_id' => 1, // Default to student role
            'credit_score' => 100, // Default credit score
            'account_status' => 'active', // Default account status
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
}
