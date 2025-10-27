<?php

namespace App\Livewire\Pages\Admin;

use Livewire\WithPagination;
use App\Models\User;
use Mary\Traits\Toast;
use Livewire\Attributes\Title;

#[Title('Admin User List')]
class AdminUserList extends AdminComponent
{
    use WithPagination, Toast;

    public $perPage = 20;
    public $search = '';
    public $statusFilter = '';
    public $creditScoreFilter = '';
    public $roleFilter = '';

    public $showStudentModal = false;
    public $showEditModal = false;
    public $showDeleteModal = false;
    public $selectedStudent = null;

    public $studentId;
    public $firstName;
    public $lastName;
    public $email;
    public $creditScore;
    public $accountStatus;
    public $isAdmin;

    public array $headers = [
        ['key' => 'id', 'label' => '#', 'class' => 'w-12'],
        ['key' => 'name', 'label' => 'Student Name', 'sortable' => true, 'class' => 'min-w-48'],
        ['key' => 'email', 'label' => 'Email', 'sortable' => true, 'class' => 'min-w-48'],
        ['key' => 'credit_score', 'label' => 'Credit Score', 'sortable' => true, 'class' => 'w-32'],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'class' => 'w-32'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'w-32'],
    ];

    // Default sort configuration
    public const DEFAULT_SORT = ['column' => 'created_at', 'direction' => 'desc'];

    public array $sortBy = self::DEFAULT_SORT;

    // Credit score thresholds
    public const CREDIT_SCORE_HIGH = 75;
    public const CREDIT_SCORE_MEDIUM = 50;

    /**
     * Get the color for a given credit score ('success', 'warning', 'error')
     */
    public function getCreditScoreColor($score): string
    {
        if ($score >= self::CREDIT_SCORE_HIGH) {
            return 'success';
        } elseif ($score >= self::CREDIT_SCORE_MEDIUM) {
            return 'warning';
        }
        return 'error';
    }

    protected function getStudentsQuery()
    {
        return User::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('account_status', $this->statusFilter);
            })
            ->when($this->roleFilter !== '', function ($query) {
                $query->where('is_admin', $this->roleFilter === 'admin');
            })
            ->when($this->creditScoreFilter, function ($query) {
                switch ($this->creditScoreFilter) {
                    case 'high':
                        $query->where('credit_score', '>=', self::CREDIT_SCORE_HIGH);
                        break;
                    case 'medium':
                        $query->whereBetween('credit_score', [self::CREDIT_SCORE_MEDIUM, self::CREDIT_SCORE_HIGH - 1]);
                        break;
                    case 'low':
                        $query->where('credit_score', '<', self::CREDIT_SCORE_MEDIUM);
                        break;
                }
            });
    }

    public function getStudentsProperty()
    {
        $query = $this->getStudentsQuery();

        if (isset($this->sortBy['column']) && isset($this->sortBy['direction'])) {
            $column = $this->sortBy['column'];
            $direction = $this->sortBy['direction'];

            switch ($column) {
                case 'name':
                    $query->orderBy('first_name', $direction)
                        ->orderBy('last_name', $direction);
                    break;
                default:
                    $query->orderBy($column, $direction);
            }
        }

        return $query->paginate($this->perPage)
            ->through(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => trim($user->first_name . ' ' . $user->last_name),
                    'email' => $user->email,
                    'credit_score' => $user->credit_score,
                    'status' => $user->account_status,
                    'account_status_label' => $user->account_status === 'active' ? 'Available' : 'Suspended',
                    'is_admin' => $user->is_admin,
                    'original' => $user,
                ];
            });
    }

    public function getTotalStudentsProperty()
    {
        return $this->getStudentsQuery()->count();
    }

    public function getTotalBorrowersProperty()
    {
        return User::where('is_admin', false)
            ->whereHas('borrowTransactions', function ($query) {
                $query->where('status', 'started');
            })->count();
    }

    public function showTransactionDetails($userId)
    {
        $this->selectedStudent = User::with(['borrowTransactions' => function ($query) {
            $query->where('status', 'started')
                ->with('inventory.academicPaper')
                ->latest();
        }])->find($userId);
        $this->showStudentModal = true;
    }

    public function editStudent($userId)
    {
        $user = User::findOrFail($userId);
        $this->studentId = $user->id;
        $this->firstName = $user->first_name;
        $this->lastName = $user->last_name;
        $this->email = $user->email;
        $this->creditScore = $user->credit_score;
        $this->accountStatus = $user->account_status;
        $this->isAdmin = $user->is_admin;
        $this->showEditModal = true;
    }

    public function saveChanges()
    {
        $this->validate([
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $this->studentId,
            'creditScore' => 'required|integer|min:0|max:100',
            'accountStatus' => 'required|in:active,suspended',
        ]);

        $user = User::findOrFail($this->studentId);
        $user->update([
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'credit_score' => $this->creditScore,
            'account_status' => $this->accountStatus,
            'is_admin' => $this->isAdmin,
        ]);

        $this->showEditModal = false;
        $this->success('Student updated successfully!');
        $this->reset(['studentId', 'firstName', 'lastName', 'email', 'creditScore', 'accountStatus', 'isAdmin']);
    }

    public function confirmDelete($userId)
    {
        $this->selectedStudent = User::findOrFail($userId);
        $this->showDeleteModal = true;
    }

    public function deleteUser()
    {
        if ($this->selectedStudent) {
            // Check for active borrow transactions (status = 'started' or returned_at/time_out is null)
            $hasActiveBorrows = $this->selectedStudent->borrowTransactions()
                ->where(function ($q) {
                    $q->where('status', 'started')
                        ->orWhereNull('time_out');
                })
                ->exists();

            if ($hasActiveBorrows) {
                $this->error('Cannot delete student: active borrow transactions exist.');
                return;
            }

            // If using SoftDeletes, prefer soft delete here
            $this->selectedStudent->delete();
            $this->showDeleteModal = false;
            $this->selectedStudent = null;
            $this->success('Student deleted successfully!');
        }
    }

    public function closeModal()
    {
        $this->showStudentModal = false;
        $this->showEditModal = false;
        $this->showDeleteModal = false;
        $this->selectedStudent = null;
        $this->reset(['studentId', 'firstName', 'lastName', 'email', 'creditScore', 'accountStatus', 'isAdmin']);
    }

    // Filter methods
    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingCreditScoreFilter()
    {
        $this->resetPage();
    }

    public function updatingRoleFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->creditScoreFilter = '';
        $this->roleFilter = '';
        $this->sortBy = self::DEFAULT_SORT;
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-user-list');
    }
}
