<?php

namespace App\Livewire\Pages\Admin;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Livewire\Attributes\Title;

#[Title('Borrow Logs')]
class AdminBorrowTransactions extends AdminComponent
{
    use WithPagination, Toast;

    public $perPage = 20;
    public $search = '';
    public $paperTypeFilter = '';
    public $statusFilter = '';
    public $selectedDate = '';

    // Edit modal properties
    public $showEditModal = false;
    public $editingTransactionId = null;
    public $editStatus = '';
    public $editTimeOut = '';

    // QR Scanner modal properties
    public $showQrModal = false;
    public $scannedQrData = '';
    public $qrUploadedFile = null;

    // MaryUI table headers - Optimized for responsive display
    public array $headers = [
        ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
        ['key' => 'user_name', 'label' => 'Student Name', 'sortable' => true, 'class' => 'min-w-16'],
        ['key' => 'email', 'label' => 'Email', 'class' => 'min-w-32'],
        ['key' => 'title', 'label' => 'Title Borrowed', 'sortable' => true, 'class' => 'min-w-48'],
        ['key' => 'paper_type', 'label' => 'Type', 'sortable' => true, 'class' => 'w-20'],
        ['key' => 'time_in', 'label' => 'Time In', 'sortable' => true, 'class' => 'w-28'],
        ['key' => 'time_out', 'label' => 'Time Out', 'sortable' => true, 'class' => 'w-28'],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'class' => 'w-20'],
        ['key' => 'notes', 'label' => 'Notes', 'class' => 'min-w-40'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'w-24', 'sortable' => false],
    ];

    // Sort configuration for MaryUI
    public array $sortBy = ['column' => 'time_in', 'direction' => 'desc'];

    protected function getTransactionsQuery()
    {
        return BorrowTransaction::with([
            'user',
            'inventory.academicPaper'
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
                    'user_name' => trim(($transaction->user?->first_name ?? '') . ' ' . ($transaction->user?->last_name ?? '')) ?: 'N/A',
                    'email' => $transaction->user?->email ?? 'N/A',
                    'user' => $transaction->user,
                    'title' => $transaction->inventory?->academicPaper?->title ?? 'No Title',
                    'paper_type' => $transaction->inventory?->academicPaper?->paper_type ?? 'N/A',
                    'time_in' => $transaction->time_in,
                    'time_out' => $transaction->time_out,
                    'status' => $transaction->status ?? 'active',
                    'notes' => $transaction->notes ?? 'N/A',
                    'original' => $transaction, // Keep original for actions
                ];
            });
    }

    public function getPaperTypesProperty()
    {
        return AcademicPaper::distinct()->pluck('paper_type')->filter();
    }

    // Edit modal methods
    public function openEditModal($transactionId)
    {
        $transaction = BorrowTransaction::find($transactionId);

        if (!$transaction) {
            $this->error("Transaction not found!");
            return;
        }

        $this->editingTransactionId = $transactionId;
        $this->editStatus = $transaction->status ?? 'started';
        $this->editTimeOut = $transaction->time_out ? $transaction->time_out->format('Y-m-d\TH:i') : '';
        $this->showEditModal = true;
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingTransactionId = null;
        $this->editStatus = '';
        $this->editTimeOut = '';
    }

    public function saveTransaction()
    {
        $this->validate([
            'editStatus' => 'required|in:started,completed',
            'editTimeOut' => 'nullable|date',
        ]);

        $transaction = BorrowTransaction::find($this->editingTransactionId);

        if (!$transaction) {
            $this->error("Transaction not found!");
            return;
        }

        // If status is completed, time_out is required
        if ($this->editStatus === 'completed' && empty($this->editTimeOut)) {
            $this->error("Time Out is required when status is completed!");
            return;
        }

        // Update transaction
        $transaction->update([
            'status' => $this->editStatus,
            'time_out' => $this->editTimeOut ? \Carbon\Carbon::parse($this->editTimeOut) : null,
        ]);

        $this->success("Transaction #$this->editingTransactionId updated successfully!");
        $this->closeEditModal();
    }

    // QR Scanner methods
    public function openQrModal()
    {
        $this->showQrModal = true;
        $this->scannedQrData = '';
        $this->qrUploadedFile = null;
        $this->dispatch('qr-modal-opened');
    }

    public function closeQrModal()
    {
        $this->showQrModal = false;
        $this->scannedQrData = '';
        $this->qrUploadedFile = null;
        $this->dispatch('qr-modal-closed');
    }

    public function processScannedQr($qrData)
    {
        $this->scannedQrData = $qrData;

        // Try to find transaction by QR data
        // Assuming QR contains transaction ID or some identifier
        $transaction = BorrowTransaction::find($qrData);

        if ($transaction) {
            $this->openEditModal($transaction->id);
            $this->closeQrModal();
            $this->success("Transaction found! Opening editor...");
        } else {
            $this->error("No transaction found with this QR code.");
        }
    }

    // Action methods
    public function viewTransaction($id)
    {
        $this->info("Viewing transaction #$id");
        // Add your view logic here
    }

    public function markCompleted($id)
    {
        $transaction = BorrowTransaction::find($id);
        if ($transaction && $transaction->status !== 'completed') {
            $transaction->update([
                'status' => 'completed',
                'time_out' => now()
            ]);
            $this->success("Transaction #$id marked as completed!");
        }
    }

    public function extendTransaction($id)
    {
        $transaction = BorrowTransaction::find($id);
        if (!$transaction) {
            $this->error("Transaction #$id not found!");
            return;
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

    public function render()
    {
        return view('livewire.pages.admin.admin-borrow-transactions');
    }
}
