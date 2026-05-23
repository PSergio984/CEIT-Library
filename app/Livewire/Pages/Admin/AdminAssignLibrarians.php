<?php

namespace App\Livewire\Pages\Admin;

use App\Models\Librarian;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Auth;
use DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Mary\Traits\Toast;

#[Title('Librarian Batch Management')]
#[Lazy]
class AdminAssignLibrarians extends AdminComponent
{
    use AuthorizesRequests, Toast;

    #[Validate('string|max:100|nullable')]
    public $search = '';

    #[Validate('string|max:100|nullable')]
    public $batchSearch = '';

    #[Validate('string|max:100|nullable')]
    public $editModalSearch = '';

    public $showCreateModal = false;

    public $showEditModal = false;

    public $selectedStudents = [];

    public $editingBatchNo = null;

    public $editingDateStart = null;

    public $editingShiftNotes = '';

    public $editingSelectedStudents = [];

    public $newBatchNo = '';

    public $selectedDate = '';

    #[Validate('string|max:20|nullable')]
    public $filterStatus = '';

    public $filterDateStart = null;

    protected $listeners = ['batchUpdated' => 'render'];

    public function mount()
    {
        $this->authorizeAccess();

        // Authorize that only super admins can access this page
        $this->authorize('manage-librarian-batches');

        // Automatically update batch statuses based on current date
        $this->updateBatchStatuses();
    }

    /**
     * Placeholder shown while lazy loading the component
     */
    public function placeholder()
    {
        return view('components.loading-placeholder', [
            'message' => 'Loading Librarian Batches...',
            'subtext' => 'Please wait while we fetch the data',
        ]);
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

        return $currentDate != $this->editingDateStart && ! empty($this->editingDateStart);
    }

    public function getConflictingBatchProperty()
    {
        if (! $this->isDateChanging || empty($this->editingDateStart)) {
            return null;
        }

        return Librarian::where('batch_no', '!=', $this->editingBatchNo)
            ->whereNotNull('start_date')
            ->where('start_date', $this->editingDateStart)
            ->first();
    }

    public function getIsSundayProperty()
    {
        if (empty($this->editingDateStart)) {
            return false;
        }

        $date = new \DateTime($this->editingDateStart);

        return $date->format('w') == '0';
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
                        return $lib->user->first_name.' '.$lib->user->last_name;
                    })->toArray(),
                    'librarians' => $librarians,
                ];
            });
    }

    public function getAssignedBatchesProperty()
    {
        return $this->getLibrariansQueryProperty()
            ->filter(function ($librarians) {
                return ! is_null($librarians->first()->start_date);
            })
            ->map(function ($librarians, $batchNo) {
                $first = $librarians->first();

                return [
                    'batch_no' => $batchNo,
                    'members' => $librarians->map(function ($lib) {
                        return $lib->user->first_name.' '.$lib->user->last_name;
                    })->toArray(),
                    'date_assigned' => $first->start_date ? date('Y-m-d', strtotime($first->start_date)) : 'N/A',
                    'librarians' => $librarians,
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

                $createdBy = strtolower(($first->createdBy->first_name ?? '').' '.($first->createdBy->last_name ?? ''));
                $shiftNotes = strtolower($first->shift_notes ?? '');

                $studentMatch = $librarians->contains(function ($lib) use ($search) {
                    $fullName = strtolower($lib->user->first_name.' '.$lib->user->last_name);

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
                'created_by' => ($first->createdBy->first_name ?? '').' '.($first->createdBy->last_name ?? ''),
                'status' => $first->status,
                'librarians' => $librarians,
                'first' => $first,
            ];
        })->values();
    }

    public function getAvailableStudentsProperty()
    {
        $usedUserIds = Librarian::pluck('user_id')->toArray();

        // Get student role ID
        $studentRoleId = Role::where('name', 'student')->value('id');

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
        // Get users in OTHER batches (not the one being edited)
        $usedUserIds = Librarian::where('batch_no', '!=', $this->editingBatchNo ?? '')
            ->pluck('user_id')
            ->toArray();

        // Get users in the CURRENT batch being edited
        $currentBatchUserIds = Librarian::where('batch_no', $this->editingBatchNo ?? '')
            ->pluck('user_id')
            ->toArray();

        // Get student and librarian role IDs
        $studentRoleId = Role::where('name', 'student')->value('id');
        $librarianRoleId = Role::where('name', 'librarian')->value('id');

        // Get available students: either students not in any batch, OR users in the current batch (can be librarians)
        // No search or sorting here - Alpine.js handles that client-side for instant response
        $availableStudents = User::where('account_status', 'active')
            ->where(function ($query) use ($studentRoleId, $librarianRoleId, $currentBatchUserIds) {
                $query->where('role_id', $studentRoleId)
                    ->orWhere(function ($q) use ($librarianRoleId, $currentBatchUserIds) {
                        $q->where('role_id', $librarianRoleId)
                            ->whereIn('id', $currentBatchUserIds);
                    });
            })
            ->whereNotIn('id', $usedUserIds)
            ->select('id', 'first_name', 'last_name', 'email', 'role_id')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return $availableStudents;
    }

    public function resetFilters()
    {
        $this->reset(['batchSearch', 'filterStatus', 'filterDateStart']);
    }

    public function generateNextBatchNumber()
    {
        $currentYear = date('Y');
        $yearPrefix = $currentYear;

        // Get the highest batch number for the current year
        $latestBatch = Librarian::where('batch_no', 'like', $yearPrefix.'%')
            ->orderBy('batch_no', 'desc')
            ->first();

        if ($latestBatch) {
            // Extract the sequence number and increment
            $sequenceNumber = (int) substr($latestBatch->batch_no, 4) + 1;
        } else {
            // First batch of the year
            $sequenceNumber = 1;
        }

        // Format: YYYY0001, YYYY0002, etc.
        return $yearPrefix.str_pad($sequenceNumber, 4, '0', STR_PAD_LEFT);
    }

    public function openCreateModal()
    {
        $this->reset(['selectedStudents']);
        $this->newBatchNo = $this->generateNextBatchNumber();
        $this->resetErrorBag();
        $this->showCreateModal = true;
    }

    public function createBatch()
    {
        // Ensure only super admins can create batches and assign librarians
        $this->authorize('manage-librarian-batches');

        // Generate batch number if not already set
        if (empty($this->newBatchNo)) {
            $this->newBatchNo = $this->generateNextBatchNumber();
        }

        $this->validate([
            'newBatchNo' => 'required|integer|unique:librarians,batch_no',
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
                    return $lib->user->first_name.' '.$lib->user->last_name.' (Batch: '.$lib->batch_no.')';
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

                // Create notification for the assigned student
                Notification::create([
                    'user_id' => $userId,
                    'type' => 'librarian_assigned',
                    'title' => 'You have been assigned as a Librarian',
                    'message' => "You have been assigned to librarian batch #{$this->newBatchNo}. You will be notified when your batch becomes active.",
                    'data' => [
                        'batch_no' => $this->newBatchNo,
                        'assigned_by' => Auth::id(),
                    ],
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
        $this->editingSelectedStudents = $librarians->pluck('user_id')->map(fn ($id) => (string) $id)->toArray();
        $this->editModalSearch = ''; // Reset search when opening modal

        $this->resetErrorBag();
        $this->showEditModal = true;
    }

    public function saveBatchAssignment()
    {
        // Ensure only super admins can modify batch assignments
        $this->authorize('manage-librarian-batches');

        // Custom validation for Sunday
        if ($this->editingDateStart) {
            $date = new \DateTime($this->editingDateStart);
            if ($date->format('w') == '0') {
                $this->error('Sundays are not allowed for librarian duty', 'Invalid Date');

                return;
            }
        }

        $this->validate([
            'editingBatchNo' => 'required',
            'editingDateStart' => 'nullable|date|after_or_equal:today',
            'editingSelectedStudents' => 'required|array|size:5',
        ], [
            'editingSelectedStudents.size' => 'A batch must have exactly 5 students.',
            'editingDateStart.after_or_equal' => 'The start date cannot be in the past.',
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
                        throw new \Exception("Cannot assign to this date. Batch No. {$conflictingBatch->batch_no} is already assigned to ".date('F j, Y', strtotime($this->editingDateStart)));
                    }
                }

                $currentStudents = Librarian::where('batch_no', $this->editingBatchNo)->pluck('user_id');
                $newStudents = collect($this->editingSelectedStudents)->map(fn ($id) => (int) $id);

                $studentsToRemove = $currentStudents->diff($newStudents);
                $studentsToAdd = $newStudents->diff($currentStudents);

                if ($studentsToAdd->isNotEmpty()) {
                    $alreadyAssigned = Librarian::whereIn('user_id', $studentsToAdd)
                        ->where('batch_no', '!=', $this->editingBatchNo)
                        ->with('user')
                        ->get();

                    if ($alreadyAssigned->isNotEmpty()) {
                        $studentNames = $alreadyAssigned->map(function ($lib) {
                            return $lib->user->first_name.' '.$lib->user->last_name.' (Batch: '.$lib->batch_no.')';
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

                        // Create notification for newly added students
                        if ($this->editingDateStart) {
                            $dutyDate = date('F j, Y', strtotime($this->editingDateStart));
                            $message = "You have been assigned to librarian batch #{$this->editingBatchNo}. Your duty date is scheduled for {$dutyDate}.";
                        } else {
                            $message = "You have been assigned to librarian batch #{$this->editingBatchNo}. You will be notified when your duty date is scheduled.";
                        }

                        Notification::create([
                            'user_id' => $userId,
                            'type' => 'librarian_assigned',
                            'title' => 'You have been assigned as a Librarian',
                            'message' => $message,
                            'data' => [
                                'batch_no' => $this->editingBatchNo,
                                'assigned_by' => Auth::id(),
                                'start_date' => $this->editingDateStart,
                            ],
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

                // Check if duty date is being assigned/changed for existing members
                $isDateChanging = $this->isDateChanging;
                $previousDate = $currentBatch ? $currentBatch->start_date : null;

                // Update batch details
                Librarian::where('batch_no', $this->editingBatchNo)->update([
                    'start_date' => $this->editingDateStart,
                    'end_date' => null,
                    'shift_notes' => $this->editingShiftNotes,
                    'status' => $status,
                ]);

                // Notify existing members if duty date is being assigned or changed
                if ($isDateChanging && $this->editingDateStart && $currentStudents->isNotEmpty()) {
                    $dutyDate = date('F j, Y', strtotime($this->editingDateStart));

                    foreach ($currentStudents as $userId) {
                        // Skip users who were just added (they already got notified above)
                        if ($studentsToAdd->contains($userId)) {
                            continue;
                        }

                        $notificationMessage = $previousDate
                            ? "Your duty date for librarian batch #{$this->editingBatchNo} has been updated to {$dutyDate}."
                            : "Your duty date for librarian batch #{$this->editingBatchNo} has been scheduled for {$dutyDate}.";

                        Notification::create([
                            'user_id' => $userId,
                            'type' => 'librarian_assigned',
                            'title' => 'Librarian Duty Date Updated',
                            'message' => $notificationMessage,
                            'data' => [
                                'batch_no' => $this->editingBatchNo,
                                'start_date' => $this->editingDateStart,
                                'previous_date' => $previousDate,
                                'updated_by' => Auth::id(),
                            ],
                        ]);
                    }
                }

                // If date is today, assign librarian role to all students in this batch
                if ($this->editingDateStart && $this->editingDateStart === date('Y-m-d')) {
                    $librarianRoleId = Role::where('name', 'librarian')->value('id') ?? 2;
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

    /**
     * Update all batch statuses based on current date
     */
    public function updateBatchStatuses()
    {
        $today = date('Y-m-d');

        DB::transaction(function () use ($today) {
            // Get student and librarian role IDs
            $studentRoleId = Role::where('name', 'student')->value('id') ?? 1;
            $librarianRoleId = Role::where('name', 'librarian')->value('id') ?? 2;

            // Update INACTIVE batches to ACTIVE if their start date is today
            $inactiveBatches = Librarian::where('status', 'inactive')
                ->whereNotNull('start_date')
                ->where('start_date', '<=', $today)
                ->get()
                ->groupBy('batch_no');

            foreach ($inactiveBatches as $batchNo => $librarians) {
                // Update batch status to active
                Librarian::where('batch_no', $batchNo)->update(['status' => 'active']);

                // Change user roles to librarian
                $userIds = $librarians->pluck('user_id');
                User::whereIn('id', $userIds)->update(['role_id' => $librarianRoleId]);

                // Send notification to each librarian that their batch is now active
                foreach ($librarians as $librarian) {
                    $dutyDate = date('F j, Y', strtotime($librarian->start_date));
                    Notification::create([
                        'user_id' => $librarian->user_id,
                        'type' => 'librarian_activated',
                        'title' => 'Your Librarian Batch is Now Active',
                        'message' => "Your librarian batch #{$batchNo} is now active. Your duty date is today, {$dutyDate}. You can now perform librarian duties.",
                        'data' => [
                            'batch_no' => $batchNo,
                            'start_date' => $librarian->start_date,
                            'end_date' => $librarian->end_date,
                        ],
                    ]);
                }
            }

            // Update ACTIVE batches to EXPIRED if their end date has passed OR if it's past their start date
            // (assuming batches are for one day only if no end_date is set)
            $activeBatches = Librarian::where('status', 'active')
                ->whereNotNull('start_date')
                ->where(function ($query) use ($today) {
                    // Either has end_date in the past, or start_date is before today (one-day duty)
                    $query->where(function ($q) use ($today) {
                        $q->whereNotNull('end_date')
                            ->where('end_date', '<', $today);
                    })
                        ->orWhere(function ($q) use ($today) {
                            $q->whereNull('end_date')
                                ->where('start_date', '<', $today);
                        });
                })
                ->get()
                ->groupBy('batch_no');

            foreach ($activeBatches as $batchNo => $librarians) {
                // Update batch status to expired
                Librarian::where('batch_no', $batchNo)->update(['status' => 'expired']);

                // Change user roles back to student
                $userIds = $librarians->pluck('user_id');
                User::whereIn('id', $userIds)
                    ->where('role_id', $librarianRoleId)
                    ->update(['role_id' => $studentRoleId]);
            }
        });
    }

    public function render()
    {
        // Update statuses on every render to ensure fresh data
        $this->updateBatchStatuses();

        return view('livewire.pages.admin.admin-assign-librarians', [
            'groupedLibrarians' => $this->groupedLibrarians,
            'availableBatches' => $this->availableBatches,
            'assignedBatches' => $this->assignedBatches,
            'allBatches' => $this->allBatches,
            'availableStudents' => $this->availableStudents,
            'studentBatchAssignments' => $this->studentBatchAssignments,
            'conflictingBatch' => $this->conflictingBatch,
            'isDateChanging' => $this->isDateChanging,
        ]);
    }
}
