<?php

namespace Database\Factories;

use App\Models\BorrowTransaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BorrowTransaction>
 */
class BorrowTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     * Creates a completed transaction by default with consistent data.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Default to a completed transaction with valid time_in and time_out
        $timeIn = $this->faker->dateTimeBetween('-3 months', '-1 day');
        $timeOut = Carbon::parse($timeIn)->addMinutes($this->faker->numberBetween(60, 240));
        $expiresAt = Carbon::parse($timeIn)->addHours(6);

        return [
            'user_id' => null, // Always pass explicitly in seeder
            'academic_paper_id' => null, // Always pass explicitly in seeder
            'inventory_id' => null, // Always pass explicitly in seeder
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'status' => 'completed',
            'expires_at' => $expiresAt,
            'session_token' => Str::random(64),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'duration_minutes' => Carbon::parse($timeIn)->diffInMinutes($timeOut),
        ];
    }

    /**
     * Create an active/started transaction (book currently borrowed)
     * - time_out is NULL
     * - status is 'started'
     * - expires_at is in the future
     */
    public function active()
    {
        return $this->state(function (array $attributes) {
            $timeIn = Carbon::now()->subMinutes($this->faker->numberBetween(30, 180));

            return [
                'time_in' => $timeIn,
                'time_out' => null, // Not returned yet
                'status' => 'started',
                'expires_at' => $timeIn->copy()->addHours(6), // Still has time
                'duration_minutes' => null,
            ];
        });
    }

    /**
     * Alias for active() - creates a started transaction
     */
    public function started()
    {
        return $this->active();
    }

    /**
     * Create a completed transaction (book has been returned)
     * - time_out is set (before expires_at for on-time return)
     * - status is 'completed'
     */
    public function completed()
    {
        return $this->state(function (array $attributes) {
            $timeIn = $this->faker->dateTimeBetween('-1 month', '-1 day');
            $carbonTimeIn = Carbon::parse($timeIn);
            $expiresAt = $carbonTimeIn->copy()->addHours(6);
            // Return before expiry for on-time return
            $timeOut = $carbonTimeIn->copy()->addMinutes($this->faker->numberBetween(60, 240));

            return [
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'status' => 'completed',
                'expires_at' => $expiresAt,
                'duration_minutes' => $carbonTimeIn->diffInMinutes($timeOut),
            ];
        });
    }

    /**
     * Create an overdue transaction (book not returned, past due date)
     * - time_out is NULL (not returned yet)
     * - status is 'overdue'
     * - expires_at is in the past
     */
    public function overdue()
    {
        return $this->state(function (array $attributes) {
            // Borrowed more than 6 hours ago, so it's past the expiration
            $timeIn = Carbon::now()->subHours($this->faker->numberBetween(7, 48));
            $expiresAt = $timeIn->copy()->addHours(6); // This is in the past

            return [
                'time_in' => $timeIn,
                'time_out' => null, // NOT returned yet - this is key!
                'status' => 'overdue',
                'expires_at' => $expiresAt,
                'duration_minutes' => null,
            ];
        });
    }

    /**
     * Create a late-returned transaction (book returned after due date)
     * - time_out is set (after expires_at)
     * - status is 'completed'
     */
    public function lateReturn()
    {
        return $this->state(function (array $attributes) {
            $timeIn = $this->faker->dateTimeBetween('-2 months', '-1 week');
            $carbonTimeIn = Carbon::parse($timeIn);
            $expiresAt = $carbonTimeIn->copy()->addHours(6);
            // Return AFTER expiry for late return
            $timeOut = $expiresAt->copy()->addHours($this->faker->numberBetween(1, 24));

            return [
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'status' => 'completed', // Still completed, just late
                'expires_at' => $expiresAt,
                'duration_minutes' => $carbonTimeIn->diffInMinutes($timeOut),
            ];
        });
    }
}
