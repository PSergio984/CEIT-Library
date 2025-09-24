<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Attendance;
use App\Models\User;
use App\Models\Librarian;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timeIn = $this->faker->dateTimeBetween('-2 months', 'now');
        $timeOut = $this->faker->optional(0.9)->dateTimeBetween($timeIn, Carbon::parse($timeIn)->addHours(8));

        $status = $timeOut ? 'completed' : 'active';

        return [
            'user_id' => User::factory(),
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'status' => $status,
            'scanned_by' => Librarian::factory(),
            'duration_minutes' => $timeIn && $timeOut ? Carbon::parse($timeIn)->diffInMinutes($timeOut) : null,
        ];
    }

    /**
     * Create an active attendance (user currently in library)
     */
    public function active()
    {
        return $this->state(function (array $attributes) {
            $timeIn = Carbon::now()->subMinutes($this->faker->numberBetween(30, 300));
            return [
                'time_in' => $timeIn,
                'time_out' => null,
                'status' => 'active',
                'duration_minutes' => null,
            ];
        });
    }

    /**
     * Create a completed library session
     */
    public function completed()
    {
        return $this->state(function (array $attributes) {
            $timeIn = $this->faker->dateTimeBetween('-1 month', '-1 day');
            $timeOut = Carbon::parse($timeIn)->addMinutes($this->faker->numberBetween(60, 480));

            return [
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'status' => 'completed',
                'duration_minutes' => Carbon::parse($timeIn)->diffInMinutes($timeOut),
            ];
        });
    }
}
