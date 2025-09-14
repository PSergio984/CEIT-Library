<?php

namespace Database\Factories;

use App\Models\Thesis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ThesisCopy>
 */
class ThesisCopyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thesis_id' => Thesis::factory(),
            'copy_number' => $this->faker->numberBetween(1, 5),
            'status' => $this->faker->randomElement(['Available', 'Reserved', 'Unavailable']),
        ];
    }

    /**
     * State for available copies
     */
    public function available()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'Available',
            ];
        });
    }

    /**
     * State for reserved copies
     */
    public function reserved()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'Reserved',
            ];
        });
    }

    /**
     * State for unavailable copies
     */
    public function unavailable()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'Unavailable',
            ];
        });
    }
}
