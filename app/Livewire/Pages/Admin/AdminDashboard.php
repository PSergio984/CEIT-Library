<?php

namespace App\Livewire\Pages\Admin;

use App\Models\AcademicPaper;
use App\Models\Attendance;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\Librarian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;

#[Title('Admin Dashboard')]
#[Lazy]
class AdminDashboard extends AdminComponent
{
    public function mount()
    {
        $this->authorizeAccess();
    }

    #[Computed]
    public function stats()
    {
        // Get student role ID for counting
        $studentRoleId = Role::where('name', 'student')->value('id') ?? 1;

        return [
            'total_users' => User::where('role_id', $studentRoleId)->count(),
            'total_papers' => AcademicPaper::count(),
            'total_copies' => Inventory::count(),
            'available_copies' => Inventory::where('status', 'Available')->count(),
            'active_librarians' => Librarian::active()->count(),
            'active_sessions' => Attendance::where('status', 'active')
                ->whereDate('time_in', today())
                ->count(),
            'active_borrows' => BorrowTransaction::where('status', 'started')
                ->whereDate('time_in', today())
                ->count(),
            'today_attendance' => Attendance::whereDate('time_in', today())->count(),
        ];
    }

    #[Computed]
    public function departmentStats()
    {
        return AcademicPaper::select('department', DB::raw('count(*) as count'))
            ->groupBy('department')
            ->get()
            ->map(fn ($item) => [
                'name' => $item->department,
                'value' => $item->count,
            ]);
    }

    #[Computed]
    public function academicPaperStats()
    {
        return AcademicPaper::select('paper_type', DB::raw('count(*) as count'))
            ->groupBy('paper_type')
            ->get()
            ->map(fn ($item) => [
                'name' => $item->paper_type,
                'value' => $item->count,
            ]);
    }

    #[Computed]
    public function loanTrends(): Collection
    {
        $startDate = now()->subDays(6)->startOfDay();
        $endDate = now()->endOfDay();

        $counts = BorrowTransaction::query()
            ->selectRaw('DATE(created_at) as date_group, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date_group')
            ->pluck('count', 'date_group');

        $trends = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayName = now()->subDays($i)->format('D');
            $count = $counts->get($date) ?? 0;

            $trends->push([
                'day' => $dayName,
                'count' => (int) $count,
            ]);
        }

        return $trends;
    }

    #[Computed]
    public function recentBorrowedPapers()
    {
        return BorrowTransaction::with(['user', 'inventory.academicPaper.authors', 'academicPaper'])
            ->where('status', 'started')
            ->whereDate('time_in', today())
            ->latest()
            ->take(5)
            ->get();
    }

    #[Computed]
    public function topBorrowers()
    {
        return User::withCount('borrowTransactions')
            ->orderByDesc('borrow_transactions_count')
            ->take(5)
            ->get();
    }

    /**
     * Placeholder shown while lazy loading the component
     */
    public function placeholder()
    {
        return view('components.loading-placeholder', [
            'message' => 'Loading Admin Dashboard...',
            'subtext' => 'Please wait while we fetch the admin data',
        ]);
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-dashboard');
    }
}
