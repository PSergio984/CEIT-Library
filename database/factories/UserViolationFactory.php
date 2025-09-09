<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\UserViolation;
use App\Models\User;
use App\Models\Violation;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserViolation>
 */
class UserViolationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'violation_id' => Violation::factory(),
            'date_occurred' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'Severity' => $this->faker->randomElement(['Minor', 'Major', 'Critical']),
            'remarks' => $this->faker->optional(0.7)->sentence(),
        ];
    }
}
