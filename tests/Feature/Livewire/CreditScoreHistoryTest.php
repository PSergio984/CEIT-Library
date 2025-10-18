<?php

namespace Tests\Feature\Livewire;

use App\Livewire\CreditScoreHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
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
