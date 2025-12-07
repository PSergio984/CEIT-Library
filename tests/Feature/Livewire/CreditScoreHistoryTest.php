<?php

namespace Tests\Feature\Livewire;

use App\Livewire\CreditScoreHistory;
use Livewire\Livewire;
use Tests\TestCase;

class CreditScoreHistoryTest extends TestCase
{
    public function test_renders_successfully()
    {
        Livewire::test(CreditScoreHistory::class)
            ->assertStatus(200);
    }
}
