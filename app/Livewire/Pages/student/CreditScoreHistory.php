<?php

namespace App\Livewire\Pages\Student;

use App\Models\ScoreIncrement;
use App\Models\ViolationTransaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Credit Score History')]
class CreditScoreHistory extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = ''; // 'reward', 'penalty', or '' for all

    public string $selectedDate = '';

    public int $perPage = 10;

    #[Computed]
    public function creditScore()
    {
        return Auth::user()?->credit_score ?? 0;
    }

    #[Computed]
    public function history()
    {
        $userId = Auth::id();
        $histories = collect();

        // Get rewards from score_increments (includes attendance bonuses and on-time return bonuses)
        $scoreIncrements = ScoreIncrement::where('user_id', $userId)
            ->when($this->selectedDate, function ($q) {
                $q->whereDate('created_at', $this->selectedDate);
            })
            ->get()
            ->map(function ($s) {
                return [
                    'id' => 'reward-score-'.$s->id,
                    'action' => $s->name,
                    'description' => $s->description,
                    'points' => $s->score_value, // This is the points added
                    'type' => 'reward',
                    'occurred_at' => $s->created_at,
                ];
            });

        // Get penalties from violation_transactions
        $violations = ViolationTransaction::with('violation')
            ->where('user_id', $userId)
            ->when($this->selectedDate, function ($q) {
                $q->whereDate('date_occurred', $this->selectedDate);
            })
            ->get()
            ->map(function ($v) {
                return [
                    'id' => 'penalty-'.$v->id,
                    'action' => $v->violation?->name ?? 'Violation',
                    'description' => $v->remarks,
                    'points' => -($v->violation?->penalty_score ?? 0),
                    'type' => 'penalty',
                    'occurred_at' => $v->date_occurred,
                ];
            });

        // Combine all histories
        $histories = $histories->concat($scoreIncrements)
            ->concat($violations);

        // Apply type filter
        if ($this->typeFilter) {
            $histories = $histories->where('type', $this->typeFilter);
        }

        // Apply search filter
        if ($this->search) {
            $histories = $histories->filter(function ($h) {
                return str_contains(strtolower($h['action']), strtolower($this->search)) ||
                    str_contains(strtolower($h['description'] ?? ''), strtolower($this->search));
            });
        }

        // Sort by occurred_at descending
        $histories = $histories->sortByDesc('occurred_at')->values();

        // Paginate manually using Laravel's paginator resolver
        $pageName = 'page'; // Default page name used by WithPagination
        $currentPage = LengthAwarePaginator::resolveCurrentPage($pageName);
        $items = $histories->slice(($currentPage - 1) * $this->perPage, $this->perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $histories->count(),
            $this->perPage,
            $currentPage,
            ['path' => request()->url(), 'pageName' => $pageName]
        );
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'selectedDate']);
        $this->resetPage();
    }

    public function viewHistory($historyId): void
    {
        // Dispatch event with history item details
        $this->dispatch('history-selected', ['id' => $historyId]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedDate(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.pages.student.credit-score-history');
    }
}
