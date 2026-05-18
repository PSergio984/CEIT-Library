<?php

namespace Database\Factories;

use App\Models\ScoreIncrement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoreIncrement>
 */
class ScoreIncrementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rewardTypes = [
            ['name' => 'Attendance 30+ Minutes', 'points' => 5],
            ['name' => 'On-Time Return', 'points' => 10],
            ['name' => 'Perfect Attendance (Week)', 'points' => 15],
            ['name' => 'Library Event Participation', 'points' => 12],
            ['name' => 'Helping Other Students', 'points' => 8],
            ['name' => 'Book Care Excellence', 'points' => 10],
            ['name' => 'Early Bird Bonus', 'points' => 5],
            ['name' => 'Study Group Leader', 'points' => 7],
        ];

        $reward = $this->faker->randomElement($rewardTypes);

        return [
            'user_id' => User::factory(),
            'name' => $reward['name'],
            'description' => $this->faker->optional()->sentence(),
            'score_value' => $reward['points'], // Individual reward points (not total score)
        ];
    }
}
