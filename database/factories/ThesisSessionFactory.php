<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ThesisSession;
use App\Models\User;
use App\Models\Thesis;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ThesisSession>
 */
class ThesisSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timeIn = $this->faker->dateTimeBetween('-3 months', 'now');
        $timeOut = $this->faker->optional(0.8)->dateTimeBetween($timeIn, Carbon::parse($timeIn)->addHours(4));

        $status = 'requested';
        if ($timeIn && $timeOut) {
            $status = 'completed';
        } elseif ($timeIn) {
            $status = $this->faker->randomElement(['started', 'expired']);
        }

        return [
            'user_id' => User::factory(),
            'thesis_id' => Thesis::factory(),
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'status' => $status,
            'expires_at' => Carbon::parse($timeIn)->addHours(6), // 6 hours to read
            'session_token' => Str::random(64),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'duration_minutes' => $timeIn && $timeOut ? Carbon::parse($timeIn)->diffInMinutes($timeOut) : null,
        ];
    }

    /**
     * Create an active thesis session
     */
    public function active()
    {
        return $this->state(function (array $attributes) {
            $timeIn = Carbon::now()->subMinutes($this->faker->numberBetween(30, 180));
            return [
                'time_in' => $timeIn,
                'time_out' => null,
                'status' => 'started',
                'expires_at' => $timeIn->copy()->addHours(6),
                'duration_minutes' => null,
            ];
        });
    }

    /**
     * Create a completed thesis session
     */
    public function completed()
    {
        return $this->state(function (array $attributes) {
            $timeIn = $this->faker->dateTimeBetween('-1 month', '-1 day');
            $timeOut = Carbon::parse($timeIn)->addMinutes($this->faker->numberBetween(60, 240));

            return [
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'status' => 'completed',
                'duration_minutes' => Carbon::parse($timeIn)->diffInMinutes($timeOut),
            ];
        });
    }
}
