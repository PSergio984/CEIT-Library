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

    public $search = '';
    public $perPage = 10;

    public $ViolationDrawer = false;
    public $selectedUserForViolation = null;
    public $selectedViolationId = null;
    public $violationSeverity = 'Minor';
    public $violationRemarks = '';
    public $searchActiveUsers = '';
    public $perPageActiveUsers = 10;

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
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
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
            'violationSeverity'        => 'required|in:Minor,Moderate,Major,Critical',
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

            $this->success("Violation '{$violation->name}' recorded for {$user->first_name} {$user->last_name}. Credit score updated to {$user->credit_score}.");
            $this->openViolationDrawer = false;
            $this->reset(['selectedUserForViolation', 'selectedViolationId', 'violationSeverity', 'violationRemarks']);
        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.pages.admin.active-users-tab');
    }
}
