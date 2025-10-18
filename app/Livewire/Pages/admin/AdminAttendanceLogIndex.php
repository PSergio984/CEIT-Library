<?php

namespace App\Livewire\Pages\Admin;

use App\Models\Attendance;
use App\Models\Librarian;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class AdminAttendanceLogIndex extends AdminComponent
{
    use WithPagination, Toast;

    public $perPage = 20;
    public $search = '';
    public $statusFilter = '';
    public $selectedDate = '';

    public array $headers = [
        ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
        ['key' => 'user_name', 'label' => 'Student Name', 'sortable' => true, 'class' => 'min-w-32'],
        ['key' => 'scanned_by_name', 'label' => 'Scanned_By', 'sortable' => true, 'class' => 'min-w-40'],
        ['key' => 'time_in', 'label' => 'Time In', 'sortable' => true, 'class' => 'w-36'],
        ['key' => 'time_out', 'label' => 'Time Out', 'sortable' => true, 'class' => 'w-36'],
        ['key' => 'duration_minutes', 'label' => 'Duration', 'sortable' => true, 'class' => 'w-24'],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'class' => 'w-24'],
    ];

    public array $sortBy = ['column' => 'time_in', 'direction' => 'desc'];

    protected function getAttendancesQuery()
    {
        $search = trim($this->search);

        return Attendance::with(['user', 'scannedByLibrarian.user'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    // Search student name (match full name or individual parts)
                    $q->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                        // Search librarian name (match full name or individual parts)
                        ->orWhereHas('scannedByLibrarian.user', function ($librarianQuery) use ($search) {
                            $librarianQuery->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('attendances.status', $this->statusFilter))
            ->when($this->selectedDate, fn($q) => $q->whereDate('attendances.time_in', $this->selectedDate));
    }

    public function getAttendancesProperty()
    {
        $query = $this->getAttendancesQuery();

        if (isset($this->sortBy['column']) && isset($this->sortBy['direction'])) {
            $column = $this->sortBy['column'];
            $direction = $this->sortBy['direction'];

            if ($column === 'user_name') {
                $query->join('users', 'attendances.user_id', '=', 'users.id')
                    ->orderBy('users.first_name', $direction)
                    ->select('attendances.*');
            } elseif ($column === 'scanned_by_name') {
                $query->leftJoin('librarians', 'attendances.scanned_by', '=', 'librarians.id')
                    ->leftJoin('users as librarian_users', 'librarians.user_id', '=', 'librarian_users.id')
                    ->orderBy('librarian_users.first_name', $direction)
                    ->select('attendances.*');
            } else {
                $query->orderBy($column, $direction);
            }
        } else {
            $query->orderBy('time_in', 'desc');
        }


        return $query->paginate($this->perPage)
            ->through(function ($attendance) {

                return [
                    'id'               => $attendance->id,
                    'user_name'        => trim(($attendance->user?->first_name ?? '') . ' ' . ($attendance->user?->last_name ?? '')) ?: 'N/A',
                    'scanned_by_name'  => trim(($attendance->scannedByLibrarian?->user?->first_name ?? '') . ' ' . ($attendance->scannedByLibrarian?->user?->last_name ?? '')) ?: 'N/A',
                    'user'             => $attendance->user,
                    'time_in'          => $attendance->time_in,
                    'time_out'         => $attendance->time_out,
                    'duration_minutes' => $attendance->duration_minutes,
                    'status'           => $attendance->status,
                    'original'         => $attendance,
                ];
            });
    }

    public function getCurrentlyInLibraryProperty()
    {
        return Attendance::where('status', 'active')
            ->whereDate('time_in', today())
            ->count();
    }

    public function getTimedOutTodayProperty()
    {
        return Attendance::where('status', 'completed')
            ->whereDate('time_out', today())
            ->count();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingSelectedDate()
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
        $this->selectedDate = '';
        $this->statusFilter = '';
        $this->sortBy = ['column' => 'time_in', 'direction' => 'desc'];
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-attendance-log-index');
    }
}
