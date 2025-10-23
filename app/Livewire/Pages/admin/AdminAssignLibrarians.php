<?php

namespace App\Livewire\Pages\Admin;

use App\Models\Librarian;
use App\Models\User;
use Auth;
use DB;
use Mary\Traits\Toast;

class AdminAssignLibrarians extends AdminComponent
{
    use Toast;

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
        $currentDate = $currentBatch ? date('Y-m-d', strtotime($currentBatch->date_start)) : null;

        return $currentDate != $this->editingDateStart && !empty($this->editingDateStart);
    }

    public function getConflictingBatchProperty()
    {
        if (!$this->isDateChanging || empty($this->editingDateStart)) {
            return null;
        }

        return Librarian::where('batch_no', '!=', $this->editingBatchNo)
            ->whereNotNull('date_start')
            ->where('date_start', $this->editingDateStart)
            ->first();
    }

    public function getAvailableBatchesProperty()
    {
        return $this->getLibrariansQueryProperty()
            ->filter(function ($librarians) {
                return is_null($librarians->first()->date_start);
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
                return !is_null($librarians->first()->date_start);
            })
            ->map(function ($librarians, $batchNo) {
                $first = $librarians->first();
                return [
                    'batch_no' => $batchNo,
                    'members' => $librarians->map(function ($lib) {
                        return $lib->user->first_name . ' ' . $lib->user->last_name;
                    })->join('<br>'),
                    'date_assigned' => $first->date_start ? date('Y-m-d', strtotime($first->date_start)) : 'N/A',
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

                if (is_null($first->date_start)) {
                    return false;
                }

                $batchStart = strtotime($first->date_start);

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
                'date_range' => ($first->date_start ? date('Y-m-d', strtotime($first->date_start)) : 'N/A'),
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

        return User::where('account_status', 'active')
            ->where('is_admin', false)
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

        $availableStudents = User::where('account_status', 'active')
            ->where('is_admin', false)
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
        $this->validate([
            'newBatchNo' => 'required|unique:librarians,batch_no',
            'selectedStudents' => 'required|array|min:1|max:5',
        ], [
            'selectedStudents.max' => 'A batch can only have a maximum of 5 students.',
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
                    'expires_at' => now()->addDay(),
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
        $this->editingDateStart = $first->date_start ? date('Y-m-d', strtotime($first->date_start)) : '';
        $this->editingShiftNotes = $first->shift_notes ?? '';
        $this->editingSelectedStudents = $librarians->pluck('user_id')->map(fn($id) => (string) $id)->toArray();

        $this->resetErrorBag();
        $this->showEditModal = true;
    }

    public function saveBatchAssignment()
    {
        $this->validate([
            'editingBatchNo' => 'required',
            'editingDateStart' => 'required|date',
            'editingSelectedStudents' => 'required|array|min:1|max:5',
        ], [
            'editingSelectedStudents.max' => 'A batch can only have a maximum of 5 students.',
            'editingSelectedStudents.min' => 'A batch must have at least 1 student.',
        ]);

        DB::transaction(function () {
            $currentBatch = Librarian::where('batch_no', $this->editingBatchNo)->first();
            $currentBatchDate = $currentBatch ? $currentBatch->date_start : null;

            $isDateChanging = $currentBatchDate != $this->editingDateStart;
            if ($isDateChanging) {
                $conflictingBatch = Librarian::where('batch_no', '!=', $this->editingBatchNo)
                    ->whereNotNull('date_start')
                    ->where('date_start', $this->editingDateStart)
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
                        'expires_at' => now()->addDay(),
                        'created_by' => Auth::id(),
                    ]);
                }
            }

            Librarian::where('batch_no', $this->editingBatchNo)->update([
                'date_start' => $this->editingDateStart,
                'shift_notes' => $this->editingShiftNotes,
                'status' => 'active',
            ]);
        });

        try {
            DB::transaction(fn() => null);
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
            // ConflictingBatch is made available for blade conditional rendering
            'conflictingBatch' => $this->conflictingBatch,
            // isDateChanging is made available for blade conditional rendering
            'isDateChanging' => $this->isDateChanging
        ]);
    }
}
