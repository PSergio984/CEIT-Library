<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Librarian;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timeIn = $this->faker->dateTimeBetween('-2 months', 'now');
        $timeOut = $this->faker->optional(0.9)->dateTimeBetween($timeIn, Carbon::parse($timeIn)->addHours(8));

        $durationMinutes = null;
        if ($timeOut) {
            $durationMinutes = Carbon::parse($timeIn)->diffInMinutes(Carbon::parse($timeOut));
        }

        $status = $timeOut ? 'completed' : 'active';

        // Only pick students for attendance, not super_admin
        $user = User::whereHas('role', function ($q) {
            $q->where('name', 'student');
        })->inRandomOrder()->first() ?? User::factory()->create();

        return [
            'user_id' => $user->id,
            'role_id' => $user->role_id,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'status' => $status,
            'scanned_by' => Librarian::inRandomOrder()->first()?->id ?? Librarian::factory(),
            'duration_minutes' => $durationMinutes,
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
            $durationMinutes = Carbon::parse($timeIn)->diffInMinutes($timeOut);

            return [
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'status' => 'completed',
                'duration_minutes' => $durationMinutes,
            ];
        });
    }
}
