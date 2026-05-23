<?php

namespace Database\Factories;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition()
    {
        return [
            'copy_number' => $this->faker->unique()->numberBetween(1, 100),
            'status' => $this->faker->randomElement(['Available', 'Reserved', 'Unavailable']),
        ];
    }
}
