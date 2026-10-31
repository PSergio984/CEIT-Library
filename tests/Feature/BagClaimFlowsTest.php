<?php

namespace Tests\Feature;

use App\Livewire\Pages\Admin\AdminBagCounter;
use App\Models\BagDeposit;
use App\Models\BagDepositEvent;
use App\Models\BagSlot;
use App\Models\Librarian;
use App\Models\Notification;
use App\Models\User;
use App\Traits\CreatesQrCanonicalMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BagClaimFlowsTest extends TestCase
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

    private function openDeposit(User $depositor, string $code = '123456', array $overrides = []): BagDeposit
    {
        $slot = BagSlot::create(['number' => (BagSlot::max('number') ?? 0) + 1, 'is_active' => true]);

        return BagDeposit::create(array_merge([
            'user_id' => $depositor->id,
            'bag_slot_id' => $slot->id,
            'item_count' => 2,
            'description' => 'Grey backpack',
            'claim_code' => $code,
            'status' => BagDeposit::STATUS_OPEN,
        ], $overrides));
    }

    public function test_third_party_claim_succeeds_with_own_qr_plus_claim_code(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $claimant = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $deposit = $this->openDeposit($depositor);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($claimant))
            ->assertSet('scannedUserId', $claimant->id)
            ->set('claimCodeInput', '123456')
            ->call('lookupClaimCode')
            ->call('claimDeposit', $deposit->id);

        $deposit->refresh();
        $this->assertSame(BagDeposit::STATUS_CLAIMED, $deposit->status);
        $this->assertSame($claimant->id, $deposit->claimed_by);
        $this->assertNotNull($deposit->claimed_at);
        $this->assertSame(BagDeposit::METHOD_CLAIM_CODE, $deposit->claim_method);
        $this->assertSame(BagSlot::STATE_FREE, $deposit->slot->state());

        $this->assertDatabaseHas('bag_deposit_events', [
            'bag_deposit_id' => $deposit->id,
            'type' => BagDepositEvent::TYPE_CLAIM,
            'actor_user_id' => $claimant->id,
            'method' => BagDeposit::METHOD_CLAIM_CODE,
        ]);
    }

    public function test_wrong_claim_code_is_rejected(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $claimant = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $deposit = $this->openDeposit($depositor, '123456');

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($claimant))
            ->set('claimCodeInput', '999999')
            ->call('lookupClaimCode')
            ->call('claimDeposit', $deposit->id);

        $deposit->refresh();
        $this->assertSame(BagDeposit::STATUS_OPEN, $deposit->status);
        $this->assertNull($deposit->claimed_by);
        $this->assertSame(0, BagDepositEvent::where('type', BagDepositEvent::TYPE_CLAIM)->count());
    }

    public function test_claim_without_code_or_own_scan_is_rejected(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $other = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $deposit = $this->openDeposit($depositor);

        $this->actingAs($librarian);

        // Scanned a valid user who is not the depositor and presented no code.
        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($other))
            ->call('claimDeposit', $deposit->id);

        $this->assertSame(BagDeposit::STATUS_OPEN, $deposit->fresh()->status);
    }

    public function test_depositor_is_notified_when_claimant_differs(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $claimant = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $deposit = $this->openDeposit($depositor);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($claimant))
            ->set('claimCodeInput', $deposit->claim_code)
            ->call('lookupClaimCode')
            ->call('claimDeposit', $deposit->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $depositor->id,
            'type' => 'bag_claim',
        ]);

        $notification = Notification::where('user_id', $depositor->id)->sole();
        $this->assertSame($deposit->id, $notification->data['bag_deposit_id']);
        $this->assertSame(BagDeposit::METHOD_CLAIM_CODE, $notification->data['claim_method']);
        // The message names the person who collected, not just the method.
        $this->assertStringContainsString('claim code', $notification->message);
        $this->assertStringContainsString(
            $claimant->first_name.' '.$claimant->last_name,
            $notification->message
        );
    }

    public function test_owner_claim_sends_no_notification(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $deposit = $this->openDeposit($depositor);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($depositor))
            ->call('claimDeposit', $deposit->id);

        $deposit->refresh();
        $this->assertSame(BagDeposit::STATUS_CLAIMED, $deposit->status);
        $this->assertSame(BagDeposit::METHOD_OWNER_SCAN, $deposit->claim_method);
        $this->assertSame(0, Notification::where('user_id', $depositor->id)->count());
    }

    public function test_temp_access_logs_event_and_keeps_deposit_open(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $deposit = $this->openDeposit($depositor);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($depositor))
            ->call('tempAccess', $deposit->id);

        $deposit->refresh();
        $this->assertSame(BagDeposit::STATUS_OPEN, $deposit->status);
        $this->assertNull($deposit->claimed_by);
        $this->assertSame(BagSlot::STATE_OCCUPIED, $deposit->slot->state());

        $this->assertDatabaseHas('bag_deposit_events', [
            'bag_deposit_id' => $deposit->id,
            'type' => BagDepositEvent::TYPE_TEMP_ACCESS,
            'actor_user_id' => $depositor->id,
            'method' => BagDeposit::METHOD_OWNER_SCAN,
        ]);

        // Owner access — no notification.
        $this->assertSame(0, Notification::count());
    }

    public function test_temp_access_by_third_party_via_code_notifies_depositor(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $accessor = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $deposit = $this->openDeposit($depositor);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($accessor))
            ->set('claimCodeInput', $deposit->claim_code)
            ->call('lookupClaimCode')
            ->call('tempAccess', $deposit->id);

        $this->assertSame(BagDeposit::STATUS_OPEN, $deposit->fresh()->status);
        $this->assertDatabaseHas('bag_deposit_events', [
            'bag_deposit_id' => $deposit->id,
            'type' => BagDepositEvent::TYPE_TEMP_ACCESS,
            'actor_user_id' => $accessor->id,
            'method' => BagDeposit::METHOD_CLAIM_CODE,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $depositor->id,
            'type' => 'bag_temp_access',
        ]);
    }

    public function test_temp_access_without_code_is_rejected(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $other = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $deposit = $this->openDeposit($depositor);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($other))
            ->call('tempAccess', $deposit->id);

        $this->assertSame(0, BagDepositEvent::where('type', BagDepositEvent::TYPE_TEMP_ACCESS)->count());
    }

    public function test_rescue_claim_requires_note_and_id_verification(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $deposit = $this->openDeposit($depositor);

        $this->actingAs($librarian);

        // No note, no ID check
        Livewire::test(AdminBagCounter::class)
            ->call('openRescue', $deposit->id)
            ->call('submitRescue')
            ->assertHasErrors(['rescueNote', 'rescueIdVerified']);

        $this->assertSame(BagDeposit::STATUS_OPEN, $deposit->fresh()->status);

        // Note but no ID check
        Livewire::test(AdminBagCounter::class)
            ->call('openRescue', $deposit->id)
            ->set('rescueNote', 'Claimant showed a student ID card')
            ->call('submitRescue')
            ->assertHasErrors(['rescueIdVerified']);

        $this->assertSame(BagDeposit::STATUS_OPEN, $deposit->fresh()->status);
    }

    public function test_rescue_claim_closes_deposit_flagged_with_note_and_id_verified(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $deposit = $this->openDeposit($depositor);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('openRescue', $deposit->id)
            ->assertSet('rescueModal', true)
            ->set('rescueNote', 'Lost phone — verified against student ID #2024-118')
            ->set('rescueIdVerified', true)
            ->call('submitRescue')
            ->assertSet('rescueModal', false)
            ->assertHasNoErrors();

        $deposit->refresh();
        $this->assertSame(BagDeposit::STATUS_CLAIMED, $deposit->status);
        $this->assertSame($depositor->id, $deposit->claimed_by);
        $this->assertNotNull($deposit->claimed_at);
        $this->assertSame(BagDeposit::METHOD_RESCUE_LOOKUP, $deposit->claim_method);
        $this->assertSame(BagSlot::STATE_FREE, $deposit->slot->state());

        $event = BagDepositEvent::where('type', BagDepositEvent::TYPE_CLAIM)->sole();
        $this->assertSame(BagDeposit::METHOD_RESCUE_LOOKUP, $event->method);
        $this->assertSame('Lost phone — verified against student ID #2024-118', $event->note);
        $this->assertTrue($event->id_verified);

        // Rescue releases always notify the depositor.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $depositor->id,
            'type' => 'bag_claim',
        ]);

        // The rescue is visibly flagged in the rendered audit trail.
        Livewire::test(AdminBagCounter::class)
            ->call('openAudit', $deposit->id)
            ->assertSee('RESCUE')
            ->assertSee('ID verified')
            ->assertSee('Lost phone');
    }

    public function test_ledger_search_finds_deposit_for_rescue_lookup(): void
    {
        $depositor = User::factory()->create(['role_id' => 1, 'first_name' => 'Marisol']);
        $other = User::factory()->create(['role_id' => 1, 'first_name' => 'Zelda']);
        $librarian = $this->librarianOnDuty();
        $match = $this->openDeposit($depositor, '111111');
        $miss = $this->openDeposit($other, '222222', ['description' => 'Red duffel']);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->set('depositSearch', 'Marisol')
            ->assertSee('Grey backpack')
            ->assertDontSee('Red duffel');
    }

    public function test_wrong_claim_codes_lock_out_further_lookups(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $claimant = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $deposit = $this->openDeposit($depositor, '123456');

        $this->actingAs($librarian);

        $component = Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($claimant));

        // Ten wrong codes trip the per-operator limiter.
        for ($i = 0; $i < 10; $i++) {
            $component->set('claimCodeInput', '999999')->call('lookupClaimCode');
        }

        // The next lookup is refused before it runs — even the right code
        // can't grant an entitlement while locked out.
        $component->set('claimCodeInput', '123456')
            ->call('lookupClaimCode')
            ->assertSet('codeEntitledIds', []);

        $component->call('claimDeposit', $deposit->id);
        $this->assertSame(BagDeposit::STATUS_OPEN, $deposit->fresh()->status);

        // The lockout spans ten minutes, not one — still held nine minutes in.
        Carbon::setTestNow(now()->addMinutes(9));
        $component->set('claimCodeInput', '123456')
            ->call('lookupClaimCode')
            ->assertSet('codeEntitledIds', []);

        // Past the window the correct code is honoured again.
        Carbon::setTestNow(now()->addMinutes(2));
        try {
            $component->set('claimCodeInput', '123456')
                ->call('lookupClaimCode')
                ->assertSet('codeEntitledIds', [$deposit->id]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_correct_claim_code_does_not_reset_the_miss_budget(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $claimant = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $depositA = $this->openDeposit($depositor, '123456');
        $depositB = $this->openDeposit($depositor, '654321');

        $this->actingAs($librarian);

        $component = Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($claimant));

        // Nine misses leave a single attempt of budget.
        for ($i = 0; $i < 9; $i++) {
            $component->set('claimCodeInput', '999999')->call('lookupClaimCode');
        }

        // A good code unlocks its deposit but must not buy fresh attempts.
        $component->set('claimCodeInput', '123456')
            ->call('lookupClaimCode')
            ->assertSet('codeEntitledIds', [$depositA->id]);

        // One more miss reaches ten — the limiter trips.
        $component->set('claimCodeInput', '999999')->call('lookupClaimCode');

        // The next lookup is refused outright — even the other known code.
        $component->set('claimCodeInput', '654321')
            ->call('lookupClaimCode')
            ->assertSet('codeEntitledIds', [$depositA->id]);
    }

    public function test_rescue_modal_context_survives_an_active_search(): void
    {
        $depositor = User::factory()->create(['role_id' => 1, 'first_name' => 'Marisol']);
        $librarian = $this->librarianOnDuty();
        $deposit = $this->openDeposit($depositor);

        $this->actingAs($librarian);

        // A non-matching search empties the ledger — the modal must still
        // resolve the deposit by id, not from the filtered list.
        Livewire::test(AdminBagCounter::class)
            ->set('depositSearch', 'zzzz-no-match')
            ->call('openRescue', $deposit->id)
            ->assertSee('Grey backpack')
            ->assertSee('Marisol');
    }

    public function test_deposit_photo_stores_on_local_disk_and_is_served_staff_only(): void
    {
        Storage::fake('local');

        $depositor = User::factory()->create(['role_id' => 1]);
        $student = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        BagSlot::create(['number' => 1, 'is_active' => true]);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($depositor))
            ->call('openDepositModal')
            ->set('formPhoto', UploadedFile::fake()->image('bag.jpg'))
            ->call('submitDeposit')
            ->assertHasNoErrors();

        $deposit = BagDeposit::sole();
        $this->assertNotNull($deposit->photo_path);
        $this->assertStringStartsWith('bag-deposits/', $deposit->photo_path);
        Storage::disk('local')->assertExists($deposit->photo_path);

        // Staff (librarian) can fetch it; a student cannot. The student gets a
        // bare 403 — asserted via the exception because the rendered error
        // page needs the Vite build this worktree lacks.
        $this->get(route('admin.bag-deposits.photo', $deposit))->assertOk();
        $this->actingAs($student);
        $this->withoutExceptionHandling();
        try {
            $this->get(route('admin.bag-deposits.photo', $deposit));
            $this->fail('Student reached the staff-only photo route');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_audit_trail_shows_deposit_claim_and_temp_events(): void
    {
        $depositor = User::factory()->create(['role_id' => 1]);
        $claimant = User::factory()->create(['role_id' => 1]);
        $librarian = $this->librarianOnDuty();
        $deposit = $this->openDeposit($depositor);

        $this->actingAs($librarian);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($depositor))
            ->call('tempAccess', $deposit->id);

        Livewire::test(AdminBagCounter::class)
            ->call('handleScan', $this->makeQr($claimant))
            ->set('claimCodeInput', $deposit->claim_code)
            ->call('lookupClaimCode')
            ->call('claimDeposit', $deposit->id);

        $this->assertSame(
            [BagDepositEvent::TYPE_TEMP_ACCESS, BagDepositEvent::TYPE_CLAIM],
            $deposit->events()->orderBy('id')->pluck('type')->all()
        );
    }
}
