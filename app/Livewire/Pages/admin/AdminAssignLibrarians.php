<?php

namespace App\Livewire\Pages\Admin;

use App\Models\Librarian;
use App\Models\User;
use Auth;
use Livewire\Component;
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

    public array $allBatchesHeaders = [
        ['key' => 'number', 'label' => '#'],
        ['key' => 'batch_no', 'label' => 'Batch no.'],
        ['key' => 'date_range', 'label' => 'Date to Serve?'],
        ['key' => 'shift_notes', 'label' => 'Shift notes'],
        ['key' => 'created_by', 'label' => 'Created by'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-20'],
    ];

    public array $simpleBatchHeaders = [
        ['key' => 'batch_no', 'label' => 'Batch no.'],
        ['key' => 'members', 'label' => 'Section & Year'],
    ];

    public array $assignedBatchHeaders = [
        ['key' => 'batch_no', 'label' => 'Batch no.'],
        ['key' => 'members', 'label' => 'Section & Year'],
        ['key' => 'date_assigned', 'label' => 'Date Assigned'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-20'],
    ];

    public function getLibrariansQueryProperty()
    {
        return Librarian::with(['user', 'createdBy'])
            ->whereNotNull('batch_no')
            ->get()
            ->groupBy('batch_no');
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
                    })->join('<br>'),
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

                // Check for match in student names within the batch
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
        return User::where('account_status', 'active')
            ->where('is_admin', false)
            ->select('id', 'first_name', 'last_name', 'email')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function resetFilters()
    {
        $this->reset(['batchSearch', 'filterStatus', 'filterDateStart']);
    }

    public function openCreateModal()
    {
        // Clear any previous form data and validation errors
        $this->reset(['selectedStudents', 'newBatchNo']);
        $this->resetErrorBag();
        $this->showCreateModal = true;
    }

    public function createBatch()
    {
        $this->validate([
            'newBatchNo' => 'required|unique:librarians,batch_no',
            'selectedStudents' => 'required|array|min:1',
        ]);

        foreach ($this->selectedStudents as $userId) {
            Librarian::create([
                'user_id' => $userId,
                'batch_no' => $this->newBatchNo,
                'status' => 'inactive',
                'expires_at' => now()->addDay(),
                'created_by' => Auth::id(),
            ]);
        }

        $this->success('Batch created successfully!');
        $this->showCreateModal = false;
        $this->reset(['selectedStudents', 'newBatchNo']);
    }

    public function openEditModal($batchNo)
    {
        // Retrieve all librarian records for the given batch number
        $librarians = Librarian::where('batch_no', $batchNo)->get();

        if ($librarians->isEmpty()) {
            $this->error('Batch not found.');
            return;
        }

        $first = $librarians->first();

        // 1. Initialize properties for the edit modal
        $this->editingBatchNo = $batchNo;
        $this->editingDateStart = $first->date_start ? date('Y-m-d', strtotime($first->date_start)) : '';
        $this->editingShiftNotes = $first->shift_notes ?? '';

        // 2. Load the IDs of the students currently in the batch
        $this->editingSelectedStudents = $librarians->pluck('user_id')->map(fn($id) => (string) $id)->toArray();

        // Clear any previous validation errors
        $this->resetErrorBag();
        $this->showEditModal = true;
    }

    public function saveBatchAssignment()
    {
        $this->validate([
            'editingBatchNo' => 'required',
            'editingDateStart' => 'required|date',
            'editingSelectedStudents' => 'required|array|min:1', // Add validation for students
        ]);

        $conflictingBatch = Librarian::where('batch_no', '!=', $this->editingBatchNo)
            ->whereNotNull('date_start')
            ->where('date_start', $this->editingDateStart) // Check for exact date match
            ->first();

        if ($conflictingBatch) {
            // Check if the current batch is already assigned to this date
            $isSameDate = $this->getLibrariansQueryProperty()->get($this->editingBatchNo)
                          ->first()
                          ->date_start == $this->editingDateStart;

            if (!$isSameDate) {
                $this->error("There is already a batch assigned on this date: Batch No. {$conflictingBatch->batch_no}");
                return;
            }
        }

        $currentStudents = Librarian::where('batch_no', $this->editingBatchNo)->pluck('user_id');
        $newStudents = collect($this->editingSelectedStudents)->map(fn($id) => (int) $id); // Ensure IDs are integers

        // Students to remove: currently in batch but not in new selection
        $studentsToRemove = $currentStudents->diff($newStudents);

        // Students to add: in new selection but not currently in batch
        $studentsToAdd = $newStudents->diff($currentStudents);

        // 1. Remove students (delete Librarian records)
        if ($studentsToRemove->isNotEmpty()) {
            Librarian::where('batch_no', $this->editingBatchNo)
                ->whereIn('user_id', $studentsToRemove)
                ->delete();
        }

        // 2. Add students (create new Librarian records)
        if ($studentsToAdd->isNotEmpty()) {
            foreach ($studentsToAdd as $userId) {
                Librarian::create([
                    'user_id' => $userId,
                    'batch_no' => $this->editingBatchNo,
                    'status' => 'inactive', // New additions start as inactive until assignment is saved/updated
                    'expires_at' => now()->addDay(),
                    'created_by' => Auth::id(),
                ]);
            }
        }

        // 3. Update date, notes, and status for remaining/added students
        Librarian::where('batch_no', $this->editingBatchNo)->update([
            'date_start' => $this->editingDateStart,
            'shift_notes' => $this->editingShiftNotes,
            'status' => 'active', // Mark all members of the batch as active upon assignment
        ]);

        $this->success('Batch assignment and members updated successfully! 🎉');
        $this->showEditModal = false;
        $this->reset(['editingBatchNo', 'editingDateStart', 'editingShiftNotes', 'editingSelectedStudents']);
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-assign-librarians', [
            'groupedLibrarians' => $this->getLibrariansQueryProperty(),
            'availableBatches' => $this->availableBatches,
            'assignedBatches' => $this->assignedBatches,
            'allBatches' => $this->allBatches,
            'availableStudents' => $this->availableStudents,
        ]);
    }
}
