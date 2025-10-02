<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RuleHeader>
 */
class RuleHeaderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->unique()->sentence(3), // e.g., "I. General Information"
            'order' => $this->faker->unique()->numberBetween(1, 100),
        ];
    }
}
