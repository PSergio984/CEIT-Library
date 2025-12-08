<?php

namespace App\Livewire\Pages\Student;

use App\Models\BorrowTransaction;
use App\Traits\CreatesQrCanonicalMessage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

#[Title('Transaction History')]
class Transaction extends Component
{
    use CreatesQrCanonicalMessage, Toast, WithPagination;

    // QR code generation settings
    private const QR_SVG_SIZE = 400;
    private const QR_MARGIN = 8;
    private const QR_ERROR_CORRECTION = 'M';

    public string $search = '';

    public string $statusFilter = '';

    public string $paperTypeFilter = '';

    public string $selectedDate = '';

    public int $perPage = 10;

    // Return QR modal properties
    public bool $isReturnQrModalOpen = false;

    public ?string $returnQrCodeDataUri = null;

    public ?string $returnQrPaperTitle = null;

    public ?int $returnQrTransactionId = null;

    /**
     * Get distinct paper types for filter dropdown
     * Cached per-request to avoid multiple queries
     */
    #[Computed]
    public function paperTypes(): array
    {
        return BorrowTransaction::query()
            ->where('user_id', Auth::id())
            ->join('inventories', 'borrow_transactions.inventory_id', '=', 'inventories.id')
            ->join('academic_papers', 'inventories.academic_paper_id', '=', 'academic_papers.id')
            ->distinct()
            ->orderBy('academic_papers.paper_type')
            ->pluck('academic_papers.paper_type')
            ->filter()
            ->values()
            ->toArray();
    }

    public function getTransactionsQuery()
    {
        $query = BorrowTransaction::with([
            'user',
            'inventory.academicPaper',
        ])
            ->where('user_id', Auth::id());

        if ($this->search) {
            $query->whereHas('inventory.academicPaper', function ($q) {
                $q->search($this->search);
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->paperTypeFilter) {
            $query->whereHas('inventory.academicPaper', function ($q) {
                $q->where('paper_type', $this->paperTypeFilter);
            });
        }

        if ($this->selectedDate) {
            $query->whereDate('time_in', $this->selectedDate);
        }

        $query->orderBy('time_in', 'desc');

        return $query;
    }

    /**
     * Get paginated transactions with computed properties
     * ✅ Uses computed property (not stored in public state)
     * ✅ Eager loads relationships (prevents N+1 queries)
     * ✅ Auto-calculates overdue status for display
     */
    #[Computed]
    public function transactions()
    {
        return $this->getTransactionsQuery()
            ->paginate($this->perPage)
            ->through(function ($transaction) {
                // Auto-calculate actual status (check if overdue)
                $displayStatus = $transaction->status;
                $isOverdue = false;
                $timeRemaining = null;
                $overdueDuration = null;

                if ($transaction->status === 'started') {
                    if ($transaction->isOverdue()) {
                        // Transaction is technically overdue but hasn't been updated yet
                        $displayStatus = 'overdue';
                        $isOverdue = true;
                        $overdueDuration = $transaction->overdue_duration;
                    } else {
                        // Still active, calculate time remaining
                        $timeRemaining = $transaction->time_remaining;
                    }
                } elseif ($transaction->status === 'overdue') {
                    $isOverdue = true;
                    $overdueDuration = $transaction->overdue_duration;
                }

                return [
                    'id' => $transaction->id,
                    'title' => $transaction->inventory->academicPaper->title ?? 'N/A',
                    'paper_type' => $transaction->inventory->academicPaper->paper_type ?? 'N/A',
                    'department' => $transaction->inventory->academicPaper->department ?? 'N/A',
                    'time_in' => $transaction->time_in,
                    'time_out' => $transaction->time_out,
                    'status' => $displayStatus, // Display overdue status even if not updated yet
                    'actual_status' => $transaction->status, // Store actual DB status
                    'is_overdue' => $isOverdue,
                    'time_remaining' => $timeRemaining,
                    'overdue_duration' => $overdueDuration,
                    'duration' => $this->formatDuration($transaction),
                    'notes' => $transaction->notes ?? 'No notes',
                    'expires_at' => $transaction->expires_at,
                    'academic_paper' => $transaction->inventory->academicPaper,
                    'inventory' => $transaction->inventory,
                    'copy_number' => $transaction->inventory->copy_number ?? 1,
                ];
            });
    }

    private function formatDuration($transaction): string
    {
        if (! $transaction->time_in) {
            return 'N/A';
        }

        $endTime = $transaction->time_out ?? now();
        $diffInSeconds = $transaction->time_in->diffInSeconds($endTime);
        $hours = intdiv($diffInSeconds, 3600);
        $minutes = intdiv($diffInSeconds % 3600, 60);
        $seconds = $diffInSeconds % 60;
        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours . 'h';
        }
        if ($minutes > 0 || $hours > 0) {
            $parts[] = $minutes . 'm';
        }
        if ($seconds > 0 && $hours == 0) {
            $parts[] = $seconds . 's';
        }
        if (empty($parts)) {
            $parts[] = '0s';
        }

        return implode(' ', $parts);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'paperTypeFilter', 'selectedDate']);
        $this->resetPage();
    }

    public function viewTransaction($transactionId): void
    {
        $transaction = BorrowTransaction::with(['inventory.academicPaper'])
            ->where('user_id', Auth::id())
            ->find($transactionId);

        if ($transaction) {
            $this->dispatch('transaction-selected', $transaction->toArray());
        }
    }

    public function extendTransaction($transactionId): void
    {
        $transaction = BorrowTransaction::where('user_id', Auth::id())
            ->where('status', 'started')
            ->find($transactionId);

        if ($transaction && $transaction->expires_at > now()) {
            $transaction->update([
                'expires_at' => $transaction->expires_at->addDays(7),
            ]);

            $this->dispatch('transaction-extended', [
                'message' => 'Transaction extended by 7 days successfully!',
            ]);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPaperTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedDate(): void
    {
        $this->resetPage();
    }

    /**
     * Generate a QR code for returning a book the user has currently borrowed
     * This allows users who didn't save their original QR to still return the book
     */
    public function generateReturnQr(int $transactionId): void
    {
        $user = Auth::user();
        if (! $user) {
            $this->error('You must be logged in.');

            return;
        }

        // Find the transaction - must belong to this user and be active
        $transaction = BorrowTransaction::with('inventory.academicPaper')
            ->where('user_id', $user->id)
            ->whereIn('status', ['started', 'overdue'])
            ->whereNull('time_out')
            ->find($transactionId);

        if (! $transaction) {
            $this->error('Active transaction not found or you are not the borrower.');

            return;
        }

        $inventory = $transaction->inventory;
        if (! $inventory) {
            $this->error('Inventory not found.');

            return;
        }

        // Build the borrow data payload (same format as original borrow QR)
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

        $this->returnQrCodeDataUri = 'data:image/svg+xml;base64,' . base64_encode($svg);
        $this->returnQrPaperTitle = $inventory->academicPaper?->title ?? 'Unknown Paper';
        $this->returnQrTransactionId = $transaction->id;
        $this->isReturnQrModalOpen = true;
    }

    public function closeReturnQrModal(): void
    {
        $this->isReturnQrModalOpen = false;
        $this->returnQrCodeDataUri = null;
        $this->returnQrPaperTitle = null;
        $this->returnQrTransactionId = null;
    }

    public function render()
    {
        return view('livewire.pages.student.transaction');
    }
}
