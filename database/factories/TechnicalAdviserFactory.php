<?php

namespace Database\Factories;

use App\Models\TechnicalAdviser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechnicalAdviser>
 */
class TechnicalAdviserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Engr. '.$this->faker->name(),
        ];
    }
}
