<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ViolationTransaction>
 */
class ViolationTransactionFactory extends Factory
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
            'date_occurred' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d H:i:s'),
            'remarks' => $this->faker->optional(0.7)->sentence(),
        ];
    }
}
