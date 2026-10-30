<?php

namespace App\Livewire\Pages\Admin;

use App\Models\BagDeposit;
use App\Models\BagSlot;
use App\Models\User;
use App\Services\BagDepositService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Mary\Traits\Toast;
use RuntimeException;

#[Title('Bag Counter')]
class AdminBagCounter extends AdminComponent
{
    use Toast;

    public ?int $scannedUserId = null;

    public ?string $scannedUserName = null;

    // QR scanner modal
    public bool $showQrModal = false;

    public bool $isProcessingQr = false;

    // Deposit form modal
    public bool $depositModal = false;

    public ?int $formSlot = null;

    public int $formItemCount = 1;

    public string $formDescription = '';

    public ?string $issuedCode = null;

    public function mount()
    {
        $this->authorizeAccess();
    }

    public function openScanner()
    {
        $this->showQrModal = true;
        $this->dispatch('qr-modal-opened');
    }

    public function closeQrModal()
    {
        $this->showQrModal = false;
        $this->isProcessingQr = false;
        $this->dispatch('qr-modal-closed');
    }

    public function handleScan(string $data)
    {
        if (! Gate::allows('librarian-or-admin-access')) {
            $this->redirect(route('student.dashboard'));

            return;
        }

        $this->isProcessingQr = true;
        $service = app(BagDepositService::class);

        try {
            $data = trim($data);

            if (empty($data)) {
                $this->error('Invalid QR code: Empty data', 'Scan Error');

                return;
            }

            $user = $service->resolveScannedUser($data);

            if ($user === BagDepositService::INVALID) {
                $this->error('Invalid QR code. This could be due to tampering, incorrect format, or a replayed scan.', 'Invalid QR Code');

                return;
            }

            $this->scannedUserId = $user->id;
            $this->scannedUserName = $user->first_name.' '.$user->last_name;
            $this->issuedCode = null;

            $this->success("{$this->scannedUserName} verified by QR", 'Courier scanned');
            $this->closeQrModal();
        } catch (\Exception $e) {
            Log::error('Bag counter QR scan error: '.$e->getMessage(), ['exception' => $e]);
            $this->error('An error occurred while processing the QR code', 'System Error');
        } finally {
            $this->isProcessingQr = false;
        }
    }

    public function clearScan()
    {
        $this->scannedUserId = null;
        $this->scannedUserName = null;
        $this->issuedCode = null;
    }

    public function openDepositModal()
    {
        if (! $this->scannedUserId) {
            $this->error('Scan a user QR first', 'No scan');

            return;
        }

        $this->issuedCode = null;
        $this->formSlot = BagSlot::available()->orderBy('number')->value('id');
        $this->formItemCount = 1;
        $this->formDescription = '';
        $this->depositModal = true;
    }

    public function submitDeposit()
    {
        if (! Gate::allows('librarian-or-admin-access')) {
            $this->redirect(route('student.dashboard'));

            return;
        }

        $this->validate([
            'formSlot' => ['required', 'integer', 'exists:bag_slots,id'],
            'formItemCount' => ['required', 'integer', 'min:1', 'max:99'],
            'formDescription' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $this->scannedUserId || ! ($depositor = User::find($this->scannedUserId))) {
            $this->error('Scan a user QR first', 'No scan');

            return;
        }

        try {
            $deposit = app(BagDepositService::class)->openDeposit(
                $depositor,
                BagSlot::findOrFail($this->formSlot),
                $this->formItemCount,
                trim($this->formDescription),
                auth()->user(),
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), 'Deposit failed');

            return;
        }

        $this->issuedCode = $deposit->claim_code;
        $this->success("Deposit recorded in slot {$deposit->slot->number}", 'Deposit opened');
    }

    /**
     * Owner-scan claim: the scanned courier claims one of their own
     * open deposits. Frees the slot.
     */
    public function claimDeposit(int $depositId)
    {
        if (! Gate::allows('librarian-or-admin-access')) {
            $this->redirect(route('student.dashboard'));

            return;
        }

        $deposit = BagDeposit::find($depositId);

        if (! $deposit || ! $deposit->isOpen()) {
            $this->error('That deposit is no longer open', 'Claim failed');

            return;
        }

        if ($deposit->user_id !== $this->scannedUserId) {
            $this->error('Owner-scan claims require the depositor\'s own QR — scan theirs, or use the code flow', 'Wrong courier');

            return;
        }

        try {
            $deposit = app(BagDepositService::class)->claimDeposit($deposit, User::findOrFail($this->scannedUserId), BagDeposit::METHOD_OWNER_SCAN, auth()->user());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), 'Claim failed');

            return;
        }

        $label = $deposit->description ?: 'bag';
        $this->success("Slot {$deposit->slot->number} freed — {$label} released", 'Claimed');
    }

    public function toggleSlotService(int $slotId)
    {
        if (! Gate::allows('librarian-or-admin-access')) {
            $this->redirect(route('student.dashboard'));

            return;
        }

        $slot = BagSlot::find($slotId);

        if (! $slot) {
            return;
        }

        if ($slot->openDeposit()->exists()) {
            $this->error('An occupied slot cannot be taken out of service', 'Slot occupied');

            return;
        }

        $slot->update(['is_active' => ! $slot->is_active]);

        $this->success(
            $slot->is_active ? "Slot {$slot->number} back in service" : "Slot {$slot->number} marked out of service",
            'Slot updated'
        );
    }

    #[Computed]
    public function bagSlots()
    {
        return BagSlot::with('openDeposit.user')->orderBy('number')->get();
    }

    #[Computed]
    public function openDeposits()
    {
        return BagDeposit::with('user', 'slot')->open()->latest()->get();
    }

    #[Computed]
    public function scannedUserDeposits()
    {
        if (! $this->scannedUserId) {
            return collect();
        }

        return BagDeposit::with('slot')
            ->where('user_id', $this->scannedUserId)
            ->open()
            ->latest()
            ->get();
    }

    #[Computed]
    public function availableSlots()
    {
        return BagSlot::available()->orderBy('number')->get();
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-bag-counter');
    }
}
