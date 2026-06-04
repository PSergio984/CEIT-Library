<?php

namespace Database\Factories;

use App\Models\Violation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Violation>
 */
class ViolationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $violations = [
            [
                'name' => 'Late Return of Books',
                'description' => 'Returning library books beyond the due date',
                'penalty' => 5,
            ],
            [
                'name' => 'Loud Talking in Library',
                'description' => 'Making excessive noise that disturbs other library users',
                'penalty' => 3,
            ],
            [
                'name' => 'Eating in Library',
                'description' => 'Consuming food inside the library premises',
                'penalty' => 4,
            ],
            [
                'name' => 'Using Mobile Phone Loudly',
                'description' => 'Taking calls or playing media without headphones',
                'penalty' => 3,
            ],
            [
                'name' => 'Damaging Library Property',
                'description' => 'Causing damage to books, furniture, or equipment',
                'penalty' => 15,
            ],
            [
                'name' => 'Smoking in Library',
                'description' => 'Smoking or vaping inside the library building',
                'penalty' => 20,
            ],
            [
                'name' => 'Bringing Prohibited Items',
                'description' => 'Bringing weapons, alcohol, or other prohibited items',
                'penalty' => 25,
            ],
            [
                'name' => 'Theft of Library Materials',
                'description' => 'Stealing books or library property',
                'penalty' => 30,
            ],
            [
                'name' => 'Inappropriate Behavior',
                'description' => 'Engaging in disruptive or inappropriate conduct',
                'penalty' => 10,
            ],
            [
                'name' => 'Unauthorized Entry',
                'description' => 'Entering restricted areas without permission',
                'penalty' => 12,
            ],
        ];

        $violation = $this->faker->randomElement($violations);

        return [
            'name' => $violation['name'],
            'description' => $violation['description'],
            'penalty_score' => $violation['penalty'],
        ];
    }
}
