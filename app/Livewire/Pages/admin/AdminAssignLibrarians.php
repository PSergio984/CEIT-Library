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
    public $editingDateEnd = null;
    public $editingShiftNotes = '';

    public $newBatchNo = '';
    public $selectedDate = '';

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

        if ($this->batchSearch) {
            $grouped = $grouped->filter(function ($librarians, $batchNo) {
                return stripos($batchNo, $this->batchSearch) !== false;
            });
        }

        return $grouped->map(function ($librarians, $batchNo) {
            $first = $librarians->first();
            return [
                'batch_no' => $batchNo,
                'date_range' => ($first->date_start ?? 'N/A') . ' - ' . ($first->date_end ?? 'N/A'),
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
            ->where('is_admin', false) // Exclude admins
            ->select('id', 'first_name', 'last_name', 'email')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function openCreateModal()
    {
        $this->showCreateModal = true;
        $this->selectedStudents = [];
        $this->newBatchNo = '';
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
        $librarians = $this->getLibrariansQueryProperty()->get($batchNo);
        $first = $librarians->first();

        $this->editingBatchNo = $batchNo;
        $this->editingDateStart = $first->date_start ? date('Y-m-d', strtotime($first->date_start)) : '';
        $this->editingDateEnd = $first->date_end ? date('Y-m-d', strtotime($first->date_end)) : '';
        $this->editingShiftNotes = $first->shift_notes ?? '';
        $this->showEditModal = true;
    }

    public function saveBatchAssignment()
    {
        $this->validate([
            'editingDateStart' => 'required|date',
            'editingDateEnd' => 'required|date|after_or_equal:editingDateStart',
        ]);

        $conflictingBatch = Librarian::where('batch_no', '!=', $this->editingBatchNo)
            ->where(function ($query) {
                $query->whereBetween('date_start', [$this->editingDateStart, $this->editingDateEnd])
                    ->orWhereBetween('date_end', [$this->editingDateStart, $this->editingDateEnd])
                    ->orWhere(function ($q) {
                        $q->where('date_start', '<=', $this->editingDateStart)
                          ->where('date_end', '>=', $this->editingDateEnd);
                    });
            })
            ->first();

        if ($conflictingBatch) {
            $this->error("There is already a batch assigned on this date: Batch No. {$conflictingBatch->batch_no}");
            return;
        }

        Librarian::where('batch_no', $this->editingBatchNo)->update([
            'date_start' => $this->editingDateStart,
            'date_end' => $this->editingDateEnd,
            'shift_notes' => $this->editingShiftNotes,
            'status' => 'active',
        ]);

        $this->success('Batch assigned successfully!');
        $this->showEditModal = false;
        $this->reset(['editingBatchNo', 'editingDateStart', 'editingDateEnd', 'editingShiftNotes']);
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
