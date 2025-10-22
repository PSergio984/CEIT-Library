<?php

namespace Database\Factories;

use App\Models\Librarian;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

use function PHPUnit\Framework\isNull;

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
        $dateStart = $this->faker->optional()->dateTimeBetween('-6 months', '+6 months');
        $dateStart2 = $dateStart ? $dateStart->format('Y-m-d H:i:s') : null;

        return [
            'user_id' => User::factory(),
            'batch_no' => $this->faker->unique()->numberBetween(2025000, 2025999),
            'expires_at' => Carbon::today()->endOfDay(),
            'created_by' => User::factory(),
            'last_login_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 week', 'now'),
            'date_start' => $dateStart2,
            'status' => is_null($dateStart2) ? 'inactive' : $this->faker->randomElement(['active', 'expired']),
            'shift_notes' => $this->faker->optional(0.5)->sentence(),
        ];

    }

    /**
     * Configure the factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Librarian $librarian) {
            $year = date('Y');
            $librarian->batch_no = $year . str_pad($librarian->id, 4, '0', STR_PAD_LEFT);
            $librarian->save();
        });
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
