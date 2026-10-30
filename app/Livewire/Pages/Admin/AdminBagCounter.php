<?php

namespace App\Livewire\Pages\Admin;

use App\Models\BagDeposit;
use App\Models\BagDepositEvent;
use App\Models\BagSlot;
use App\Models\User;
use App\Services\BagDepositService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use RuntimeException;

#[Title('Bag Counter')]
class AdminBagCounter extends AdminComponent
{
    use Toast;
    use WithFileUploads;

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

    public $formPhoto = null;

    public ?string $issuedCode = null;

    // Third-party claim: six-digit codes scanned couriers may present
    public string $claimCodeInput = '';

    /** @var array<int> */
    public array $codeEntitledIds = [];

    // Ledger search + staff rescue lookup
    public string $depositSearch = '';

    public bool $rescueModal = false;

    public ?int $rescueDepositId = null;

    public string $rescueNote = '';

    public bool $rescueIdVerified = false;

    // Audit trail modal
    public bool $auditModal = false;

    public ?int $auditDepositId = null;

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
            $this->codeEntitledIds = [];
            $this->claimCodeInput = '';

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
        $this->codeEntitledIds = [];
        $this->claimCodeInput = '';
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
        $this->formPhoto = null;
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
            'formPhoto' => ['nullable', 'image', 'max:5120'],
        ]);

        if (! $this->scannedUserId || ! ($depositor = User::find($this->scannedUserId))) {
            $this->error('Scan a user QR first', 'No scan');

            return;
        }

        // Private disk — never reachable through a public URL.
        $photoPath = $this->formPhoto?->store('bag-deposits', 'local');

        try {
            $deposit = app(BagDepositService::class)->openDeposit(
                $depositor,
                BagSlot::findOrFail($this->formSlot),
                $this->formItemCount,
                trim($this->formDescription),
                auth()->user(),
                $photoPath,
            );
        } catch (RuntimeException $e) {
            if ($photoPath) {
                Storage::disk('local')->delete($photoPath);
            }
            $this->error($e->getMessage(), 'Deposit failed');

            return;
        }

        $this->issuedCode = $deposit->claim_code;
        $this->success("Deposit recorded in slot {$deposit->slot->number}", 'Deposit opened');
    }

    /**
     * Bind the scanned courier to an open deposit via its six-digit claim
     * code — the third-party (delegated) claim path. A wrong code grants
     * nothing.
     */
    public function lookupClaimCode()
    {
        if (! Gate::allows('librarian-or-admin-access')) {
            $this->redirect(route('student.dashboard'));

            return;
        }

        if (! $this->scannedUserId) {
            $this->error('Scan a user QR first', 'No scan');

            return;
        }

        $code = trim($this->claimCodeInput);

        if (! preg_match('/^\d{6}$/', $code)) {
            $this->error('Enter the six-digit claim code', 'Bad code');

            return;
        }

        $deposit = app(BagDepositService::class)->findOpenDepositByCode($code);

        if (! $deposit) {
            $this->error('No open deposit matches that code', 'Bad code');

            return;
        }

        if (! in_array($deposit->id, $this->codeEntitledIds)) {
            $this->codeEntitledIds[] = $deposit->id;
        }

        $this->claimCodeInput = '';
        $this->success('Deposit unlocked by claim code', 'Code accepted');
    }

    /**
     * Claim an open deposit for the scanned courier: owner_scan when they
     * deposited it, claim_code when they presented the deposit's code.
     * Frees the slot.
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

        [$method, $error] = $this->claimAuthorization($deposit);

        if ($error) {
            $this->error($error, 'Wrong courier');

            return;
        }

        try {
            $deposit = app(BagDepositService::class)->claimDeposit($deposit, User::findOrFail($this->scannedUserId), $method, auth()->user());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), 'Claim failed');

            return;
        }

        $this->codeEntitledIds = array_diff($this->codeEntitledIds, [$deposit->id]);

        $label = $deposit->description ?: 'bag';
        $this->success("Slot {$deposit->slot->number} freed — {$label} released", 'Claimed');
    }

    /**
     * Temporary access: open the slot without closing the deposit. Same
     * entitlement as a claim — own scan or the deposit's claim code.
     */
    public function tempAccess(int $depositId)
    {
        if (! Gate::allows('librarian-or-admin-access')) {
            $this->redirect(route('student.dashboard'));

            return;
        }

        $deposit = BagDeposit::find($depositId);

        if (! $deposit || ! $deposit->isOpen()) {
            $this->error('That deposit is no longer open', 'Access failed');

            return;
        }

        [, $error] = $this->claimAuthorization($deposit);

        if ($error) {
            $this->error($error, 'Wrong courier');

            return;
        }

        try {
            app(BagDepositService::class)->tempAccess($deposit, User::findOrFail($this->scannedUserId), auth()->user());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), 'Access failed');

            return;
        }

        $this->success("Slot {$deposit->slot->number} opened temporarily — deposit stays open", 'Temporary access');
    }

    /**
     * The scanned courier may act on their own deposits or on deposits
     * unlocked by a presented claim code.
     *
     * @return array{0: string|null, 1: string|null} method, error message
     */
    private function claimAuthorization(BagDeposit $deposit): array
    {
        if (! $this->scannedUserId) {
            return [null, 'Scan a user QR first'];
        }

        if ($deposit->user_id === $this->scannedUserId) {
            return [BagDeposit::METHOD_OWNER_SCAN, null];
        }

        if (in_array($deposit->id, $this->codeEntitledIds)) {
            return [BagDeposit::METHOD_CLAIM_CODE, null];
        }

        return [null, 'Claims require the depositor\'s own QR or the deposit\'s claim code'];
    }

    public function openRescue(int $depositId)
    {
        $this->rescueDepositId = $depositId;
        $this->rescueNote = '';
        $this->rescueIdVerified = false;
        $this->rescueModal = true;
    }

    /**
     * Rescue release: no QR, no code — staff verify the claimant's physical
     * ID and write a note. Flagged as rescue_lookup on the audit event.
     */
    public function submitRescue()
    {
        if (! Gate::allows('librarian-or-admin-access')) {
            $this->redirect(route('student.dashboard'));

            return;
        }

        $this->validate([
            'rescueNote' => ['required', 'string', 'max:500'],
            'rescueIdVerified' => ['accepted'],
        ]);

        $deposit = BagDeposit::find($this->rescueDepositId);

        if (! $deposit || ! $deposit->isOpen()) {
            $this->error('That deposit is no longer open', 'Rescue failed');

            return;
        }

        try {
            $deposit = app(BagDepositService::class)->rescueClaim($deposit, $this->rescueNote, $this->rescueIdVerified, auth()->user());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), 'Rescue failed');

            return;
        }

        $this->rescueModal = false;
        $this->success("Slot {$deposit->slot->number} freed — rescue release logged", 'Rescue claim');
    }

    public function openAudit(int $depositId)
    {
        $this->auditDepositId = $depositId;
        $this->auditModal = true;
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
        $search = trim($this->depositSearch);

        return BagDeposit::with('user', 'slot')
            ->open()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhereHas('slot', fn ($s) => $s->where('number', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn ($u) => $u->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->get();
    }

    /**
     * Deposits the scanned courier may act on: their own plus any unlocked
     * by a claim code they presented.
     */
    #[Computed]
    public function claimableDeposits()
    {
        if (! $this->scannedUserId) {
            return collect();
        }

        return BagDeposit::with('slot', 'user')
            ->where(fn ($q) => $q->where('user_id', $this->scannedUserId)
                ->orWhereIn('id', $this->codeEntitledIds ?: [0]))
            ->open()
            ->latest()
            ->get();
    }

    #[Computed]
    public function auditEvents()
    {
        if (! $this->auditDepositId) {
            return collect();
        }

        return BagDepositEvent::with('actor', 'scannedByLibrarian.user', 'scannedByAdmin')
            ->where('bag_deposit_id', $this->auditDepositId)
            ->oldest()
            ->get();
    }

    #[Computed]
    public function auditDeposit()
    {
        return $this->auditDepositId ? BagDeposit::with('user', 'slot')->find($this->auditDepositId) : null;
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
