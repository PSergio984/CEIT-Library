<?php

namespace Database\Factories;

use App\Models\ResearchAdviser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResearchAdviser>
 */
class ResearchAdviserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
        ];
    }
}
