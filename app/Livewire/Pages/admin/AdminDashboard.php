<?php

namespace App\Livewire\Pages\Admin;

use App\Models\AcademicPaper;
use App\Models\User;
use App\Models\Attendance;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\Librarian;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

#[Title('Admin Dashboard')]
class AdminDashboard extends AdminComponent
{
    #[Computed]
    public function stats()
    {
        return [
            'total_users' => User::where('is_admin', 0)->count(),
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
            ->map(fn($item) => [
                'name' => $item->department,
                'value' => $item->count
            ]);
    }

    #[Computed]
    public function academicPaperStats()
    {
        return AcademicPaper::select('paper_type', DB::raw('count(*) as count'))
            ->groupBy('paper_type')
            ->get()
            ->map(fn($item) => [
                'name' => $item->paper_type,
                'value' => $item->count
            ]);
    }

    #[Computed]
    public function recentBorrowedPapers()
    {
        return BorrowTransaction::with(['user', 'inventory.academicPaper.authors'])
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

    public function render()
    {
        return view('livewire.pages.admin.admin-dashboard');
    }
}
