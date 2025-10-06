<?php

namespace Tests\Feature\Livewire\Student;

use App\Livewire\Pages\Student\RuleAndRegulationIndex;
use App\Models\RuleHeader;
use App\Models\RuleRegulation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RuleAndRegulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_headers_and_rules_in_order(): void
    {
        $h1 = RuleHeader::factory()
            ->has(
                RuleRegulation::factory()->state([
                    'content' => 'Alpha',
                    'order' => 1,
                ]),
                'ruleRegulations'
            )
            ->create([
                'title' => 'First',
                'order' => 1,
            ]);

        $h2 = RuleHeader::factory()
            ->has(
                RuleRegulation::factory()->state([
                    'content' => 'Beta',
                    'order' => 1,
                ]),
                'ruleRegulations'
            )
            ->create([
                'title' => 'Second',
                'order' => 2,
            ]);

        Livewire::test(RuleAndRegulationIndex::class)
            ->assertSeeInOrder(['First', 'Second'])
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('header-' . $h1->id)
            ->assertSee('header-' . $h2->id);
    }
}
