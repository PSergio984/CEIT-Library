<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\CreditScore;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditScore>
 */
class CreditScoreFactory extends Factory
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
            'score' => $this->faker->numberBetween(10, 75), // Realistic score range considering violations
        ];
    }

    /**
     * Create a user with excellent score
     */
    public function excellent()
    {
        return $this->state(fn (array $attributes) => [
            'score' => $this->faker->numberBetween(70, 75),
        ]);
    }

    /**
     * Create a user with good score
     */
    public function good()
    {
        return $this->state(fn (array $attributes) => [
            'score' => $this->faker->numberBetween(50, 69),
        ]);
    }

    /**
     * Create a user with poor score
     */
    public function poor()
    {
        return $this->state(fn (array $attributes) => [
            'score' => $this->faker->numberBetween(10, 29),
        ]);
    }
}
