<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\BorrowTransaction;
use App\Models\User;
use App\Models\AcademicPaper;
use App\Models\Inventory;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BorrowTransaction>
 */
class BorrowTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $timeIn = $this->faker->dateTimeBetween('-3 months', 'now');
        $timeOut = $this->faker->optional(0.8)->dateTimeBetween($timeIn, \Carbon\Carbon::parse($timeIn)->addHours(4));

        $status = 'started';
        if ($timeIn && $timeOut) {
            $status = 'completed';
        } elseif ($timeIn) {
            $carbonTimeIn = $timeIn instanceof \Carbon\Carbon ? $timeIn : \Carbon\Carbon::parse($timeIn);
            // Deterministic: if timeIn is older than 6 hours, set overdue, else started
            $hoursAgo = $carbonTimeIn->diffInHours(now());
            $status = $hoursAgo > 6 ? 'overdue' : 'started';
        }

        return [
            'user_id' => null, // Always pass explicitly in seeder
            'academic_paper_id' => null, // Always pass explicitly in seeder
            'inventory_id' => null, // Always pass explicitly in seeder
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'status' => $status,
            'expires_at' => \Carbon\Carbon::parse($timeIn)->addHours(6),
            'session_token' => \Illuminate\Support\Str::random(64),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'duration_minutes' => $timeIn && $timeOut ? \Carbon\Carbon::parse($timeIn)->diffInMinutes($timeOut) : null,
        ];
    }

    /**
     * Create an active academic paper session
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
     * Create a completed academic paper session
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
