<?php

namespace Database\Factories;

use App\Models\Dean;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dean>
 */
class DeanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Dr. '.$this->faker->name(),
        ];
    }
}
