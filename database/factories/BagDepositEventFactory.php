<?php

namespace Database\Factories;

use App\Models\BagDeposit;
use App\Models\BagDepositEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BagDepositEvent>
 */
class BagDepositEventFactory extends Factory
{
    protected $model = BagDepositEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bag_deposit_id' => BagDeposit::factory(),
            'type' => BagDepositEvent::TYPE_DEPOSIT,
            'actor_user_id' => User::factory(),
            'scanned_by' => null,
            'scanned_by_admin_id' => null,
            'method' => BagDeposit::METHOD_OWNER_SCAN,
            'note' => null,
            'id_verified' => false,
        ];
    }

    /**
     * A staff rescue release — flagged with a note and an ID check.
     */
    public function rescue(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BagDepositEvent::TYPE_CLAIM,
            'method' => BagDeposit::METHOD_RESCUE_LOOKUP,
            'note' => $this->faker->sentence(),
            'id_verified' => true,
        ]);
    }
}
