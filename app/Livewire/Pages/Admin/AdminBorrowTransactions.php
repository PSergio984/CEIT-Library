<?php

namespace App\Livewire\Pages\Admin;

use App\Livewire\Forms\BorrowTransactionForm;
use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\Notification;
use App\Models\User;
use App\Rules\NoHtmlTags;
use App\Rules\SafeText;
use App\Services\BorrowService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Borrow Logs')]
#[Lazy]
class AdminBorrowTransactions extends AdminComponent
{
    use Toast, WithPagination;

    public BorrowTransactionForm $form;

    public int $perPage = 10;

    public function mount()
    {
        $this->authorizeAccess();
    }

    #[Validate(['nullable', 'string', 'max:100', new NoHtmlTags, new SafeText])]
    public $search = '';

    #[Validate(['nullable', 'string', 'max:50', new NoHtmlTags, new SafeText])]
    public $paperTypeFilter = '';

    #[Validate(['nullable', 'string', 'max:20', 'in:started,completed'])]
    public $statusFilter = '';

    #[Validate(['nullable', 'date'])]
    public $selectedDate = '';

    // Edit modal properties
    public $showEditModal = false;

    // QR Scanner modal properties
    public $showQrModal = false;

    public $scannedQrData = '';

    public $isProcessingQr = false;

    // Borrow confirmation modal properties
    public $showConfirmBorrowModal = false;

    public $pendingBorrowData = [];

    public $borrowNotes = '';

    // MaryUI table headers - Optimized for responsive display
    public array $headers = [
        ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
        ['key' => 'user_name', 'label' => 'Student Name', 'sortable' => true, 'class' => 'min-w-32'],

        ['key' => 'title', 'label' => 'Title Borrowed', 'sortable' => true, 'class' => 'min-w-40'],
        ['key' => 'paper_type', 'label' => 'Type', 'sortable' => true, 'class' => 'w-20'],
        ['key' => 'time_in', 'label' => 'Time In', 'sortable' => true, 'class' => 'w-28'],
        ['key' => 'time_out', 'label' => 'Time Out', 'sortable' => true, 'class' => 'w-28'],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'class' => 'w-24'],
        ['key' => 'notes', 'label' => 'Notes', 'class' => 'w-28'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-20 text-center', 'sortable' => false],
    ];

    // Sort configuration for MaryUI
    public array $sortBy = ['column' => 'time_in', 'direction' => 'desc'];

    public function exportPdf()
    {
        $this->authorize('manage-borrow-logs');

        $transactions = $this->getTransactionsQuery()
            ->orderBy($this->sortBy['column'] ?? 'time_in', $this->sortBy['direction'] ?? 'desc')
            ->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.borrow-transactions', [
            'transactions' => $transactions,
            'filters' => [
                'search' => $this->search,
                'paperType' => $this->paperTypeFilter,
                'status' => $this->statusFilter,
                'date' => $this->selectedDate,
            ],
            'generatedAt' => now()->format('M d, Y h:i A')
        ])->setPaper('a4', 'landscape');

        $filename = 'borrow-transactions-' . now()->format('Y-m-d') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, $filename);
    }

    // Check if user can edit transactions (admin only)
    public function getCanEditProperty(): bool
    {
        return Gate::allows('manage-borrow-logs');
    }

    protected function getTransactionsQuery()
    {
        return BorrowTransaction::with([
            'user',
            'inventory.academicPaper',
        ])
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->whereHas('user', function ($q) {
                        $q->where('first_name', 'like', "%{$this->search}%")
                            ->orWhere('last_name', 'like', "%{$this->search}%")
                            ->orWhere('email', 'like', "%{$this->search}%");
                    })
                        ->orWhereHas('inventory.academicPaper', function ($q) {
                            $q->where('title', 'like', "%{$this->search}%");
                        })
                        ->orWhere('notes', 'like', "%{$this->search}%");
                });
            })
            ->when($this->paperTypeFilter, function ($query) {
                $query->whereHas('inventory.academicPaper', function ($q) {
                    $q->where('paper_type', $this->paperTypeFilter);
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->selectedDate, function ($query) {
                $query->whereDate('time_in', $this->selectedDate);
            });
    }

    public function getTransactionsProperty()
    {
        $query = $this->getTransactionsQuery();

        // Apply sorting based on MaryUI sortBy
        if (isset($this->sortBy['column']) && isset($this->sortBy['direction'])) {
            $column = $this->sortBy['column'];
            $direction = $this->sortBy['direction'];

            switch ($column) {
                case 'user_name':
                    $query->join('users', 'borrow_transactions.user_id', '=', 'users.id')
                        ->orderBy('users.first_name', $direction)
                        ->select('borrow_transactions.*');
                    break;
                case 'title':
                    $query->join('inventories', 'borrow_transactions.inventory_id', '=', 'inventories.id')
                        ->join('academic_papers', 'inventories.academic_paper_id', '=', 'academic_papers.id')
                        ->orderBy('academic_papers.title', $direction)
                        ->select('borrow_transactions.*');
                    break;
                case 'paper_type':
                    $query->join('inventories', 'borrow_transactions.inventory_id', '=', 'inventories.id')
                        ->join('academic_papers', 'inventories.academic_paper_id', '=', 'academic_papers.id')
                        ->orderBy('academic_papers.paper_type', $direction)
                        ->select('borrow_transactions.*');
                    break;
                default:
                    $query->orderBy($column, $direction);
            }
        } else {
            $query->orderBy('time_in', 'desc');
        }

        return $query->paginate($this->perPage)
            ->through(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'user_name' => trim(($transaction->user?->first_name ?? '').' '.($transaction->user?->last_name ?? '')) ?: 'N/A',
                    'user' => $transaction->user,
                    'title' => $transaction->inventory?->academicPaper?->title ?? 'No Title',
                    'paper_type' => $transaction->inventory?->academicPaper?->paper_type ?? 'N/A',
                    'time_in' => $transaction->time_in,
                    'time_out' => $transaction->time_out,
                    'status' => $transaction->status ?? 'active',
                    'notes' => $transaction->notes ?? 'N/A',
                    'original' => $transaction,
                ];
            });
    }

    #[\Livewire\Attributes\Computed]
    public function getPaperTypesProperty()
    {
        return Cache::remember('academic_paper_types', 3600, function () {
            return AcademicPaper::distinct()->pluck('paper_type')->filter();
        });
    }

    #[\Livewire\Attributes\Computed]
    public function quickStats()
    {
        return [
            'active' => BorrowTransaction::where('status', 'started')->count(),
            'overdue' => BorrowTransaction::where('status', 'overdue')->count(),
            'today' => BorrowTransaction::whereDate('time_in', today())->count(),
        ];
    }

    // Edit modal methods
    public function openEditModal($transactionId)
    {
        $transaction = BorrowTransaction::find($transactionId);

        if (! $transaction) {
            $this->error('Transaction not found!');

            return;
        }

        $this->form->setTransaction($transaction);
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->form->reset();
    }

    public function saveTransaction()
    {
        $this->authorize('manage-borrow-logs');

        $this->form->update();

        $this->success('Transaction updated successfully!');
        $this->closeEditModal();
    }

    // QR Scanner methods
    public function openQrModal()
    {
        $this->showQrModal = true;
        $this->scannedQrData = '';
        $this->isProcessingQr = false;
        $this->dispatch('qr-modal-opened');
    }

    public function closeQrModal()
    {
        $this->showQrModal = false;
        $this->scannedQrData = '';
        $this->isProcessingQr = false;
        $this->dispatch('qr-modal-closed');
    }

    public function processScannedQr($qrData, BorrowService $borrowService)
    {
        $this->authorize('manage-borrow-logs');

        \Log::info('=== QR Processing Started (Component) ===');

        $this->isProcessingQr = true;

        try {
            $this->scannedQrData = $qrData;

            $result = $borrowService->processScannedQr($qrData);

            if (! $result['success']) {
                $this->error($result['message']);
                $this->isProcessingQr = false;

                return ['found' => false];
            }

            if ($result['action'] === 'returned') {
                $this->isProcessingQr = false;
                $this->success($result['message']);

                return ['found' => true, 'action' => 'returned'];
            }

            if ($result['action'] === 'borrow_prepared') {
                $this->pendingBorrowData = $result['data'];

                // Close QR modal and open confirmation modal
                $this->closeQrModal();
                $this->showConfirmBorrowModal = true;
                $this->borrowNotes = '';

                return ['found' => true, 'action' => 'borrow_prepared'];
            }

            return ['found' => false];
        } catch (\Exception $e) {
            \Log::error('QR Processing Exception (Component):', [
                'message' => $e->getMessage(),
            ]);
            $this->error('Error processing QR code: '.$e->getMessage());
            $this->isProcessingQr = false;

            return ['found' => false];
        } finally {
            $this->isProcessingQr = false;
        }
    }

    public function closeConfirmBorrowModal()
    {
        $this->showConfirmBorrowModal = false;
        $this->pendingBorrowData = [];
        $this->borrowNotes = '';
    }

    public function confirmBorrow(BorrowService $borrowService)
    {
        $this->authorize('manage-borrow-logs');

        if (empty($this->pendingBorrowData)) {
            $this->error('No pending borrow request!');

            return;
        }

        $result = $borrowService->confirmBorrow($this->pendingBorrowData, $this->borrowNotes);

        if ($result['success']) {
            $this->success($result['message']);
            $this->closeConfirmBorrowModal();
            $this->reset(['borrowNotes', 'pendingBorrowData']);
        } else {
            $this->error($result['message']);
        }
    }

    // Filter methods
    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingSelectedDate()
    {
        $this->resetPage();
    }

    public function updatingPaperTypeFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->paperTypeFilter = '';
        $this->selectedDate = '';
        $this->statusFilter = '';
        $this->sortBy = ['column' => 'time_in', 'direction' => 'desc'];
        $this->resetPage();
    }

    /**
     * Placeholder shown while lazy loading the component
     */
    public function placeholder()
    {
        return view('components.loading-placeholder', [
            'message' => 'Loading borrow transactions...',
            'subtext' => 'Please wait while we fetch the transaction data',
        ]);
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-borrow-transactions');
    }
}
