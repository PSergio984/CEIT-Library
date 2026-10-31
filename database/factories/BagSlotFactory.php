<?php

namespace Database\Factories;

use App\Models\BagSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BagSlot>
 */
class BagSlotFactory extends Factory
{
    protected $model = BagSlot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => $this->faker->unique()->numberBetween(1, 9999),
            'is_active' => true,
        ];
    }

    /**
     * A slot marked out of service.
     */
    public function outOfService(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
