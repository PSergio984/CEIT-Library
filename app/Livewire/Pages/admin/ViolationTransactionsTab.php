<?php

namespace App\Livewire\Pages\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use App\Models\ViolationTransaction;

class ViolationTransactionsTab extends AdminComponent
{
    use WithPagination, Toast;

    public $searchTransaction = '';
    public $perPageTransaction = 10;
    public $severityFilter = '';
    public $dateFilter = '';

    public array $sortBy = ['column' => 'date_occurred', 'direction' => 'desc'];

    public array $transactionHeaders = [
        ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
        ['key' => 'user.name', 'label' => 'User', 'sortable' => false, 'class' => 'min-w-40'],
        ['key' => 'violation.name', 'label' => 'Violation', 'sortable' => false, 'class' => 'min-w-40'],
        ['key' => 'violation_penalty', 'label' => 'Penalty', 'sortable' => true, 'class' => 'w-24'],
        ['key' => 'severity', 'label' => 'Severity', 'sortable' => true, 'class' => 'w-24'],
        ['key' => 'date_occurred', 'label' => 'Date', 'sortable' => true, 'class' => 'w-32'],
    ];

    public $severityOptions = [
        ['id' => 'Minor', 'name' => 'Minor'],
        ['id' => 'Major', 'name' => 'Major'],
        ['id' => 'Critical', 'name' => 'Critical'],
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
            ->when($this->severityFilter, function ($query) {
                $query->where('severity', $this->severityFilter);
            })
            ->when($this->dateFilter, function ($query) {
                $query->whereDate('date_occurred', $this->dateFilter);
            });

        if (isset($this->sortBy['column']) && isset($this->sortBy['direction'])) {
            $query->orderBy($this->sortBy['column'], $this->sortBy['direction']);
        }

        return $query->paginate($this->perPageTransaction, ['*'], 'transactionsPage');
    }

    public function undoTransaction(int $id)
    {
        $transaction = ViolationTransaction::findOrFail($id);
        $transaction->delete();

        $this->success("Violation for {$transaction->user->name} undone.");
    }

    public function clearTransactionFilters()
    {
        $this->searchTransaction = '';
        $this->severityFilter = '';
        $this->dateFilter = '';
        $this->sortBy = ['column' => 'date_occurred', 'direction' => 'desc'];
        $this->resetPage('transactionsPage');
    }

    public function render()
    {
        return view('livewire.pages.admin.violation-transactions-tab');
    }
}
