<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ScoreIncrement;
use App\Models\User;

/**
 * @extends Factory<\App\Models\ScoreIncrement>
 */
class ScoreIncrementFactory extends Factory
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
            'name' => $this->faker->word(),
            'description' => $this->faker->optional()->sentence(),
            'score_value' => $this->faker->numberBetween(50, 100), // Realistic score range considering violations
        ];
    }

    /**
     * Create a user with excellent score
     */
    public function excellent()
    {
        return $this->state(fn (array $attributes) => [
            'score_value' => $this->faker->numberBetween(70, 75),
        ]);
    }

    /**
     * Create a user with good score
     */
    public function good()
    {
        return $this->state(fn (array $attributes) => [
            'score_value' => $this->faker->numberBetween(50, 69),
        ]);
    }

    /**
     * Create a user with poor score
     */
    public function poor()
    {
        return $this->state(fn (array $attributes) => [
            'score_value' => $this->faker->numberBetween(10, 29),
        ]);
    }
}
