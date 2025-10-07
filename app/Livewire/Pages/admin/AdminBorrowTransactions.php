<?php

namespace App\Livewire\Pages\Admin;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use Livewire\Component;
use Livewire\WithPagination;

class AdminBorrowTransactions extends AdminComponent
{
    use WithPagination;

    public $perPage = 15;
    public $search = '';
    public $paperTypeFilter = '';
    public $yearFrom = '';
    public $yearTo = '';

    protected function getTransactionsQuery()
    {
        return BorrowTransaction::with([
            'user',
            'inventory.academicPaper.authors'  // Get book details
        ])
        ->when($this->search, function($query) {
            $query->whereHas('user', function($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                  ->orWhere('last_name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
                  ->orWhere('student_no', 'like', "%{$this->search}%");
            })
            ->orWhereHas('inventory.academicPaper', function($q) {
                $q->where('title', 'like', "%{$this->search}%");
            });
        })
        ->when($this->paperTypeFilter, function($query) {
            $query->whereHas('inventory.academicPaper', function($q) {
                $q->where('paper_type', $this->paperTypeFilter);
            });
        })
        ->when($this->yearFrom, function($query) {
            $query->whereHas('inventory.academicPaper', function($q) {
                $q->where('publication_year', '>=', $this->yearFrom);
            });
        })
        ->when($this->yearTo, function($query) {
            $query->whereHas('inventory.academicPaper', function($q) {
                $q->where('publication_year', '<=', $this->yearTo);
            });
        })
        ->orderBy('time_in', 'desc');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingPaperTypeFilter()
    {
        $this->resetPage();
    }

    public function updatingYearFrom()
    {
        $this->resetPage();
    }

    public function updatingYearTo()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->paperTypeFilter = '';
        $this->yearFrom = '';
        $this->yearTo = '';
        $this->resetPage();
    }

    public function render()
    {
        $transactions = $this->getTransactionsQuery()->paginate($this->perPage);

        // Get unique paper types for filter dropdown
        $paperTypes = AcademicPaper::distinct()->pluck('paper_type')->filter();

        return view('livewire.pages.admin.admin-borrow-transactions', [
            'transactions' => $transactions,
            'paperTypes' => $paperTypes
        ]);
    }
}
