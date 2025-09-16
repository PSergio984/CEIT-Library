<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AcademicPaper>
 */
class AcademicPaperFactory extends Factory
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
            'publication_year' => $this->faker->numberBetween(2018, 2025),
            'paper_type' => $this->faker->randomElement(['academic paper', 'Dissertation', 'Capstone', 'Research Paper']),
            'research_project_adviser' => $this->faker->name(),
            'department' => $this->faker->randomElement([
                'Computer Engineering',
                'Information Technology',
                'Electronics Engineering',
                'Electrical Engineering'
            ]),
            'dean' => $this->faker->randomElement([
                'Dr. Maria Santos',
                'Dr. Juan Dela Cruz',
                'Dr. Ana Reyes'
            ]),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function ($academicPaper) {
            // Attach 1-4 random authors if any exist
            $authorIds = \App\Models\Author::inRandomOrder()->limit(rand(1,4))->pluck('id');
            if ($authorIds->count()) {
                $academicPaper->authors()->attach($authorIds);
            }
        });
    }
}
