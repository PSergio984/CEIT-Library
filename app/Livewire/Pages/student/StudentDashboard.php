<?php

namespace App\Livewire\Pages\Student;

use App\Models\AcademicPaper;
use App\Models\Attendance;
use App\Models\BorrowTransaction;
use App\Models\ScoreIncrement;
use App\Models\ViolationTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Student Dashboard')]
class StudentDashboard extends Component
{
    #[Computed]
    public function stats()
    {
        $user = Auth::user();

        return [
            'credit_score' => $user->credit_score ?? 0,
            'active_borrows' => BorrowTransaction::where('user_id', $user->id)
                ->where('status', 'started')
                ->count(),
            'total_borrows' => BorrowTransaction::where('user_id', $user->id)->count(),
            'library_visits' => Attendance::where('user_id', $user->id)->count(),
            'overdue_count' => BorrowTransaction::where('user_id', $user->id)
                ->where('status', 'started')
                ->where('expires_at', '<', now())
                ->count(),
        ];
    }

    #[Computed]
    public function activeBorrows()
    {
        return BorrowTransaction::with(['inventory.academicPaper.authors'])
            ->where('user_id', Auth::id())
            ->where('status', 'started')
            ->latest()
            ->take(5)
            ->get();
    }

    #[Computed]
    public function recentActivity()
    {
        $userId = Auth::id();
        $activities = collect();

        // Recent borrows
        $recentBorrows = BorrowTransaction::with('inventory.academicPaper')
            ->where('user_id', $userId)
            ->whereHas('inventory.academicPaper')
            ->latest()
            ->take(3)
            ->get()
            ->map(function ($borrow) {
                return [
                    'type' => 'borrow',
                    'title' => 'Borrowed: ' . $borrow->inventory->academicPaper->title,
                    'description' => 'Status: ' . ucfirst($borrow->status),
                    'date' => $borrow->created_at,
                    'icon' => 'o-book-open',
                    'color' => $borrow->status === 'started' ? 'text-warning' : 'text-success',
                ];
            });
        // Recent attendance
        $recentAttendance = Attendance::where('user_id', $userId)
            ->latest()
            ->take(2)
            ->get()
            ->map(function ($attendance) {
                return [
                    'type' => 'attendance',
                    'title' => 'Library Visit',
                    'description' => $attendance->status === 'active' ? 'Currently in library' : 'Visit completed',
                    'date' => $attendance->time_in,
                    'icon' => 'o-building-library',
                    'color' => 'text-info',
                ];
            });

        return $activities->merge($recentBorrows)
            ->merge($recentAttendance)
            ->sortByDesc('date')
            ->take(5);
    }

    #[Computed]
    public function creditScoreHistory()
    {
        $userId = Auth::id();
        $history = collect();

        // Get score increments (rewards)
        $increments = ScoreIncrement::where('user_id', $userId)
            ->latest()
            ->take(3)
            ->get()
            ->map(function ($increment) {
                return [
                    'type' => 'reward',
                    'points' => '+' . $increment->points,
                    'reason' => $increment->reason,
                    'date' => $increment->created_at,
                ];
            });

        // Get violations (penalties)
        $violations = ViolationTransaction::where('user_id', $userId)
            ->latest()
            ->take(3)
            ->get()
            ->map(function ($violation) {
                return [
                    'type' => 'penalty',
                    'points' => '-' . $violation->penalty_points,
                    'reason' => $violation->violation?->violation ?? 'Violation',
                    'date' => $violation->created_at,
                ];
            });
        return $history->merge($increments)
            ->merge($violations)
            ->sortByDesc('date')
            ->take(5);
    }

    #[Computed]
    public function availablePapers()
    {
        return AcademicPaper::with('copies')
            ->whereHas('copies', function ($query) {
                $query->where('status', 'Available');
            })
            ->withCount(['copies as available_count' => function ($query) {
                $query->where('status', 'Available');
            }])
            ->inRandomOrder()
            ->take(4)
            ->get();
    }

    #[Computed]
    public function upcomingDueDates()
    {
        return BorrowTransaction::with('inventory.academicPaper')
            ->where('user_id', Auth::id())
            ->where('status', 'started')
            ->orderBy('expires_at')
            ->take(3)
            ->get();
    }

    public function render()
    {
        return view('livewire.pages.student.student-dashboard');
    }
}
