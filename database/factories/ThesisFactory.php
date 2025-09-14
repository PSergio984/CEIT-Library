<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Thesis>
 */
class ThesisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catalog_code' => 'CEIT-' . $this->faker->unique()->numberBetween(1000, 9999),
            'title' => $this->faker->sentence(6, true),
            'year' => $this->faker->numberBetween(2018, 2025),
            'research_project_adviser' => $this->faker->name(),
            'department' => $this->faker->randomElement([
                'Computer Engineering',
                'Information Technology',
                'Electronics Engineering',
                'Electrical Engineering'
            ]),
            'member1' => $this->faker->name(),
            'member2' => $this->faker->name(),
            'member3' => $this->faker->name(),
            'member4' => $this->faker->name(),
            'dean' => $this->faker->randomElement([
                'Dr. Maria Santos',
                'Dr. Juan Dela Cruz',
                'Dr. Ana Reyes'
            ]),
        ];
    }
}
