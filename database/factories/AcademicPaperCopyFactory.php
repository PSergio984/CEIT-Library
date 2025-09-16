<?php

namespace Database\Factories;

use App\Models\AcademicPaperCopy;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicPaperCopyFactory extends Factory
{
    protected $model = AcademicPaperCopy::class;

    public function definition()
    {
        return [
            'copy_number' => $this->faker->numberBetween(1, 10), // Removed unique()
            'status' => $this->faker->randomElement(['Available', 'Reserved', 'Unavailable']),
        ];
    }
}
