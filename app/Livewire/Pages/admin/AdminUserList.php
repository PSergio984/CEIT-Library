<?php

namespace App\Livewire\Pages\Admin;

use Livewire\WithPagination;
use App\Models\User;
use Mary\Traits\Toast;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;

#[Title('Admin User List')]
#[Lazy]
class AdminUserList extends AdminComponent
{
    use WithPagination, Toast, AuthorizesRequests;

    public $perPage = 20;
    public $search = '';
    public $statusFilter = '';
    public $creditScoreFilter = '';
    public $roleFilter = '';

    // Modal visibility
    public $showStudentModal = false;
    public $showEditModal = false;
    public $showDeleteModal = false;

    // Modal data properties (primitives only - no Eloquent models)
    public $selectedStudentId = null;
    public $selectedStudentData = [];

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
        // Get student role ID
        $studentRoleId = \App\Models\Role::where('name', 'student')->value('id') ?? 1;

        return User::query()
            ->with('role')
            ->select(['id', 'first_name', 'last_name', 'email', 'credit_score', 'account_status', 'role_id'])
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
            ->when($this->roleFilter !== '', function ($query) use ($studentRoleId) {
                if ($this->roleFilter === 'student') {
                    $query->where('role_id', $studentRoleId);
                } else {
                    $query->where('role_id', '!=', $studentRoleId);
                }
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

    #[Computed(persist: true, seconds: 5)]
    public function students()
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
                case 'status':
                    $query->orderBy('account_status', $direction);
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
                    'credit_score_color' => $this->getCreditScoreColor($user->credit_score),
                    'status' => $user->account_status,
                    'account_status_label' => $user->account_status === 'active' ? 'Available' : 'Suspended',
                    'is_admin' => $user->role && $user->role->hasAdminAccess(),
                ];
            });
    }

    #[Computed]
    public function totalStudents()
    {
        $cacheKey = 'admin_user_list_total_students_' . md5(serialize([
            $this->search,
            $this->statusFilter,
            $this->roleFilter,
            $this->creditScoreFilter,
        ]));

        return Cache::remember($cacheKey, 300, function () {
            return $this->getStudentsQuery()->count();
        });
    }

    #[Computed(persist: true, seconds: 60)]
    public function totalBorrowers()
    {
        return Cache::remember('admin_user_list_total_borrowers', 300, function () {
            $studentRoleId = \App\Models\Role::where('name', 'student')->value('id') ?? 1;
            return User::where('role_id', $studentRoleId)
                ->whereHas('borrowTransactions', function ($query) {
                    $query->where('status', 'started');
                })->count();
        });
    }

    public function showTransactionDetails($userId)
    {
        $student = User::with(['borrowTransactions' => function ($query) {
            $query->where('status', 'started')
                ->with('inventory.academicPaper')
                ->latest();
        }])->findOrFail($userId);

        // Store minimal data + transactions (primitives only)
        $this->selectedStudentData = [
            'id' => $student->id,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'email' => $student->email,
            'credit_score' => $student->credit_score,
            'account_status' => $student->account_status,
            'transactions' => $student->borrowTransactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'title' => $transaction->inventory?->academicPaper?->title ?? 'Unknown Title',
                    'paper_type' => $transaction->inventory?->academicPaper?->paper_type ?? 'N/A',
                    'time_in' => $transaction->time_in?->toIso8601String(),
                ];
            })->toArray(),
        ];
        $this->selectedStudentId = $userId;
        $this->showStudentModal = true;
        $this->skipRender();
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
        $this->isAdmin = $user->role_id;
        $this->showEditModal = true;
        $this->skipRender();
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
        ]);

        // Clear cache after update
        Cache::forget('admin_user_list_total_borrowers');
        $this->clearStatsCaches();

        $this->showEditModal = false;
        $this->success('Student updated successfully!');
        $this->reset(['studentId', 'firstName', 'lastName', 'email', 'creditScore', 'accountStatus', 'isAdmin']);
    }

    public function confirmDelete($userId)
    {
        $user = User::findOrFail($userId);
        $this->selectedStudentData = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        ];
        $this->selectedStudentId = $userId;
        $this->showDeleteModal = true;
        $this->skipRender();
    }

    public function deleteUser()
    {
        if ($this->selectedStudentId) {
            $student = User::findOrFail($this->selectedStudentId);

            // Check for active borrow transactions
            $hasActiveBorrows = $student->borrowTransactions()
                ->where(function ($q) {
                    $q->where('status', 'started')
                        ->orWhereNull('time_out');
                })
                ->exists();

            if ($hasActiveBorrows) {
                $this->error('Cannot delete student: active borrow transactions exist.');
                return;
            }

            $student->delete();

            // Clear cache after deletion
            Cache::forget('admin_user_list_total_borrowers');
            $this->clearStatsCaches();

            $this->showDeleteModal = false;
            $this->selectedStudentId = null;
            $this->selectedStudentData = [];
            $this->success('Student deleted successfully!');
        }
    }

    protected function clearStatsCaches()
    {
        // If using a taggable cache store, use tags for broad invalidation
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags(['admin_user_list_stats'])->flush();
        } else {
            // Fallback: clear known keys (not perfect, but avoids getIterator error)
            foreach (
                [
                    'admin_user_list_total_borrowers',
                    'admin_user_list_total_students_' . md5(serialize(['', '', '', ''])),
                    'admin_user_list_total_students_' . md5(serialize([$this->search, $this->statusFilter, $this->roleFilter, $this->creditScoreFilter])),
                ] as $key
            ) {
                Cache::forget($key);
            }
        }
    }

    public function closeModal()
    {
        $this->showStudentModal = false;
        $this->showEditModal = false;
        $this->showDeleteModal = false;
        $this->selectedStudentId = null;
        $this->selectedStudentData = [];
        $this->reset(['studentId', 'firstName', 'lastName', 'email', 'creditScore', 'accountStatus', 'isAdmin']);
        $this->skipRender();
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

    public function placeholder()
    {
        return <<<'HTML'
        <div class="p-6">
            <div class="flex items-center justify-center h-96">
                <span class="loading loading-spinner loading-lg"></span>
            </div>
        </div>
        HTML;
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-user-list');
    }
}
