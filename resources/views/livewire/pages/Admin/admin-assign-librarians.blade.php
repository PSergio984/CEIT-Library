<div>
    <header class="px-6 py-4 shadow-md">
        <h1 class="text-2xl font-bold text-white">Admin - Librarian Batch Assignments</h1>
        <p class="text-sm text-slate-400 mt-1">Assign students to librarian duty batches for scheduled shifts</p>
    </header>

    <div class="px-4 py-5 min-h-screen">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

            <div class="flex flex-col space-y-4 lg:h-[calc(100vh-100px)]">

                <div class="bg-slate-700/50 backdrop-blur-sm rounded-xl shadow-lg p-4 flex-shrink-0">
                    <h2 class="text-white text-lg font-semibold mb-3">Create a Batch</h2>
                    <div class="space-y-4">
                        <button wire:click.prevent="openCreateModal"
                            class="w-full bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 shadow-md transform hover:scale-[1.01]">
                            Create New Batch
                        </button>
                    </div>
                </div>

                <div class="bg-slate-700/50 backdrop-blur-sm rounded-xl shadow-lg p-4 flex-grow flex flex-col min-h-0">
                    <h2 class="text-white text-lg font-semibold mb-3">Available Batches (Unassigned)</h2>
                    <div class="overflow-y-auto flex-1 rounded-lg">
                        <div class="space-y-3">
                            @forelse($availableBatches as $batch)
                                <div class="border border-slate-600 rounded-lg p-4 hover:bg-slate-600/30 transition">
                                    <div class="flex items-start justify-between mb-2">
                                        <span
                                            class="font-mono font-bold text-blue-400 text-lg">{{ $batch['batch_no'] }}</span>
                                        <button wire:click.prevent="openEditModal('{{ $batch['batch_no'] }}')"
                                            class="text-blue-400 hover:text-blue-300 p-2 rounded-lg hover:bg-slate-600/50 transition"
                                            title="Assign Date">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($batch['members'] as $member)
                                            <span class="px-2 py-1 bg-slate-600/50 text-slate-200 rounded text-xs">
                                                {{ $member }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-12">
                                    <svg class="w-16 h-16 mx-auto mb-3 text-slate-500" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                    </svg>
                                    <p class="text-slate-400">No unassigned batches</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="bg-slate-700/50 backdrop-blur-sm rounded-xl shadow-lg p-4 flex-grow flex flex-col min-h-0">
                    <h2 class="text-white text-lg font-semibold mb-3">Assigned Batches</h2>
                    <div class="overflow-y-auto flex-1 rounded-lg">
                        <div class="space-y-3">
                            @forelse($assignedBatches as $batch)
                                <div class="border border-slate-600 rounded-lg p-4 hover:bg-slate-600/30 transition">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <span
                                                class="font-mono font-bold text-blue-400 text-lg block">{{ $batch['batch_no'] }}</span>
                                            <span class="text-xs text-green-400 flex items-center gap-1 mt-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                {{ $batch['date_assigned'] }}
                                            </span>
                                        </div>
                                        <button wire:click.prevent="openEditModal('{{ $batch['batch_no'] }}')"
                                            class="text-blue-400 hover:text-blue-300 p-2 rounded-lg hover:bg-slate-600/50 transition"
                                            title="Edit Assignment">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($batch['members'] as $member)
                                            <span class="px-2 py-1 bg-slate-600/50 text-slate-200 rounded text-xs">
                                                {{ $member }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-12">
                                    <svg class="w-16 h-16 mx-auto mb-3 text-slate-500" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p class="text-slate-400">No assigned batches</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div
                    class="bg-slate-700/50 backdrop-blur-sm rounded-xl shadow-lg p-4 flex flex-col lg:h-[calc(100vh-100px)]">
                    <h2 class="text-white text-lg font-semibold mb-3">All Batches</h2>

                    <div class="mb-4 space-y-3 flex-shrink-0">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label for="filterStatus"
                                    class="block text-xs font-medium text-slate-400 mb-1">Status</label>
                                <select id="filterStatus" wire:model.live="filterStatus"
                                    class="w-full bg-slate-800 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 border border-slate-600">
                                    <option value="">All</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="expired">Expired</option>
                                </select>
                            </div>

                            <div>
                                <label for="filterDateStart"
                                    class="block text-xs font-medium text-slate-400 mb-1">Date</label>
                                <input type="date" id="filterDateStart" wire:model.live="filterDateStart"
                                    class="w-full bg-slate-800 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 border border-slate-600">
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <input type="text" wire:model.live.debounce.300ms="batchSearch"
                                    placeholder="Search Batch no., notes, creator, or student name..."
                                    class="w-full bg-slate-800 text-white text-sm rounded-lg px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-blue-500 border border-slate-600">
                                <svg class="absolute right-3 top-2.5 w-4 h-4 text-slate-400" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>

                            <button wire:click="resetFilters"
                                class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold py-2 px-4 rounded-lg transition duration-200 shadow-md whitespace-nowrap">
                                Clear
                            </button>
                        </div>
                    </div>


                    <div class="overflow-y-auto flex-1 rounded-lg">
                        <table class="w-full table-auto">
                            <thead class="sticky top-0 bg-slate-700/90 backdrop-blur-sm z-10">
                                <tr class="text-slate-300 text-sm border-b border-slate-600">
                                    <th class="text-left py-2 px-2 w-8">#</th>
                                    <th class="text-left py-2 px-2">Batch no.</th>
                                    <th class="text-left py-2 px-2">Date</th>
                                    <th class="text-left py-2 px-2">Notes</th>
                                    <th class="text-left py-2 px-2">Created by</th>
                                    <th class="text-left py-2 px-2">Status</th>
                                    <th class="text-center py-2 px-2 w-16">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-white text-sm">
                                @forelse($allBatches as $index => $batch)
                                    <tr class="border-b border-slate-600 hover:bg-slate-600/30 transition">
                                        <td class="py-2.5 px-2 text-slate-400">{{ $index + 1 }}</td>
                                        <td class="py-2.5 px-2">
                                            <span
                                                class="font-mono font-semibold text-blue-400">{{ $batch['batch_no'] }}</span>
                                        </td>
                                        <td class="py-2.5 px-2">
                                            @if ($batch['date_range'] !== 'N/A')
                                                <span class="text-green-400">{{ $batch['date_range'] }}</span>
                                            @else
                                                <span class="text-slate-500 italic">Not set</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 px-2 max-w-[200px]">
                                            <div class="truncate text-slate-400" title="{{ $batch['shift_notes'] }}">
                                                {{ $batch['shift_notes'] }}
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-2 text-slate-300">{{ $batch['created_by'] }}</td>
                                        <td class="py-2.5 px-2">
                                            <span
                                                class="px-2 py-1 rounded-full text-xs font-semibold
                                                {{ $batch['status'] === 'active'
                                                    ? 'bg-green-500/20 text-green-400'
                                                    : ($batch['status'] === 'inactive'
                                                        ? 'bg-yellow-500/20 text-yellow-400'
                                                        : 'bg-red-500/20 text-red-400') }}">
                                                {{ ucfirst($batch['status']) }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-2 text-center">
                                            <button wire:click.prevent="openEditModal('{{ $batch['batch_no'] }}')"
                                                class="text-blue-400 hover:text-blue-300 p-1.5 rounded-lg hover:bg-slate-600/50 transition"
                                                title="Edit">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-8 px-3 text-center">
                                            <div class="flex flex-col items-center gap-2">
                                                <svg class="w-12 h-12 text-slate-500" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <p class="text-sm text-slate-400">No batches found</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-70 flex items-center justify-center p-4">
            <div
                class="inline-block align-bottom bg-slate-700 rounded-lg text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full border border-slate-600">
                <div class="bg-slate-700 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg font-medium text-white mb-4">Create New Batch</h3>

                    <form wire:submit.prevent="createBatch" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Batch Number</label>
                            <input type="text" wire:model.live="newBatchNo" placeholder="e.g., 2025001"
                                class="w-full border border-slate-600 bg-slate-800 text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            @error('newBatchNo')
                                <span class="text-red-400 text-xs">{{ $message }}</span>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                Select Students
                                <span class="text-xs text-slate-400">
                                    ({{ count($selectedStudents) }}/5 selected)
                                </span>
                            </label>

                            @if (count($selectedStudents) === 5)
                                <div class="bg-green-900/50 border border-green-600 rounded-lg p-3 mb-3">
                                    <p class="text-green-300 text-sm">✅ Required 5 students selected</p>
                                </div>
                            @elseif (count($selectedStudents) > 0)
                                <div class="bg-yellow-900/50 border border-yellow-600 rounded-lg p-3 mb-3">
                                    <p class="text-yellow-300 text-sm">⚠️ You must select exactly 5 students
                                        ({{ 5 - count($selectedStudents) }} more needed)</p>
                                </div>
                            @else
                                <div class="bg-blue-900/50 border border-blue-600 rounded-lg p-3 mb-3">
                                    <p class="text-blue-300 text-sm">ℹ️ Select exactly 5 students for this batch</p>
                                </div>
                            @endif

                            @if ($this->availableStudents->isEmpty())
                                <div class="bg-orange-900/50 border border-orange-600 rounded-lg p-4 mb-3">
                                    <p class="text-orange-300 text-sm font-medium">ℹ️ No available students</p>
                                    <p class="text-orange-200 text-xs mt-1">All active students are already
                                        assigned to batches.</p>
                                </div>
                            @endif

                            <div class="max-h-64 overflow-y-auto space-y-2 border border-slate-600 bg-slate-800 rounded-lg p-3"
                                x-data="{ selected: @entangle('selectedStudents').defer }">
                                @forelse($this->availableStudents as $student)
                                    <label
                                        class="flex items-center space-x-2 cursor-pointer hover:bg-slate-700 p-2 rounded-lg transition duration-150"
                                        x-bind:class="{ 'opacity-50 cursor-not-allowed': selected.length >= 5 && !selected.includes(
                                                {{ $student->id }}) }">
                                        <input type="checkbox" x-model="selected" value="{{ $student->id }}"
                                            x-bind:disabled="selected.length >= 5 && !selected.includes({{ $student->id }})"
                                            class="rounded border-slate-500 text-blue-500 bg-slate-700 focus:ring-blue-500">
                                        <span class="text-sm text-white">
                                            {{ $student->first_name }} {{ $student->last_name }}
                                            <span class="text-slate-400">({{ $student->email }})</span>
                                        </span>
                                    </label>
                                @empty
                                    <p class="text-sm text-slate-400 text-center py-4">No students available for
                                        assignment</p>
                                @endforelse
                            </div>
                            @error('selectedStudents')
                                <span class="text-red-400 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                    </form>
                </div>

                <div class="bg-slate-800 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2 rounded-b-lg">
                    <button type="button" wire:click="createBatch"
                        {{ $this->availableStudents->isEmpty() || count($selectedStudents) !== 5 ? 'disabled' : '' }}
                        class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                        Create Batch
                    </button>
                    <button type="button" wire:click="$set('showCreateModal', false)"
                        class="mt-3 w-full inline-flex justify-center rounded-lg border border-slate-600 shadow-sm px-4 py-2 bg-slate-700 text-base font-medium text-slate-300 hover:bg-slate-600 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm transition duration-200">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($showEditModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog"
            aria-modal="true">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
                <div class="fixed inset-0 bg-black bg-opacity-70 transition-opacity"
                    wire:click="$set('showEditModal', false)"></div>

                <div
                    class="inline-block align-bottom bg-slate-700 rounded-lg text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-600 z-10">
                    <div class="bg-slate-700 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-white mb-4">Assign Batch to Date</h3>

                        <div class="space-y-4">
                            <div class="bg-blue-900/50 p-3 rounded-lg border border-blue-600/50">
                                <p class="text-sm font-medium text-white">
                                    Batch Number: <span class="font-bold font-mono">{{ $editingBatchNo }}</span>
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">
                                    Edit Students in Batch
                                    <span class="text-xs text-slate-400">
                                        ({{ count($editingSelectedStudents) }}/5 selected)
                                    </span>
                                </label>

                                @if (count($editingSelectedStudents) === 5)
                                    <div class="bg-green-900/50 border border-green-600 rounded-lg p-3 mb-3">
                                        <p class="text-green-300 text-sm">✅ Required 5 students selected</p>
                                    </div>
                                @elseif (count($editingSelectedStudents) > 0)
                                    <div class="bg-yellow-900/50 border border-yellow-600 rounded-lg p-3 mb-3">
                                        <p class="text-yellow-300 text-sm">⚠️ You must select exactly 5 students
                                            ({{ 5 - count($editingSelectedStudents) }} more needed)</p>
                                    </div>
                                @else
                                    <div class="bg-red-900/50 border border-red-600 rounded-lg p-3 mb-3">
                                        <p class="text-red-300 text-sm">❌ Select exactly 5 students for this batch</p>
                                    </div>
                                @endif

                                <div class="max-h-64 overflow-y-auto space-y-2 border border-slate-600 bg-slate-800 rounded-lg p-3"
                                    x-data="{ editSelected: @entangle('editingSelectedStudents').defer }">
                                    @forelse($this->availableStudentsForEdit as $student)
                                        @php
                                            $isCurrentBatchMember = collect(
                                                $this->groupedLibrarians->get($editingBatchNo),
                                            )
                                                ->pluck('user_id')
                                                ->contains($student->id);
                                        @endphp

                                        <label
                                            class="flex items-center justify-between space-x-2 p-2 rounded-lg transition duration-150 cursor-pointer hover:bg-slate-700"
                                            x-bind:class="{
                                                'opacity-50 cursor-not-allowed': editSelected.length >= 5 && !
                                                    editSelected.includes({{ $student->id }}),
                                                'bg-blue-900/20 border border-blue-600/30': {{ $isCurrentBatchMember ? 'true' : 'false' }} &&
                                                    editSelected.includes({{ $student->id }})
                                            }">

                                            <div class="flex items-center space-x-2 flex-1">
                                                <input type="checkbox" x-model="editSelected"
                                                    value="{{ $student->id }}"
                                                    x-bind:disabled="editSelected.length >= 5 && !editSelected.includes(
                                                        {{ $student->id }})"
                                                    class="rounded border-slate-500 text-blue-500 bg-slate-700 focus:ring-blue-500 cursor-pointer">

                                                <div class="flex flex-col">
                                                    <span class="text-sm text-white">
                                                        {{ $student->first_name }} {{ $student->last_name }}
                                                    </span>
                                                    <span class="text-xs text-slate-400">{{ $student->email }}</span>
                                                </div>
                                            </div>

                                            @if ($isCurrentBatchMember)
                                                <span
                                                    class="text-xs bg-blue-600/50 text-blue-200 px-2 py-1 rounded-full whitespace-nowrap"
                                                    x-show="editSelected.includes({{ $student->id }})">
                                                    Current Member
                                                </span>
                                            @endif
                                        </label>
                                    @empty
                                        <p class="text-sm text-slate-400 text-center py-4">No students available
                                        </p>
                                    @endforelse
                                </div>

                                @error('editingSelectedStudents')
                                    <span class="text-red-400 text-xs">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">
                                    Serving Date
                                    <span class="text-xs text-slate-400">(Optional - Set when students should become
                                        librarians)</span>
                                </label>
                                <input type="date" wire:model.live="editingDateStart"
                                    class="w-full border border-slate-600 bg-slate-800 text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('editingDateStart')
                                    <span class="text-red-400 text-xs">{{ $message }}</span>
                                @enderror

                                @if ($editingDateStart)
                                    @if ($this->conflictingBatch)
                                        <div class="mt-2 bg-red-900/50 border border-red-600 rounded-lg p-3">
                                            <p class="text-red-300 text-sm">
                                                <strong>Date Conflict:</strong> Batch No. <span
                                                    class="font-mono">{{ $this->conflictingBatch->batch_no }}</span>
                                                is
                                                already assigned to this date.
                                            </p>
                                        </div>
                                    @elseif($editingDateStart === date('Y-m-d'))
                                        <div class="mt-2 bg-blue-900/50 border border-blue-600 rounded-lg p-3">
                                            <p class="text-blue-300 text-sm">
                                                🎯 <strong>Today's Date!</strong> These students will immediately become
                                                librarians when you save.
                                            </p>
                                        </div>
                                    @elseif($this->isDateChanging && !$this->conflictingBatch)
                                        <div class="mt-2 bg-green-900/50 border border-green-600 rounded-lg p-3">
                                            <p class="text-green-300 text-sm">
                                                ✅ This date is available. Students will become librarians on this date.
                                            </p>
                                        </div>
                                    @endif
                                @else
                                    <div class="mt-2 bg-slate-800/70 border border-slate-600 rounded-lg p-3">
                                        <p class="text-slate-400 text-sm">
                                            ℹ️ Leave empty to create batch without assignment. Set date later to
                                            activate.
                                        </p>
                                    </div>
                                @endif
                            </div>


                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-2">Shift Notes</label>
                                <textarea wire:model="editingShiftNotes" placeholder="Add notes about this shift..." rows="3"
                                    class="w-full border border-slate-600 bg-slate-800 text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-800 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2 rounded-b-lg">
                        <button type="button" wire:click="saveBatchAssignment"
                            {{ $this->conflictingBatch || count($editingSelectedStudents) !== 5 ? 'disabled' : '' }}
                            class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ $editingDateStart ? 'Save & Assign Date' : 'Save Batch' }}
                        </button>
                        <button type="button" wire:click="$set('showEditModal', false)"
                            class="mt-3 w-full inline-flex justify-center rounded-lg border border-slate-600 shadow-sm px-4 py-2 bg-slate-700 text-base font-medium text-slate-300 hover:bg-slate-600 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm transition duration-200">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
