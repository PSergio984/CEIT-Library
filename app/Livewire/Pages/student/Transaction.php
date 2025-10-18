<?php

namespace App\Livewire\Pages\Student;

use App\Models\BorrowTransaction;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;

#[Title('Transaction History')]
class Transaction extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $paperTypeFilter = '';
    public string $selectedDate = '';
    public int $perPage = 10;


    #[Computed]
    public function paperTypes(): array
    {
        return BorrowTransaction::query()
            ->where('user_id', Auth::id())
            ->join('inventories', 'borrow_transactions.inventory_id', '=', 'inventories.id')
            ->join('academic_papers', 'inventories.academic_paper_id', '=', 'academic_papers.id')
            ->distinct()
            ->pluck('academic_papers.paper_type')
            ->toArray();
    }

    public function getTransactionsQuery()
    {
        $query = BorrowTransaction::with([
            'user',
            'inventory.academicPaper'
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

    #[Computed]
    public function transactions()
    {
        return $this->getTransactionsQuery()
            ->paginate($this->perPage)
            ->through(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'title' => $transaction->inventory->academicPaper->title ?? 'N/A',
                    'paper_type' => $transaction->inventory->academicPaper->paper_type ?? 'N/A',
                    'time_in' => $transaction->time_in,
                    'time_out' => $transaction->time_out,
                    'status' => $transaction->status,
                    'duration' => $this->formatDuration($transaction),
                    'notes' => $transaction->notes ?? 'No notes',
                    'expires_at' => $transaction->expires_at,
                    'academic_paper' => $transaction->inventory->academicPaper,
                    'inventory' => $transaction->inventory,
                ];
            });
    }

    private function formatDuration($transaction): string
    {
        if (!$transaction->time_in) {
            return 'N/A';
        }

        $endTime = $transaction->time_out ?? now();
        $duration = $transaction->time_in->diffInMinutes($endTime);

        if ($duration < 60) {
            return $duration . 'm';
        } else {
            $hours = floor($duration / 60);
            $minutes = $duration % 60;
            return $hours . 'h ' . $minutes . 'm';
        }
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
                'expires_at' => $transaction->expires_at->addDays(7)
            ]);

            $this->dispatch('transaction-extended', [
                'message' => 'Transaction extended by 7 days successfully!'
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

    public function render()
    {
        return view('livewire.pages.student.transaction');
    }
}
