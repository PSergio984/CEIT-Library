<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Pages\Admin\AdminRuleAndRegulationIndex;
use App\Models\RuleHeader;
use App\Models\RuleRegulation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminRuleAndRegulationIndexTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_reads_rules_list(): void
    {
        $user = User::factory()->create();
        $header = RuleHeader::factory()->create(['title' => 'Main Library Rules']);
        $regulation = RuleRegulation::factory()->create([
            'rule_header_id' => $header->id,
            'content' => '1. Silence must be observed.',
        ]);

        Livewire::actingAs($user)
            ->test(AdminRuleAndRegulationIndex::class)
            ->assertSee($header->title)
            ->assertSee($regulation->content);
    }

    #[Test]
    public function it_creates_a_rule_via_admin_index_component(): void
    {
        $user = User::factory()->create();
        $header = RuleHeader::factory()->create(['title' => 'General']);

        Livewire::actingAs($user)
            ->test(AdminRuleAndRegulationIndex::class)
            ->call('openCreateDrawer')
            ->set('form.rule_header_id', $header->id)
            ->set('form.content', 'Return books on time.')
            ->call('save')
            ->assertSet('openDrawer', false)
            ->assertSee('Return books on time.');

        $this->assertDatabaseHas('rule_regulations', [
            'rule_header_id' => $header->id,
            'content' => 'Return books on time.',
        ]);
    }

    #[Test]
    public function it_updates_a_rule_via_admin_index_component(): void
    {
        $user = User::factory()->create();
        $header = RuleHeader::factory()->create(['title' => 'General']);
        $rule = RuleRegulation::factory()->create([
            'rule_header_id' => $header->id,
            'content' => 'Old content',
        ]);

        Livewire::actingAs($user)
            ->test(AdminRuleAndRegulationIndex::class)
            ->call('edit', $rule->id)
            ->set('form.content', 'Updated content')
            ->call('update')
            ->assertSet('openDrawer', false)
            ->assertSee('Updated content');

        $this->assertDatabaseHas('rule_regulations', [
            'id' => $rule->id,
            'content' => 'Updated content',
        ]);
    }

    #[Test]
    public function it_deletes_a_rule_via_admin_index_component(): void
    {
        $user = User::factory()->create();
        $header = RuleHeader::factory()->create(['title' => 'General']);
        $rule = RuleRegulation::factory()->create([
            'rule_header_id' => $header->id,
            'content' => 'To be deleted',
        ]);

        Livewire::actingAs($user)
            ->test(AdminRuleAndRegulationIndex::class)
            ->assertSee('To be deleted')
            ->call('confirmDelete', $rule->id)
            ->assertSet('confirmDeleteModal', true)
            ->call('deleteConfirmed')
            ->assertSet('confirmDeleteModal', false)
            ->assertDontSee('To be deleted');

        $this->assertDatabaseMissing('rule_regulations', ['id' => $rule->id]);
    }
}
