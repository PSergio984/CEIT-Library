<?php

namespace App\Livewire\Pages\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationTransaction;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

class ActiveUsersTab extends AdminComponent
{
    use WithPagination, Toast;

    // Refresh the table when a violation is recorded on ViolationTransactionsTab
    protected $listeners = [
        'refreshActiveUsers' => '$refresh',
    ];

    public $search = '';
    public $perPage = 10;

    public $ViolationDrawer = false;
    public $selectedUserForViolation = null;
    public $selectedViolationId = null;
    public $violationSeverity = 'Minor';
    public $violationRemarks = '';
    public $searchActiveUsers = '';
    public $perPageActiveUsers = 10;

    public $confirmForgotTimeoutModal = false;
    public $attendanceToDeclare = null;

    public array $activeUsersHeaders = [
        ['key' => 'id', 'label' => '#', 'class' => 'w-20'],
        ['key' => 'user.name', 'label' => 'Name', 'sortable' => false, 'class' => 'w-70'],
        ['key' => 'user.credit_score', 'label' => 'Credit Score', 'sortable' => false, 'class' => 'w-60'],
        ['key' => 'time_in', 'label' => 'Time In', 'sortable' => true, 'class' => 'w-60'],
    ];

    public array $sortBy = ['column' => 'time_in', 'direction' => 'desc'];

    public $severityOptions = [
        ['id' => 'Minor', 'name' => 'Minor'],
        ['id' => 'Major', 'name' => 'Major'],
        ['id' => 'Critical', 'name' => 'Critical'],
    ];

    public function getActiveUsersProperty()
    {
        $search = trim($this->searchActiveUsers);

        $query = Attendance::with('user')
            ->where('status', 'active')
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->whereDate('time_in', today())
            ->when($search, function ($query) use ($search) {
                // Split the search into words
                $terms = preg_split('/\s+/', $search);

                $query->whereHas('user', function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $q->where(function ($sub) use ($term) {
                            $sub->where('first_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%");
                        });
                    }
                });
            });


        if (isset($this->sortBy['column']) && isset($this->sortBy['direction'])) {
            $query->orderBy($this->sortBy['column'], $this->sortBy['direction']);
        }

        return $query->paginate($this->perPageActiveUsers, ['*'], 'activeUsersPage');
    }

    public function getViolationOptionsProperty()
    {
        return Violation::orderBy('name')->get()->map(function ($violation) {
            return [
                'id'   => $violation->id,
                'name' => $violation->name . ' (-' . $violation->penalty_score . ' points)',
            ];
        });
    }

    public function clearActiveUsersFilters()
    {
        $this->searchActiveUsers = '';
        $this->resetPage('activeUsersPage');
    }

    public function updatingSearchActiveUsers()
    {
        $this->resetPage('activeUsersPage');
    }

    public function openViolationDrawer($userId)
    {
        $this->selectedUserForViolation = $userId;
        $this->selectedViolationId = null;
        $this->violationSeverity = 'Minor';
        $this->violationRemarks = '';
        $this->ViolationDrawer = true;
    }

    public function recordViolation()
    {
        $this->validate([
            'selectedUserForViolation' => 'required|exists:users,id',
            'selectedViolationId'      => 'required|exists:violations,id',
            'violationSeverity'        => 'required|in:Minor,Major,Critical',
            'violationRemarks'         => 'nullable|string|max:500',
        ]);

        try {
            DB::transaction(function () {
                ViolationTransaction::create([
                    'user_id'       => $this->selectedUserForViolation,
                    'violation_id'  => $this->selectedViolationId,
                    'severity'      => $this->violationSeverity,
                    'remarks'       => $this->violationRemarks,
                    'date_occurred' => now(),
                ]);
            });

            $user = User::find($this->selectedUserForViolation);
            $violation = Violation::find($this->selectedViolationId);

            // refresh table and reset pagination after change
            $this->dispatch('refreshViolationTransactionsTab');
            $this->resetPage('activeUsersPage');

            $this->success("Violation '{$violation->name}' recorded for {$user->first_name} {$user->last_name}. Credit score updated to {$user->credit_score}.");
            $this->ViolationDrawer = false;

            $this->reset(['selectedUserForViolation', 'selectedViolationId', 'violationSeverity', 'violationRemarks']);
        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
        }
    }

    public function declareForgotTimeout($attendanceId)
    {
        try {
            DB::transaction(function () use ($attendanceId) {
                $attendance = Attendance::lockForUpdate()->find($attendanceId);

                if (!$attendance || !$attendance->isActive()) {
                    throw new \Exception('Attendance not found or not active.');
                }

                // Close attendance now
                $attendance->time_out = now();
                $attendance->duration_minutes = 0;
                $attendance->status = 'completed';
                $attendance->save();

                // Create or find the "Forgot to time out" violation (uses config penalty if missing)
                $defaultPenalty = (int)config('attendance.forgot_timeout_penalty', 5);
                $violation = Violation::firstOrCreate(
                    ['name' => 'Forgot to time out'],
                    ['description' => 'Marked by admin as forgot to time out', 'penalty_score' => $defaultPenalty]
                );

                // Record violation transaction for audit
                ViolationTransaction::create([
                    'user_id'       => $attendance->user_id,
                    'violation_id'  => $violation->id,
                    'severity'      => 'Minor',
                    'remarks'       => 'Declared by admin: forgot to time out',
                    'date_occurred' => now(),
                ]);

            });

            $this->dispatch('refreshViolationTransactionsTab');
            $this->success('Attendance closed and penalty applied.');
        } catch (\Exception $e) {
            $this->error('Failed to declare forgot-timeout: ' . $e->getMessage());
        }
    }

    public function openDeclareForgotTimeoutModal($attendanceId)
    {
        $this->attendanceToDeclare = $attendanceId;
        $this->confirmForgotTimeoutModal = true;
    }

    public function confirmDeclareForgotTimeout()
    {
        if (!$this->attendanceToDeclare) {
            $this->error('No attendance selected.');
            return;
        }

        $attendanceId = $this->attendanceToDeclare;
        $this->confirmForgotTimeoutModal = false;
        $this->attendanceToDeclare = null;

        $this->declareForgotTimeout($attendanceId);
    }

    public function render()
    {
        return view('livewire.pages.admin.active-users-tab');
    }
}
