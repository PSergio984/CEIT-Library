<?php

namespace Database\Factories;

use App\Models\RuleHeader;
use App\Models\RuleRegulation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RuleRegulation>
 */
class RuleRegulationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_header_id' => RuleHeader::factory(),
            'content' => $this->faker->paragraph(2),
        ];
    }
}
