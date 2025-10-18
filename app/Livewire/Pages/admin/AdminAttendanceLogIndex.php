<?php

namespace App\Livewire\Pages\Admin;

use App\Models\Attendance;
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
        ['key' => 'email', 'label' => 'Email', 'class' => 'min-w-32'],
        ['key' => 'time_in', 'label' => 'Time In', 'sortable' => true, 'class' => 'w-36'],
        ['key' => 'time_out', 'label' => 'Time Out', 'sortable' => true, 'class' => 'w-36'],
        ['key' => 'duration_minutes', 'label' => 'Duration', 'sortable' => true, 'class' => 'w-24'],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'class' => 'w-24'],
    ];

    public array $sortBy = ['column' => 'time_in', 'direction' => 'desc'];

    protected function getAttendancesQuery()
    {
        return Attendance::with('user')
            ->when($this->search, function ($query) {
                $query->whereHas('user', function ($q) {
                    $q->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->selectedDate, fn($q) => $q->whereDate('time_in', $this->selectedDate));
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
            } else {
                $query->orderBy($column, $direction);
            }
        } else {
            $query->orderBy('time_in', 'desc');
        }

        return $query->paginate($this->perPage)
            ->through(function ($attendance) {
                return [
                    'id' => $attendance->id,
                    'user_name' => trim(($attendance->user?->first_name ?? '') . ' ' . ($attendance->user?->last_name ?? '')) ?: 'N/A',
                    'email' => $attendance->user?->email ?? 'N/A',
                    'user' => $attendance->user,
                    'time_in' => $attendance->time_in,
                    'time_out' => $attendance->time_out,
                    'duration_minutes' => $attendance->duration_minutes,
                    'status' => $attendance->status,
                    'original' => $attendance,
                ];
            });
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
