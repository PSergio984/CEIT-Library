<?php

namespace App\Livewire\Pages\Admin;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class AdminBorrowTransactions extends AdminComponent
{
    use WithPagination, Toast;

    public $perPage = 20;
    public $search = '';
    public $paperTypeFilter = '';
    public $statusFilter = '';
    public $selectedDate = '';

    // MaryUI table headers - Optimized for responsive display
    public array $headers = [
        ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
        ['key' => 'user_name', 'label' => 'Student Name', 'sortable' => true, 'class' => 'min-w-16'],
        ['key' => 'user.student_no', 'label' => 'Student No.', 'sortable' => true, 'class' => 'w-24'],
        ['key' => 'title', 'label' => 'Title Borrowed', 'sortable' => true, 'class' => 'min-w-48'],
        ['key' => 'paper_type', 'label' => 'Type', 'sortable' => true, 'class' => 'w-20'],
        ['key' => 'time_in', 'label' => 'Time In', 'sortable' => true, 'class' => 'w-28'],
        ['key' => 'time_out', 'label' => 'Time Out', 'sortable' => true, 'class' => 'w-28'],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'class' => 'w-20'],
        ['key' => 'notes', 'label' => 'Notes', 'class' => 'min-w-40'],
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
                    ->orWhere('student_no', 'like', "%{$this->search}%");
                })
                ->orWhereHas('inventory.academicPaper', function ($q) {
                    $q->where('title', 'like', "%{$this->search}%");
                })
                ->orWhere('notes', 'like', "%{$this->search}%");
            });
        })
        ->when($this->paperTypeFilter, function($query) {
            $query->whereHas('inventory.academicPaper', function($q) {
                $q->where('paper_type', $this->paperTypeFilter);
            });
        })
        ->when($this->statusFilter, function($query) {
            $query->where('status', $this->statusFilter);
        })
        ->when($this->selectedDate, function($query) {
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
                case 'user.student_no':
                    $query->join('users', 'borrow_transactions.user_id', '=', 'users.id')
                          ->orderBy('users.student_no', $direction)
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
