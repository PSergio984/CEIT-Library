<?php

namespace Database\Factories;

use App\Models\AcademicPaper;
use App\Models\Author;
use App\Models\Dean;
use App\Models\ResearchAdviser;
use App\Models\TechnicalAdviser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicPaper>
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
        $departments = [
            'Civil Engineering',
            'Information Technology',
            'Electrical Engineering',
        ];

        $department = $this->faker->randomElement($departments);

        // Get random dean (expects to be seeded first)
        $dean = Dean::inRandomOrder()->first();

        return [
            'title' => $this->faker->unique()->sentence(6, true),
            'publication_year' => $this->faker->numberBetween(2002, 2025),
            'paper_type' => $this->faker->randomElement(['Thesis', 'Feasib', 'Capstone', 'Research', 'Practicum', 'Report']),
            'research_adviser_id' => ResearchAdviser::inRandomOrder()->first()?->id,
            'technical_adviser_id' => TechnicalAdviser::inRandomOrder()->first()?->id,
            'department' => $department,
            'dean_id' => $dean?->id,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function ($academicPaper) {
            // Attach 1-4 random authors if any exist
            $authorIds = Author::inRandomOrder()->limit(rand(1, 4))->pluck('id');
            if ($authorIds->count()) {
                $academicPaper->authors()->attach($authorIds);
            }
        });
    }
}
