<div>
    <header class="px-6 py-4 bg-base-200 shadow-md">
        <h1 class="text-2xl font-bold text-base-content">Admin - Manage User Roles</h1>
        <p class="text-sm text-base-content/60 mt-1">Assign Super Admin and Admin roles to users</p>
    </header>

    <div class="px-4 py-5 min-h-screen bg-base-100">
        <div class="bg-base-200 rounded-xl shadow-lg p-6">

            <!-- Filters -->
            <div class="mb-6 space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Search -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-base-content/70 mb-1">Search Users</label>
                        <div class="relative">
                            <input type="text" wire:model.live.debounce.300ms="search"
                                placeholder="Search by name or email..."
                                class="input input-bordered w-full text-sm pr-10">
                            <svg class="absolute right-3 top-3 w-4 h-4 text-base-content/50" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Role Filter -->
                    <div>
                        <label class="block text-xs font-medium text-base-content/70 mb-1">Filter by Role</label>
                        <select wire:model.live="filterRole" class="select select-bordered w-full text-sm">
                            <option value="">All Roles</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Clear Filters -->
                <div class="flex justify-end">
                    <button wire:click="resetFilters" class="btn btn-error text-white text-sm font-semibold">
                        Clear Filters
                    </button>
                </div>
            </div>

            <!-- Role Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                @foreach ($roles as $role)
                    <div class="bg-base-300 rounded-lg p-4 border border-base-content/10">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-base-content/70">{{ $role->display_name }}s</p>
                                <p class="text-2xl font-bold text-base-content">
                                    {{ $this->allUsers->where('role_id', $role->id)->count() }}
                                </p>
                            </div>
                            <div
                                class="p-3 rounded-full
                                {{ $role->name === 'super_admin' ? 'bg-red-500/20' : '' }}
                                {{ $role->name === 'admin' ? 'bg-purple-500/20' : '' }}
                                {{ $role->name === 'librarian' ? 'bg-blue-500/20' : '' }}
                                {{ $role->name === 'student' ? 'bg-green-500/20' : '' }}">
                                <svg class="w-6 h-6
                                    {{ $role->name === 'super_admin' ? 'text-red-400' : '' }}
                                    {{ $role->name === 'admin' ? 'text-purple-400' : '' }}
                                    {{ $role->name === 'librarian' ? 'text-blue-400' : '' }}
                                    {{ $role->name === 'student' ? 'text-green-400' : '' }}"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Users Table -->
            <div class="overflow-x-auto rounded-lg relative">
                <!-- Loading overlay for pagination -->
                <div wire:loading.delay wire:target="gotoPage, previousPage, nextPage"
                    class="absolute inset-0 bg-base-100/80 backdrop-blur-sm z-20 flex items-center justify-center rounded-lg">
                    <div class="flex flex-col items-center gap-3">
                        <span class="loading loading-spinner loading-lg text-primary"></span>
                        <p class="text-sm font-medium text-base-content">Loading users...</p>
                    </div>
                </div>

                <table class="table table-zebra w-full">
                    <thead class="bg-base-200 sticky top-0 z-10">
                        <tr class="text-base-content text-sm border-b border-base-300">
                            <th class="w-8">#</th>
                            <th>Name</th>
                            <th class="hidden sm:table-cell">Email</th>
                            <th>Current Role</th>
                            <th class="text-center w-20 sm:w-32">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-base-content text-sm">
                        @forelse($users as $index => $user)
                            <tr class="border-b border-base-300 hover:bg-base-300/30 transition">
                                <td class="text-base-content/60">
                                    {{ ($users->currentPage() - 1) * $users->perPage() + $index + 1 }}</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ $user->first_name }}
                                            {{ $user->last_name }}</span>
                                        @if ($user->id === Auth::id())
                                            <span class="badge badge-primary badge-sm">
                                                You
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="hidden sm:table-cell text-base-content/80 text-xs sm:text-sm">
                                    {{ $user->email }}</td>
                                <td class="py-2 px-2 sm:py-3 sm:px-4">
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-semibold
                                        {{ $user->role->name === 'super_admin' ? 'bg-red-500/20 text-red-400' : '' }}
                                        {{ $user->role->name === 'admin' ? 'bg-purple-500/20 text-purple-400' : '' }}
                                        {{ $user->role->name === 'librarian' ? 'bg-blue-500/20 text-blue-400' : '' }}
                                        {{ $user->role->name === 'student' ? 'bg-green-500/20 text-green-400' : '' }}">
                                        {{ $user->role->display_name }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button wire:click="openAssignRoleModal({{ $user->id }})"
                                        class="btn btn-ghost btn-sm text-primary inline-flex items-center gap-1"
                                        title="Change Role">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <span class="text-xs">Change</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-12 px-4 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <svg class="w-16 h-16 text-base-content/40" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                        <p class="text-base-content/60">No users found</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($users->hasPages())
                <div class="mt-6">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Assign Role Modal -->
    @if ($showAssignRoleModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-70 flex items-center justify-center p-4">
            <div
                class="inline-block align-bottom bg-base-200 rounded-lg text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full border border-base-300">
                <div class="bg-base-200 px-6 pt-5 pb-4">
                    <h3 class="text-lg font-medium text-base-content mb-4">Assign Role</h3>

                    @php
                        $user = $users->firstWhere('id', $selectedUserId);
                    @endphp

                    @if ($user)
                        <div class="space-y-4">
                            <!-- User Info -->
                            <div class="bg-base-300 p-3 rounded-lg border border-base-content/10">
                                <p class="text-sm text-base-content/70">User</p>
                                <p class="text-base-content font-medium">{{ $user->first_name }}
                                    {{ $user->last_name }}</p>
                                <p class="text-xs text-base-content/60">{{ $user->email }}</p>
                            </div>

                            <!-- Role Selection -->
                            <div>
                                <label class="block text-sm font-medium text-base-content mb-2">
                                    Select New Role
                                </label>
                                <div class="space-y-2">
                                    @foreach ($roles as $role)
                                        @php
                                            // Only super admins can assign admin and super_admin roles
                                            $canAssignRole =
                                                Auth::user()->isSuperAdmin() ||
                                                !in_array($role->name, ['admin', 'super_admin']);
                                        @endphp

                                        @if ($canAssignRole)
                                            <label
                                                class="flex items-start space-x-3 p-3 rounded-lg cursor-pointer transition
                                                {{ $selectedRoleId == $role->id ? 'bg-primary/20 border-2 border-primary' : 'bg-base-300 border border-base-content/10 hover:bg-base-300/70' }}">
                                                <input type="radio" wire:model.live="selectedRoleId"
                                                    value="{{ $role->id }}" class="radio radio-primary mt-1">
                                                <div class="flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="font-medium text-base-content">{{ $role->display_name }}</span>
                                                        @if (in_array($role->name, ['admin', 'super_admin']))
                                                            <span class="badge badge-warning badge-sm">
                                                                Super Admin Only
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <p class="text-xs text-base-content/60 mt-1">
                                                        {{ $role->description }}
                                                    </p>
                                                </div>
                                            </label>
                                        @endif
                                    @endforeach
                                </div>
                                @error('selectedRoleId')
                                    <span class="text-error text-xs">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Warning for self-demotion -->
                            @if ($user->id === Auth::id() && $selectedRoleId != $user->role_id)
                                <div class="alert alert-warning rounded-lg p-3">
                                    <p class="text-sm">⚠️ You cannot change your own role!</p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="bg-base-300 px-6 py-3 flex flex-row-reverse gap-2 rounded-b-lg">
                    <button type="button" wire:click="assignRole"
                        {{ $user && $user->id === Auth::id() && $selectedRoleId != $user->role_id ? 'disabled' : '' }}
                        class="btn btn-primary">
                        Assign Role
                    </button>
                    <button type="button" wire:click="$set('showAssignRoleModal', false)" class="btn btn-ghost">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
