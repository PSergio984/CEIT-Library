<?php

namespace App\Livewire\Pages\Admin;

use App\Models\ViolationTransaction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class ViolationTransactionsTab extends AdminComponent
{
    use Toast, WithPagination;

    protected $listeners = ['refreshViolationTransactionsTab' => 'getViolationTransactionsProperty'];

    public $searchTransaction = '';

    public $perPageTransaction = 10;

    public $dateFilter = '';

    public $confirmUndoModal = false;

    public $editingId = null;

    public array $sortBy = ['column' => 'date_occurred', 'direction' => 'desc'];

    public array $transactionHeaders = [
        ['key' => 'id', 'label' => '#', 'class' => 'w-16'],
        ['key' => 'user.name', 'label' => 'User', 'sortable' => false, 'class' => 'w-44'],
        ['key' => 'violation.name', 'label' => 'Violation', 'sortable' => false, 'class' => 'w-80'],
        ['key' => 'violation_penalty', 'label' => 'Penalty', 'sortable' => true, 'class' => 'w-24'],
        ['key' => 'date_occurred', 'label' => 'Date', 'sortable' => true, 'class' => 'w-36'],
    ];

    public function getViolationTransactionsProperty()
    {
        $search = trim($this->searchTransaction);

        $query = ViolationTransaction::with(['user', 'violation'])
            ->when($search, function ($query) use ($search) {
                $terms = explode(' ', $search); // split by space

                $query->where(function ($outer) use ($terms, $search) {
                    // Search in user first/last name
                    $outer->whereHas('user', function ($q) use ($terms) {
                        foreach ($terms as $term) {
                            $q->where(function ($sub) use ($term) {
                                $sub->where('first_name', 'like', "%{$term}%")
                                    ->orWhere('last_name', 'like', "%{$term}%");
                            });
                        }
                    })
                        // Also search in violation name
                        ->orWhereHas('violation', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($this->dateFilter, function ($query) {
                $query->whereDate('date_occurred', $this->dateFilter);
            });

        if (isset($this->sortBy['column']) && isset($this->sortBy['direction'])) {
            $query->orderBy($this->getSanitizedSortColumn(), $this->sortBy['direction']);
        }

        return $query->paginate($this->perPageTransaction, ['*'], 'transactionsPage');
    }

    protected function getSanitizedSortColumn(): string
    {
        $allowed = ['id', 'violation_penalty', 'date_occurred'];

        return in_array($this->sortBy['column'], $allowed)
            ? $this->sortBy['column']
            : 'date_occurred';
    }

    public function confirmUndo(int $id)
    {
        $this->editingId = $id;
        $this->confirmUndoModal = true;
    }

    public function undoConfirmed()
    {
        try {
            $transaction = ViolationTransaction::findOrFail($this->editingId);
            $transaction->delete();
            $this->dispatch('refreshActiveUsers');
            $this->success("Violation for {$transaction->user->name} undone.");
        } catch (ModelNotFoundException $e) {
            $this->error('Violation transaction not found or already undone.');
        } finally {
            $this->confirmUndoModal = false;
            $this->resetPage('transactionsPage');
        }
    }

    public function clearTransactionFilters()
    {
        $this->searchTransaction = '';
        $this->dateFilter = '';
        $this->sortBy = ['column' => 'date_occurred', 'direction' => 'desc'];
        $this->resetPage('transactionsPage');
    }

    public function render()
    {
        return view('livewire.pages.admin.violation-transactions-tab');
    }
}
