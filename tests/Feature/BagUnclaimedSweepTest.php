<?php

namespace Tests\Feature;

use App\Livewire\Pages\Admin\AdminBagCounter;
use App\Models\BagDeposit;
use App\Models\BagSlot;
use App\Models\Librarian;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class BagUnclaimedSweepTest extends TestCase
{
    use RefreshDatabase;

    private function librarianOnDuty(): User
    {
        $librarian = User::factory()->create(['role_id' => 2]);

        Librarian::create([
            'user_id' => $librarian->id,
            'batch_no' => 2026001,
            'start_date' => now()->subDays(1),
            'end_date' => now()->addDays(1),
            'status' => 'active',
        ]);

        return $librarian;
    }

    private function deposit(User $depositor, array $overrides = []): BagDeposit
    {
        $slot = BagSlot::create(['number' => (BagSlot::max('number') ?? 0) + 1, 'is_active' => true]);

        // created_at isn't fillable — pull it out and set it after create.
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $deposit = BagDeposit::create(array_merge([
            'user_id' => $depositor->id,
            'bag_slot_id' => $slot->id,
            'item_count' => 1,
            'description' => 'Bag',
            'claim_code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'status' => BagDeposit::STATUS_OPEN,
        ], $overrides));

        if ($createdAt) {
            $deposit->created_at = $createdAt;
            $deposit->save();
        }

        return $deposit;
    }

    public function test_sweep_notifies_depositor_of_every_open_deposit(): void
    {
        $depositorA = User::factory()->create(['role_id' => 1]);
        $depositorB = User::factory()->create(['role_id' => 1]);
        $openA = $this->deposit($depositorA);
        $openB = $this->deposit($depositorB);

        $this->artisan('bag:notify-unclaimed')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $depositorA->id,
            'type' => 'bag_unclaimed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $depositorB->id,
            'type' => 'bag_unclaimed',
        ]);

        $this->assertNotNull($openA->fresh()->unclaimed_notified_at);
        $this->assertNotNull($openB->fresh()->unclaimed_notified_at);
    }

    public function test_claimed_deposits_are_never_swept(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $claimed = $this->deposit($depositor, [
            'status' => BagDeposit::STATUS_CLAIMED,
            'claimed_by' => $depositor->id,
            'claimed_at' => now(),
            'claim_method' => BagDeposit::METHOD_OWNER_SCAN,
        ]);

        $this->artisan('bag:notify-unclaimed')
            ->expectsOutputToContain('No unclaimed bag deposits')
            ->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertNull($claimed->fresh()->unclaimed_notified_at);
    }

    public function test_sweep_is_idempotent_within_a_day_and_repings_next_day(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $deposit = $this->deposit($depositor);

        // First sweep notifies and stamps.
        $this->artisan('bag:notify-unclaimed')->assertExitCode(0);
        $this->assertSame(1, Notification::where('user_id', $depositor->id)->count());
        $firstStamp = $deposit->fresh()->unclaimed_notified_at;
        $this->assertNotNull($firstStamp);

        // Same day: stamped today -> skipped.
        $this->artisan('bag:notify-unclaimed')
            ->expectsOutputToContain('No unclaimed bag deposits')
            ->assertExitCode(0);
        $this->assertSame(1, Notification::where('user_id', $depositor->id)->count());

        // Next day: stale stamp -> re-ping.
        Carbon::setTestNow(now()->addDay()->setTime(21, 30));
        try {
            $this->artisan('bag:notify-unclaimed')->assertExitCode(0);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame(2, Notification::where('user_id', $depositor->id)->count());
        $this->assertTrue($deposit->fresh()->unclaimed_notified_at->gt($firstStamp));
    }

    public function test_sweep_skips_deposits_claimed_between_pings(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $deposit = $this->deposit($depositor);

        $this->artisan('bag:notify-unclaimed')->assertExitCode(0);
        $this->assertSame(1, Notification::count());

        // Claimed before the next day's sweep.
        $deposit->update([
            'status' => BagDeposit::STATUS_CLAIMED,
            'claimed_by' => $depositor->id,
            'claimed_at' => now(),
            'claim_method' => BagDeposit::METHOD_CLAIM_CODE,
        ]);

        Carbon::setTestNow(now()->addDay()->setTime(21, 30));
        try {
            $this->artisan('bag:notify-unclaimed')
                ->expectsOutputToContain('No unclaimed bag deposits')
                ->assertExitCode(0);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame(1, Notification::count());
    }

    public function test_dry_run_lists_without_notifying_or_stamping(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $deposit = $this->deposit($depositor);

        $this->artisan('bag:notify-unclaimed', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertNull($deposit->fresh()->unclaimed_notified_at);
    }

    public function test_counter_page_lists_previous_day_open_deposits(): void
    {
        $overnightUser = User::factory()->create(['role_id' => 1]);
        $todayUser = User::factory()->create(['role_id' => 1]);
        $claimedUser = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();

        // Left overnight, still open -> listed.
        $overnight = $this->deposit($overnightUser, ['created_at' => now()->subDay()]);

        // Opened today -> not unclaimed yet.
        $today = $this->deposit($todayUser);

        // Claimed yesterday -> never unclaimed.
        $claimed = $this->deposit($claimedUser, [
            'created_at' => now()->subDay(),
            'status' => BagDeposit::STATUS_CLAIMED,
            'claimed_by' => $claimedUser->id,
            'claimed_at' => now()->subDay(),
            'claim_method' => BagDeposit::METHOD_RESCUE_LOOKUP,
        ]);

        $this->actingAs($librarian);

        // Assert against the unclaimed card's wire keys so the open-deposits
        // ledger (which also lists open deposits) can't produce false hits.
        Livewire::test(AdminBagCounter::class)
            ->assertSee('unclaimed-'.$overnight->id, false)
            ->assertDontSee('unclaimed-'.$today->id, false)
            ->assertDontSee('unclaimed-'.$claimed->id, false);
    }
}
