<?php

namespace Database\Seeders;

use App\Models\BagSlot;
use Illuminate\Database\Seeder;

class BagSlotSeeder extends Seeder
{
    /**
     * Provision the physical numbered slots the bag counter deposits into.
     * Idempotent — firstOrCreate never clobbers an out-of-service flag.
     */
    public function run(): void
    {
        for ($number = 1; $number <= 20; $number++) {
            BagSlot::firstOrCreate(['number' => $number], ['is_active' => true]);
        }
    }
}
