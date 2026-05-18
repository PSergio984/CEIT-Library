<?php

namespace App\Livewire\Pages\Student;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Traits\CreatesQrCanonicalMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

#[Layout('components.layouts.app')]
class ShowAcademicPaper extends Component
{
    use CreatesQrCanonicalMessage;
    use WithPagination;

    // QR code generation settings
    private const QR_SVG_SIZE = 400;

    private const QR_MARGIN = 8;

    private const QR_ERROR_CORRECTION = 'M';

    public array $sortBy = ['column' => 'id', 'direction' => 'asc'];

    public int $perPage = 2;

    public bool $isModalOpen = false;

    public bool $isQrModalOpen = false;

    public ?string $qrCodeDataUri = null;

    public ?int $selectedInventoryId = null;

    public ?string $selectedPaperTitle = null;

    // For return QR modal
    public bool $isReturnQrModalOpen = false;

    public ?string $returnQrCodeDataUri = null;

    public ?string $returnQrPaperTitle = null;

    public ?int $returnQrTransactionId = null;

    public ?int $returnQrInventoryId = null;

    public array $headers = [
        ['key' => 'id', 'label' => 'Copy Id'],
        ['key' => 'status', 'label' => 'Availability'],
        ['key' => 'action', 'label' => 'Action'],
    ];

    public ?AcademicPaper $academicPaper = null;

    public function mount(?AcademicPaper $academicPaper = null)
    {
        if ($academicPaper) {
            $this->academicPaper = $academicPaper->load('authors', 'copies');
            $this->isModalOpen = true;
        }
    }

    public function openModal(AcademicPaper $academicPaper): void
    {
        $this->academicPaper = $academicPaper->load('authors', 'copies');
        $this->isModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->academicPaper = null;
    }

    public function updatedIsModalOpen(): void
    {
        if (! $this->isModalOpen) {
            $this->academicPaper = null;
        }
    }

    #[Computed]
    public function rows(): array
    {
        if (! $this->academicPaper) {
            return [];
        }

        $copies = $this->academicPaper->copies()
            ->orderBy(...array_values($this->sortBy))
            ->get();

        return $copies->map(function ($copy) {
            return [
                'id' => $copy->id,
                'status' => $copy->status,
            ];
        })->toArray();
    }

    public function requestQr($inventoryId): void
    {
        $user = Auth::user();
        if (! $user) {
            $this->dispatch('toast', type: 'error', message: 'You must be logged in to request a borrow QR.');

            return;
        }

        // Find the inventory item and verify it's available
        $inventory = Inventory::with('academicPaper')->find($inventoryId);

        if (! $inventory) {
            $this->dispatch('toast', type: 'error', message: 'Copy not found.');

            return;
        }

        if ($inventory->status !== 'Available') {
            $this->dispatch('toast', type: 'error', message: 'This copy is not available for borrowing.');

            return;
        }

        // Build the borrow data payload
        $borrowData = [
            'inventory_id' => $inventory->id,
            'paper_id' => $inventory->academic_paper_id,
            'requested_by' => $user->id,
        ];

        // Create encrypted QR message using trait method
        $qrContent = $this->createEncryptedQrMessage($borrowData);

        // Generate QR code as SVG
        $svg = QrCode::size(self::QR_SVG_SIZE)
            ->margin(self::QR_MARGIN)
            ->errorCorrection(self::QR_ERROR_CORRECTION)
            ->generate($qrContent);

        $this->qrCodeDataUri = 'data:image/svg+xml;base64,'.base64_encode($svg);
        $this->selectedInventoryId = $inventory->id;
        $this->selectedPaperTitle = $inventory->academicPaper?->title ?? 'Unknown Paper';
        $this->isQrModalOpen = true;
    }

    public function closeQrModal(): void
    {
        $this->isQrModalOpen = false;
        $this->qrCodeDataUri = null;
        $this->selectedInventoryId = null;
        $this->selectedPaperTitle = null;
    }

    /**
     * Get the user's active borrow transactions for the current paper's copies
     * Returns an array of inventory_id => transaction for quick lookup
     */
    #[Computed]
    public function userActiveBorrows(): array
    {
        $user = Auth::user();
        if (! $user || ! $this->academicPaper) {
            return [];
        }

        $inventoryIds = $this->academicPaper->copies->pluck('id')->toArray();

        return BorrowTransaction::where('user_id', $user->id)
            ->whereIn('inventory_id', $inventoryIds)
            ->whereIn('status', ['started', 'overdue'])
            ->whereNull('time_out')
            ->get()
            ->keyBy('inventory_id')
            ->toArray();
    }

    /**
     * Generate return QR code for a copy the user has borrowed
     */
    public function showReturnQr(int $inventoryId): void
    {
        $user = Auth::user();
        if (! $user) {
            $this->dispatch('toast', type: 'error', message: 'You must be logged in.');

            return;
        }

        // Find the user's active transaction for this inventory
        $transaction = BorrowTransaction::where('user_id', $user->id)
            ->where('inventory_id', $inventoryId)
            ->whereIn('status', ['started', 'overdue'])
            ->whereNull('time_out')
            ->with('inventory.academicPaper')
            ->first();

        if (! $transaction) {
            $this->dispatch('toast', type: 'error', message: 'No active borrow found for this copy.');

            return;
        }

        // Build the return QR payload (same format as borrow, admin will detect it's for return)
        $returnData = [
            'inventory_id' => $transaction->inventory_id,
            'paper_id' => $transaction->inventory->academic_paper_id ?? $transaction->academic_paper_id,
            'requested_by' => $user->id,
        ];

        // Create encrypted QR message using trait method
        $qrContent = $this->createEncryptedQrMessage($returnData);

        // Generate QR code as SVG
        $svg = QrCode::size(self::QR_SVG_SIZE)
            ->margin(self::QR_MARGIN)
            ->errorCorrection(self::QR_ERROR_CORRECTION)
            ->generate($qrContent);

        $this->returnQrCodeDataUri = 'data:image/svg+xml;base64,'.base64_encode($svg);
        $this->returnQrPaperTitle = $transaction->inventory->academicPaper?->title ?? 'Unknown Paper';
        $this->returnQrTransactionId = $transaction->id;
        $this->returnQrInventoryId = $transaction->inventory_id;
        $this->isReturnQrModalOpen = true;
    }

    public function closeReturnQrModal(): void
    {
        $this->isReturnQrModalOpen = false;
        $this->returnQrCodeDataUri = null;
        $this->returnQrPaperTitle = null;
        $this->returnQrTransactionId = null;
        $this->returnQrInventoryId = null;
    }

    /**
     * Get download URL for the borrow QR code
     */
    #[Computed]
    public function borrowQrDownloadUrl(): ?string
    {
        if (! $this->selectedInventoryId) {
            return null;
        }

        return route('qr-code.download', [
            'inventoryId' => $this->selectedInventoryId,
        ]);
    }

    /**
     * Get download URL for the return QR code
     */
    #[Computed]
    public function returnQrDownloadUrl(): ?string
    {
        if (! $this->returnQrInventoryId) {
            return null;
        }

        return route('qr-code.download', [
            'inventoryId' => $this->returnQrInventoryId,
        ]);
    }

    public function render()
    {
        return view('livewire.pages.student.show-academic-paper');
    }
}
