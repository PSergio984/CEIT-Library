<?php

namespace App\Livewire\Pages\Admin;

use App\Models\Role;
use App\Models\User;
use Auth;
use DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Mary\Traits\Toast;

class AdminManageRoles extends AdminComponent
{
    use Toast, AuthorizesRequests;

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
                $q->where('first_name', 'like', '%' . $this->search . '%')
                    ->orWhere('last_name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        return $query->orderBy('role_id', 'desc')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function getRolesProperty()
    {
        return Role::all();
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

        $user->update(['role_id' => $this->selectedRoleId]);

        $this->success("Role updated: {$user->first_name} {$user->last_name} is now a {$newRole->display_name}");
        $this->showAssignRoleModal = false;
        $this->reset(['selectedUserId', 'selectedRoleId']);
    }

    public function resetFilters()
    {
        $this->reset(['search', 'filterRole']);
    }

    public function render()
    {
        return view('livewire.pages.admin.admin-manage-roles', [
            'users' => $this->users,
            'roles' => $this->roles,
        ]);
    }
}
