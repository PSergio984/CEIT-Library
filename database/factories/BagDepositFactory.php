<?php

namespace Database\Factories;

use App\Models\BagDeposit;
use App\Models\BagSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BagDeposit>
 */
class BagDepositFactory extends Factory
{
    protected $model = BagDeposit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bag_slot_id' => BagSlot::factory(),
            'item_count' => $this->faker->numberBetween(1, 4),
            'description' => $this->faker->optional()->words(3, true),
            'claim_code' => str_pad((string) $this->faker->unique()->numberBetween(0, 999999), 6, '0', STR_PAD_LEFT),
            'status' => BagDeposit::STATUS_OPEN,
        ];
    }

    /**
     * A deposit closed by claim — frees its slot for the next deposit.
     */
    public function claimed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BagDeposit::STATUS_CLAIMED,
            'claimed_by' => User::factory(),
            'claimed_at' => now(),
            'claim_method' => BagDeposit::METHOD_OWNER_SCAN,
        ]);
    }
}
