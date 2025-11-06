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
        $departments = [
            'Civil Engineering',
            'Information Technology',
            'Electrical Engineering'
        ];

        $department = $this->faker->randomElement($departments);

        // Get or create a random adviser
        $adviser = \App\Models\Adviser::firstOrCreate(
            ['name' => $this->faker->name()],
        );

        // Get or create a random dean
        $dean = \App\Models\Dean::inRandomOrder()->first();
        if (!$dean) {
            $dean = \App\Models\Dean::create([
                'name' => $this->faker->randomElement([
                    'Dr. Maria Santos',
                    'Dr. Juan Dela Cruz',
                    'Dr. Ana Reyes'
                ]),
                'department' => $department,
            ]);
        }

        return [
            'title' => $this->faker->sentence(6, true),
            'publication_year' => $this->faker->numberBetween(2002, 2025),
            'paper_type' => $this->faker->randomElement(['Thesis', 'Feasib', 'Capstone', 'Research', 'Practicum', 'Report']),
            'adviser_id' => $adviser->id,
            'department' => $department,
            'dean_id' => $dean->id,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function ($academicPaper) {
            // Attach 1-4 random authors if any exist
            $authorIds = \App\Models\Author::inRandomOrder()->limit(rand(1, 4))->pluck('id');
            if ($authorIds->count()) {
                $academicPaper->authors()->attach($authorIds);
            }
        });
    }
}
