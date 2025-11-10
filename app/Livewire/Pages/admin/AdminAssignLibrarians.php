<?php

namespace App\Livewire\Pages\Admin;

use App\Models\Librarian;
use App\Models\User;
use Auth;
use DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Mary\Traits\Toast;

class AdminAssignLibrarians extends AdminComponent
{
    use Toast, AuthorizesRequests;

    public $search = '';
    public $batchSearch = '';
    public $showCreateModal = false;
    public $showEditModal = false;
    public $selectedStudents = [];
    public $editingBatchNo = null;
    public $editingDateStart = null;
    public $editingShiftNotes = '';
    public $editingSelectedStudents = [];

    public $newBatchNo = '';
    public $selectedDate = '';

    public $filterStatus = '';
    public $filterDateStart = null;

    protected $listeners = ['batchUpdated' => 'render'];

    public function mount()
    {
        // Authorize that only super admins can access this page
        $this->authorize('manage-librarian-batches');
    }


    public function getGroupedLibrariansProperty()
    {
        return Librarian::with(['user', 'createdBy'])
            ->whereNotNull('batch_no')
            ->get()
            ->groupBy('batch_no');
    }

    public function getLibrariansQueryProperty()
    {
        return $this->getGroupedLibrariansProperty();
    }

    public function getIsDateChangingProperty()
    {
        if (is_null($this->editingBatchNo)) {
            return false;
        }

        $currentBatch = Librarian::where('batch_no', $this->editingBatchNo)->first();
        $currentDate = $currentBatch && $currentBatch->start_date ? date('Y-m-d', strtotime($currentBatch->start_date)) : null;

        return $currentDate != $this->editingDateStart && !empty($this->editingDateStart);
    }

    public function getConflictingBatchProperty()
    {
        if (!$this->isDateChanging || empty($this->editingDateStart)) {
            return null;
        }

        return Librarian::where('batch_no', '!=', $this->editingBatchNo)
            ->whereNotNull('start_date')
            ->where('start_date', $this->editingDateStart)
            ->first();
    }

    public function getAvailableBatchesProperty()
    {
        return $this->getLibrariansQueryProperty()
            ->filter(function ($librarians) {
                return is_null($librarians->first()->start_date);
            })
            ->map(function ($librarians, $batchNo) {
                return [
                    'batch_no' => $batchNo,
                    'members' => $librarians->map(function ($lib) {
                        return $lib->user->first_name . ' ' . $lib->user->last_name;
                    })->toArray(),
                    'librarians' => $librarians
                ];
            });
    }

    public function getAssignedBatchesProperty()
    {
        return $this->getLibrariansQueryProperty()
            ->filter(function ($librarians) {
                return !is_null($librarians->first()->start_date);
            })
            ->map(function ($librarians, $batchNo) {
                $first = $librarians->first();
                return [
                    'batch_no' => $batchNo,
                    'members' => $librarians->map(function ($lib) {
                        return $lib->user->first_name . ' ' . $lib->user->last_name;
                    })->toArray(),
                    'date_assigned' => $first->start_date ? date('Y-m-d', strtotime($first->start_date)) : 'N/A',
                    'librarians' => $librarians
                ];
            });
    }

    public function getAllBatchesProperty()
    {
        $grouped = $this->getLibrariansQueryProperty();

        if ($this->filterStatus) {
            $grouped = $grouped->filter(function ($librarians) {
                return $librarians->first()->status === $this->filterStatus;
            });
        }

        $filterStart = $this->filterDateStart ? strtotime($this->filterDateStart) : null;

        if ($filterStart) {
            $grouped = $grouped->filter(function ($librarians) use ($filterStart) {
                $first = $librarians->first();

                if (is_null($first->start_date)) {
                    return false;
                }

                $batchStart = strtotime($first->start_date);

                return $batchStart >= $filterStart;
            });
        }

        if ($this->batchSearch) {
            $search = strtolower($this->batchSearch);
            $grouped = $grouped->filter(function ($librarians, $batchNo) use ($search) {
                $first = $librarians->first();

                $createdBy = strtolower(($first->createdBy->first_name ?? '') . ' ' . ($first->createdBy->last_name ?? ''));
                $shiftNotes = strtolower($first->shift_notes ?? '');

                $studentMatch = $librarians->contains(function ($lib) use ($search) {
                    $fullName = strtolower($lib->user->first_name . ' ' . $lib->user->last_name);
                    return stripos($fullName, $search) !== false;
                });

                return stripos($batchNo, $search) !== false ||
                    stripos($shiftNotes, $search) !== false ||
                    stripos($createdBy, $search) !== false ||
                    $studentMatch;
            });
        }

        return $grouped->map(function ($librarians, $batchNo) {
            $first = $librarians->first();
            return [
                'batch_no' => $batchNo,
                'date_range' => ($first->start_date ? date('Y-m-d', strtotime($first->start_date)) : 'N/A'),
                'shift_notes' => $first->shift_notes ?? 'N/A',
                'created_by' => ($first->createdBy->first_name ?? '') . ' ' . ($first->createdBy->last_name ?? ''),
                'status' => $first->status,
                'librarians' => $librarians,
                'first' => $first
            ];
        })->values();
    }

    public function getAvailableStudentsProperty()
    {
        $usedUserIds = Librarian::pluck('user_id')->toArray();

        // Get student role ID
        $studentRoleId = \App\Models\Role::where('name', 'student')->value('id');

        return User::where('account_status', 'active')
            ->where('role_id', $studentRoleId)
            ->whereNotIn('id', $usedUserIds)
            ->select('id', 'first_name', 'last_name', 'email')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function getStudentBatchAssignmentsProperty()
    {
        return Librarian::select('user_id', 'batch_no')
            ->get()
            ->groupBy('user_id')
            ->map(function ($assignments) {
                return $assignments->first()->batch_no;
            });
    }

    public function getAvailableStudentsForEditProperty()
    {
        $usedUserIds = Librarian::where('batch_no', '!=', $this->editingBatchNo ?? '')
            ->pluck('user_id')
            ->toArray();

        // Get student role ID
        $studentRoleId = \App\Models\Role::where('name', 'student')->value('id');

        $availableStudents = User::where('account_status', 'active')
            ->where('role_id', $studentRoleId)
            ->whereNotIn('id', $usedUserIds)
            ->select('id', 'first_name', 'last_name', 'email')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return $availableStudents;
    }

    public function resetFilters()
    {
        $this->reset(['batchSearch', 'filterStatus', 'filterDateStart']);
    }

    public function openCreateModal()
    {
        $this->reset(['selectedStudents', 'newBatchNo']);
        $this->resetErrorBag();
        $this->showCreateModal = true;
    }

    public function createBatch()
    {
        // Ensure only super admins can create batches and assign librarians
        $this->authorize('manage-librarian-batches');

        $this->validate([
            'newBatchNo' => 'required|unique:librarians,batch_no',
            'selectedStudents' => 'required|array|size:5',
        ], [
            'selectedStudents.size' => 'A batch must have exactly 5 students.',
        ]);

        DB::transaction(function () {
            $alreadyAssigned = Librarian::whereIn('user_id', $this->selectedStudents)
                ->with('user')
                ->get();

            if ($alreadyAssigned->isNotEmpty()) {
                $studentNames = $alreadyAssigned->map(function ($lib) {
                    return $lib->user->first_name . ' ' . $lib->user->last_name . ' (Batch: ' . $lib->batch_no . ')';
                })->join(', ');

                throw new \Exception("The following students are already assigned to batches: {$studentNames}");
            }

            foreach ($this->selectedStudents as $userId) {
                Librarian::create([
                    'user_id' => $userId,
                    'batch_no' => $this->newBatchNo,
                    'status' => 'inactive',
                    'start_date' => null,
                    'end_date' => null,
                    'created_by' => Auth::id(),
                ]);
            }
        });

        $this->success('Batch created successfully!');
        $this->showCreateModal = false;
        $this->reset(['selectedStudents', 'newBatchNo']);
    }

    public function openEditModal($batchNo)
    {
        $librarians = Librarian::where('batch_no', $batchNo)->get();

        if ($librarians->isEmpty()) {
            $this->error('Batch not found.');
            return;
        }

        $first = $librarians->first();

        $this->editingBatchNo = $batchNo;
        $this->editingDateStart = $first->start_date ? date('Y-m-d', strtotime($first->start_date)) : '';
        $this->editingShiftNotes = $first->shift_notes ?? '';
        $this->editingSelectedStudents = $librarians->pluck('user_id')->map(fn($id) => (string) $id)->toArray();

        $this->resetErrorBag();
        $this->showEditModal = true;
    }

    public function saveBatchAssignment()
    {
        // Ensure only super admins can modify batch assignments
        $this->authorize('manage-librarian-batches');

        $this->validate([
            'editingBatchNo' => 'required',
            'editingDateStart' => 'nullable|date',
            'editingSelectedStudents' => 'required|array|size:5',
        ], [
            'editingSelectedStudents.size' => 'A batch must have exactly 5 students.',
        ]);

        try {
            DB::transaction(function () {
            $currentBatch = Librarian::where('batch_no', $this->editingBatchNo)->first();
            $currentBatchDate = $currentBatch ? $currentBatch->start_date : null;

            $isDateChanging = $currentBatchDate != $this->editingDateStart;
            if ($isDateChanging && $this->editingDateStart) {
                $conflictingBatch = Librarian::where('batch_no', '!=', $this->editingBatchNo)
                    ->whereNotNull('start_date')
                    ->where('start_date', $this->editingDateStart)
                    ->lockForUpdate()
                    ->first();

                if ($conflictingBatch) {
                    throw new \Exception("Cannot assign to this date. Batch No. {$conflictingBatch->batch_no} is already assigned to " . date('F j, Y', strtotime($this->editingDateStart)));
                }
            }

            $currentStudents = Librarian::where('batch_no', $this->editingBatchNo)->pluck('user_id');
            $newStudents = collect($this->editingSelectedStudents)->map(fn($id) => (int) $id);

            $studentsToRemove = $currentStudents->diff($newStudents);
            $studentsToAdd = $newStudents->diff($currentStudents);

            if ($studentsToAdd->isNotEmpty()) {
                $alreadyAssigned = Librarian::whereIn('user_id', $studentsToAdd)
                    ->where('batch_no', '!=', $this->editingBatchNo)
                    ->with('user')
                    ->get();

                if ($alreadyAssigned->isNotEmpty()) {
                    $studentNames = $alreadyAssigned->map(function ($lib) {
                        return $lib->user->first_name . ' ' . $lib->user->last_name . ' (Batch: ' . $lib->batch_no . ')';
                    })->join(', ');

                    throw new \Exception("The following students are already assigned to other batches: {$studentNames}");
                }
            }

            if ($studentsToRemove->isNotEmpty()) {
                Librarian::where('batch_no', $this->editingBatchNo)
                    ->whereIn('user_id', $studentsToRemove)
                    ->delete();
            }

            if ($studentsToAdd->isNotEmpty()) {
                foreach ($studentsToAdd as $userId) {
                    Librarian::create([
                        'user_id' => $userId,
                        'batch_no' => $this->editingBatchNo,
                        'status' => 'inactive',
                        'start_date' => $this->editingDateStart,
                        'end_date' => null,
                        'created_by' => Auth::id(),
                    ]);
                }
            }

            // Determine status based on date
            $status = 'inactive';
            if ($this->editingDateStart) {
                $today = date('Y-m-d');
                if ($this->editingDateStart == $today) {
                    $status = 'active';
                } elseif ($this->editingDateStart < $today) {
                    $status = 'expired';
                }
            }

            // Update batch details
            Librarian::where('batch_no', $this->editingBatchNo)->update([
                'start_date' => $this->editingDateStart,
                'end_date' => null,
                'shift_notes' => $this->editingShiftNotes,
                'status' => $status,
            ]);

            // If date is today, assign librarian role to all students in this batch
            if ($this->editingDateStart && $this->editingDateStart === date('Y-m-d')) {
                $librarianRoleId = \App\Models\Role::where('name', 'librarian')->value('id') ?? 2;
                User::whereIn('id', $this->editingSelectedStudents)
                    ->update(['role_id' => $librarianRoleId]);
            }
        });
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return;
        }


        $this->success('Batch assignment and members updated successfully! ');
        $this->showEditModal = false;
        $this->reset(['editingBatchNo', 'editingDateStart', 'editingShiftNotes', 'editingSelectedStudents']);
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-assign-librarians', [
            'groupedLibrarians' => $this->groupedLibrarians,
            'availableBatches' => $this->availableBatches,
            'assignedBatches' => $this->assignedBatches,
            'allBatches' => $this->allBatches,
            'availableStudents' => $this->availableStudents,
            'studentBatchAssignments' => $this->studentBatchAssignments,
            'conflictingBatch' => $this->conflictingBatch,
            'isDateChanging' => $this->isDateChanging
        ]);
    }
}
