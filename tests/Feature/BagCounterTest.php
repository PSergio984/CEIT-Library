<?php

namespace Tests\Feature;

use App\Livewire\Pages\Admin\AdminBagCounter;
use App\Models\BagDeposit;
use App\Models\BagDepositEvent;
use App\Models\BagSlot;
use App\Models\Librarian;
use App\Models\User;
use App\Traits\CreatesQrCanonicalMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BagCounterTest extends TestCase
{
    use CreatesQrCanonicalMessage, RefreshDatabase;

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

    private function makeQr(User $user): string
    {
        $data = [
            'v' => 7,
            'user_id' => $user->id,
            'nonce' => Str::random(16),
            'timestamp' => time(),
        ];

        $canonicalMessage = $this->createCanonicalMessage($data);
        $data['hash'] = hash_hmac('sha256', $canonicalMessage, config('app.qr_hmac_secret'));

        return json_encode(['encrypted' => Crypt::encryptString(json_encode($data))]);
    }

    private function makeSlots(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            BagSlot::create(['number' => $i, 'is_active' => true]);
        }
    }

    public function test_operator_opens_deposit_by_scanning_user_qr(): void
    {
        $student = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $this->makeSlots(4);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($student))
            ->assertSet('scannedUserId', $student->id)
            ->call('openDepositModal')
            ->assertSet('depositModal', true)
            ->set('formItemCount', 2)
            ->set('formDescription', 'Grey backpack + box')
            ->call('submitDeposit')
            ->assertHasNoErrors()
            ->assertSet('issuedCode', fn ($code) => preg_match('/^\d{6}$/', $code) === 1);

        $deposit = BagDeposit::sole();

        $this->assertSame($student->id, $deposit->user_id);
        $this->assertSame(2, $deposit->item_count);
        $this->assertSame('Grey backpack + box', $deposit->description);
        $this->assertSame(BagDeposit::STATUS_OPEN, $deposit->status);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $deposit->claim_code);
        $this->assertSame(BagSlot::STATE_OCCUPIED, $deposit->slot->state());
    }

    public function test_claim_codes_are_unique_among_open_deposits(): void
    {
        $librarian = $this->librarianOnDuty();
        $this->makeSlots(3);
        $students = User::factory(2)->create(['role_id' => 1]);

        $this->actingAs($librarian);

        foreach ($students as $i => $student) {
            Livewire::test(AdminBagCounter::class)
                ->call('handleScan', $this->makeQr($student))
                ->call('openDepositModal')
                ->set('formSlot', BagSlot::orderBy('number')->skip($i)->first()->id)
                ->call('submitDeposit');
        }

        $codes = BagDeposit::open()->pluck('claim_code');
        $this->assertCount(2, $codes->unique());
        $codes->each(fn ($code) => $this->assertMatchesRegularExpression('/^\d{6}$/', $code));

        // A claimed code can be reused — uniqueness only holds among open deposits.
        BagDeposit::open()->first()->update([
            'status' => BagDeposit::STATUS_CLAIMED,
            'claimed_by' => $students[0]->id,
            'claimed_at' => now(),
            'claim_method' => BagDeposit::METHOD_OWNER_SCAN,
        ]);

        $claimedCode = BagDeposit::first()->claim_code;
        BagDeposit::create([
            'user_id' => $students[1]->id,
            'bag_slot_id' => BagSlot::orderBy('number')->skip(2)->first()->id,
            'item_count' => 1,
            'claim_code' => $claimedCode,
            'status' => BagDeposit::STATUS_OPEN,
        ]);

        $this->assertDatabaseHas('bag_deposits', ['claim_code' => $claimedCode, 'status' => 'open']);
    }

    public function test_owner_scan_lists_and_claims_their_open_deposits(): void
    {
        $student = User::factory()->create(['role_id' => 1]);
        $other = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $this->makeSlots(3);

        $ownDeposit = BagDeposit::create([
            'user_id' => $student->id,
            'bag_slot_id' => BagSlot::where('number', 1)->first()->id,
            'item_count' => 1,
            'description' => 'Tuba case',
            'claim_code' => '123456',
            'status' => BagDeposit::STATUS_OPEN,
        ]);
        BagDeposit::create([
            'user_id' => $other->id,
            'bag_slot_id' => BagSlot::where('number', 2)->first()->id,
            'item_count' => 1,
            'claim_code' => '654321',
            'status' => BagDeposit::STATUS_OPEN,
        ]);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($student))
            ->assertSet('scannedUserId', $student->id)
            // Owner's open deposit is listed; the other user's is not.
            ->assertSee('Tuba case')
            ->assertDontSee('654321')
            ->call('claimDeposit', $ownDeposit->id);

        $ownDeposit->refresh();
        $this->assertSame(BagDeposit::STATUS_CLAIMED, $ownDeposit->status);
        $this->assertSame($student->id, $ownDeposit->claimed_by);
        $this->assertNotNull($ownDeposit->claimed_at);
        $this->assertSame(BagDeposit::METHOD_OWNER_SCAN, $ownDeposit->claim_method);

        // Slot is free again
        $this->assertSame(BagSlot::STATE_FREE, $ownDeposit->slot->state());

        // Claim event recorded
        $this->assertDatabaseHas('bag_deposit_events', [
            'bag_deposit_id' => $ownDeposit->id,
            'type' => BagDepositEvent::TYPE_CLAIM,
            'actor_user_id' => $student->id,
            'method' => 'owner_scan',
        ]);
    }

    public function test_deposit_and_claim_write_audit_events_with_actor_and_scanner(): void
    {
        $student = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $this->makeSlots(1);

        $this->actingAs($librarian);

        $qr = $this->makeQr($student);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $qr)
            ->call('openDepositModal')
            ->call('submitDeposit');

        $deposit = BagDeposit::sole();
        $librarianDuty = $librarian->getActiveLibrarianDuty();

        // Deposit event: actor = depositor, scanner = librarian duty
        $depositEvent = BagDepositEvent::where('type', 'deposit')->sole();
        $this->assertSame($deposit->id, $depositEvent->bag_deposit_id);
        $this->assertSame($student->id, $depositEvent->actor_user_id);
        $this->assertSame($librarianDuty->id, $depositEvent->scanned_by);
        $this->assertNull($depositEvent->scanned_by_admin_id);
        $this->assertNotNull($depositEvent->created_at);

        // Claim event: actor = claimant, same scanner identity
        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($student))
            ->call('claimDeposit', $deposit->id);

        $claimEvent = BagDepositEvent::where('type', 'claim')->sole();
        $this->assertSame($student->id, $claimEvent->actor_user_id);
        $this->assertSame($librarianDuty->id, $claimEvent->scanned_by);
        $this->assertSame('owner_scan', $claimEvent->method);
        $this->assertNotNull($claimEvent->created_at);
    }

    public function test_admin_scanner_is_recorded_as_admin_not_librarian(): void
    {
        $student = User::factory()->create(['role_id' => 1]);
        $admin = User::factory()->create(['role_id' => 3]);
        $this->makeSlots(1);

        $this->actingAs($admin);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($student))
            ->call('openDepositModal')
            ->call('submitDeposit');

        $event = BagDepositEvent::sole();
        $this->assertNull($event->scanned_by);
        $this->assertSame($admin->id, $event->scanned_by_admin_id);
    }

    public function test_occupied_and_out_of_service_slots_cannot_receive_deposits(): void
    {
        $student = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $this->makeSlots(2);

        $occupied = BagSlot::where('number', 1)->first();
        $outOfService = BagSlot::where('number', 2)->first();
        $outOfService->update(['is_active' => false]);

        BagDeposit::create([
            'user_id' => $student->id,
            'bag_slot_id' => $occupied->id,
            'item_count' => 1,
            'claim_code' => '111111',
            'status' => BagDeposit::STATUS_OPEN,
        ]);

        $this->actingAs($librarian);

        // Occupied slot
        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($student))
            ->call('openDepositModal')
            ->set('formSlot', $occupied->id)
            ->call('submitDeposit');

        $this->assertSame(1, BagDeposit::count());

        // Out-of-service slot
        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($student))
            ->call('openDepositModal')
            ->set('formSlot', $outOfService->id)
            ->call('submitDeposit');

        $this->assertSame(1, BagDeposit::count());
    }

    public function test_slot_can_be_marked_out_of_service_and_back(): void
    {
        $librarian = $this->librarianOnDuty();
        $this->makeSlots(1);
        $slot = BagSlot::first();

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('toggleSlotService', $slot->id);

        $this->assertFalse($slot->fresh()->is_active);

        Livewire::test(AdminBagCounter::class)
            ->call('toggleSlotService', $slot->id);

        $this->assertTrue($slot->fresh()->is_active);
    }

    public function test_occupied_slot_cannot_be_taken_out_of_service(): void
    {
        $student = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $this->makeSlots(1);
        $slot = BagSlot::first();

        BagDeposit::create([
            'user_id' => $student->id,
            'bag_slot_id' => $slot->id,
            'item_count' => 1,
            'claim_code' => '222222',
            'status' => BagDeposit::STATUS_OPEN,
        ]);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('toggleSlotService', $slot->id);

        $this->assertTrue($slot->fresh()->is_active);
    }

    public function test_user_can_hold_multiple_concurrent_open_deposits(): void
    {
        $student = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $this->makeSlots(3);

        $this->actingAs($librarian);

        $component = Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($student));

        // One scan serves several deposits for the same courier — no rescan needed.
        $component->call('openDepositModal')
            ->set('formSlot', BagSlot::where('number', 1)->first()->id)
            ->call('submitDeposit');
        $component->call('openDepositModal')
            ->set('formSlot', BagSlot::where('number', 2)->first()->id)
            ->call('submitDeposit');

        $this->assertSame(2, BagDeposit::where('user_id', $student->id)->open()->count());
    }

    public function test_invalid_qr_is_rejected(): void
    {
        $librarian = $this->librarianOnDuty();
        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', 'not-a-qr-payload')
            ->assertSet('scannedUserId', null);
    }

    public function test_claim_by_non_owner_scan_is_rejected(): void
    {
        $student = User::factory()->create(['role_id' => 1]);
        $other = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $this->makeSlots(1);

        $deposit = BagDeposit::create([
            'user_id' => $student->id,
            'bag_slot_id' => BagSlot::first()->id,
            'item_count' => 1,
            'claim_code' => '333333',
            'status' => BagDeposit::STATUS_OPEN,
        ]);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($other))
            ->call('claimDeposit', $deposit->id);

        $this->assertSame(BagDeposit::STATUS_OPEN, $deposit->fresh()->status);
    }

    public function test_deposits_work_without_any_attendance_record(): void
    {
        $student = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $this->makeSlots(1);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($student))
            ->call('openDepositModal')
            ->call('submitDeposit');

        $this->assertDatabaseCount('bag_deposits', 1);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_page_is_gated_to_librarian_or_admin(): void
    {
        $student = User::factory()->create(['role_id' => 1]);

        // Route is behind the librarian.or.admin middleware; the component
        // gate refuses non-privileged users at mount.
        $this->actingAs($student);

        Livewire::test(AdminBagCounter::class)
            ->assertRedirect(route('student.dashboard'));
    }
}
