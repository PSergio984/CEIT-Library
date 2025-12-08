<?php

namespace App\Livewire\Pages\Admin;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Lazy]
#[Title('User Role Management')]
class AdminManageRoles extends AdminComponent
{
    use AuthorizesRequests, Toast, WithPagination;

    public $search = '';

    public $filterRole = '';

    public $showAssignRoleModal = false;

    public $selectedUserId = null;

    public $selectedRoleId = null;

    public function mount()
    {
        // Only super admins can manage roles
        $this->authorize('manage-user-roles');
    }

    public function getUsersProperty()
    {
        $query = User::with('role')
            ->where('account_status', 'active');

        // Filter by role
        if ($this->filterRole) {
            $query->where('role_id', $this->filterRole);
        }

        // Search by name or email
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('first_name', 'like', '%'.$this->search.'%')
                    ->orWhere('last_name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        return $query->orderBy('role_id', 'desc')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(10);
    }

    public function getRolesProperty()
    {
        return Role::all();
    }

    public function getAllUsersProperty()
    {
        $query = User::with('role')
            ->where('account_status', 'active');

        // Apply same filters as paginated users
        if ($this->filterRole) {
            $query->where('role_id', $this->filterRole);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('first_name', 'like', '%'.$this->search.'%')
                    ->orWhere('last_name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }

        return $query->get();
    }

    public function openAssignRoleModal($userId)
    {
        $user = User::findOrFail($userId);

        $this->selectedUserId = $userId;
        $this->selectedRoleId = $user->role_id;
        $this->showAssignRoleModal = true;
    }

    public function assignRole()
    {
        $this->authorize('manage-user-roles');

        $this->validate([
            'selectedUserId' => 'required|exists:users,id',
            'selectedRoleId' => 'required|exists:roles,id',
        ]);

        $user = User::findOrFail($this->selectedUserId);
        $oldRole = $user->role->display_name ?? 'Unknown';
        $newRole = Role::findOrFail($this->selectedRoleId);

        // Prevent demoting yourself
        if ($user->id === Auth::id() && $newRole->name !== Role::SUPER_ADMIN) {
            $this->error('You cannot change your own role!');

            return;
        }

        // Only super admins can promote to admin or super_admin role
        if (in_array($newRole->name, [Role::ADMIN, Role::SUPER_ADMIN])) {
            if (! Auth::user()->isSuperAdmin()) {
                $this->error('Only Super Admins can promote users to Admin or Super Admin roles!');

                return;
            }
        }

        $user->update(['role_id' => $this->selectedRoleId]);

        // Create notification for role change
        $roleChangeMessage = "Your role has been changed from {$oldRole} to {$newRole->display_name}.";

        // Add specific message based on new role
        if ($newRole->name === Role::LIBRARIAN) {
            $roleChangeMessage .= ' You now have librarian privileges.';
        } elseif ($newRole->name === Role::ADMIN) {
            $roleChangeMessage .= ' You now have administrative privileges.';
        } elseif ($newRole->name === Role::SUPER_ADMIN) {
            $roleChangeMessage .= ' You now have full system access.';
        } elseif ($newRole->name === Role::STUDENT) {
            $roleChangeMessage .= ' Your privileges have been adjusted accordingly.';
        }

        Notification::create([
            'user_id' => $user->id,
            'type' => 'role_changed',
            'title' => 'Your Role Has Been Updated',
            'message' => $roleChangeMessage,
            'data' => [
                'old_role_id' => $user->role_id,
                'old_role_name' => $oldRole,
                'new_role_id' => $this->selectedRoleId,
                'new_role_name' => $newRole->display_name,
                'changed_by' => Auth::id(),
            ],
        ]);

        // Create notification for admin who made the change
        $adminMessage = "You successfully changed {$user->first_name} {$user->last_name}'s role from {$oldRole} to {$newRole->display_name}.";

        Notification::create([
            'user_id' => Auth::id(),
            'type' => 'user_activity',
            'title' => 'Role Change Completed',
            'message' => $adminMessage,
            'data' => [
                'target_user_id' => $user->id,
                'target_user_name' => $user->first_name.' '.$user->last_name,
                'old_role_id' => $user->role_id,
                'old_role_name' => $oldRole,
                'new_role_id' => $this->selectedRoleId,
                'new_role_name' => $newRole->display_name,
                'action' => 'role_change',
            ],
        ]);

        $this->success("Role updated: {$user->first_name} {$user->last_name} is now a {$newRole->display_name}");
        $this->showAssignRoleModal = false;
        $this->reset(['selectedUserId', 'selectedRoleId']);
    }

    public function resetFilters()
    {
        $this->reset(['search', 'filterRole']);
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterRole()
    {
        $this->resetPage();
    }

    /**
     * Placeholder shown while lazy loading the component
     */
    public function placeholder()
    {
        return view('components.loading-placeholder', [
            'message' => 'Loading role management...',
            'subtext' => 'Please wait while we fetch user roles',
        ]);
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-manage-roles', [
            'users' => $this->users,
            'roles' => $this->roles,
        ]);
    }
}
