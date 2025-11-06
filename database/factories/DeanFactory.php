<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Dean>
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
        $departments = [
            'Civil Engineering',
            'Information Technology',
            'Electrical Engineering'
        ];

        return [
            'name' => 'Dr. ' . $this->faker->name(),
            'department' => $this->faker->randomElement($departments),
        ];
    }
}
