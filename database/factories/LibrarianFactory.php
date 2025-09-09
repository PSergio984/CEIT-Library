<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Librarian;
use App\Models\User;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Librarian>
 */
class LibrarianFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => $this->faker->randomElement(['active', 'inactive', 'expired']),
            'expires_at' => Carbon::today()->endOfDay(), // Expires at end of day
            'created_by' => User::factory(),
            'last_login_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 week', 'now'),
            'shift_notes' => $this->faker->optional(0.5)->sentence(),
        ];
    }

    /**
     * Create an active librarian on duty
     */
    public function active()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => Carbon::today()->endOfDay(),
            'last_login_at' => Carbon::now()->subHours($this->faker->numberBetween(1, 8)),
        ]);
    }

    /**
     * Create an expired librarian duty
     */
    public function expired()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => Carbon::yesterday()->endOfDay(),
        ]);
    }
}
