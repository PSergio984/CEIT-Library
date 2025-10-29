<div>
    <header class="p-5 shadow-md">
        <h1 class="text-2xl font-bold text-white">Admin - Assign Librarians to Batches</h1>
    </header>

    <div class="p-5 min-h-screen ">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            <div class="flex flex-col space-y-6 lg:h-[calc(100vh-100px)]">

                <div class="bg-slate-700/50 backdrop-blur-sm rounded-xl shadow-lg p-6 flex-shrink-0">
                    <h2 class="text-white text-xl font-semibold mb-4">Create a Batch</h2>
                    <div class="space-y-4">
                        <button wire:click.prevent="openCreateModal"
                            class="w-full bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 shadow-md transform hover:scale-[1.01]">
                            Create New Batch
                        </button>
                    </div>
                </div>

                <div class="bg-slate-700/50 backdrop-blur-sm rounded-xl shadow-lg p-6 flex-grow flex flex-col min-h-0">
                    <h2 class="text-white text-xl font-semibold mb-4">Available Batches (Unassigned)</h2>
                    <div class="overflow-y-auto flex-1 rounded-lg">
                        <table class="w-full">
                            <thead class="sticky top-0 bg-slate-700/90 backdrop-blur-sm z-10">
                                <tr class="text-slate-300 text-sm border-b border-slate-600">
                                    <th class="text-left py-2 px-3">Batch no.</th>
                                    <th class="text-left py-2 px-3">Members</th>
                                    <th class="text-left py-2 px-3"></th>
                                </tr>
                            </thead>
                            <tbody class="text-white text-sm">
                                @forelse($availableBatches as $batch)
                                    <tr
                                        class="border-b border-slate-600 hover:bg-slate-600/30 transition cursor-pointer">
                                        <td class="py-3 px-3 font-mono">{{ $batch['batch_no'] }}</td>
                                        <td class="py-3 px-3">
                                            <div class="text-sm text-slate-300">
                                                {{ implode(', ', $batch['members']) }}
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <button wire:click.prevent="openEditModal('{{ $batch['batch_no'] }}')"
                                                class="text-blue-400 hover:text-blue-300 p-1 rounded-full hover:bg-slate-600/50 transition"
                                                title="Assign Date">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
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
                                        <td colspan="3" class="py-3 px-3 text-center text-slate-400">No available
                                            batches</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-slate-700/50 backdrop-blur-sm rounded-xl shadow-lg p-6 flex-grow flex flex-col min-h-0">
                    <h2 class="text-white text-xl font-semibold mb-4">Assigned Batches</h2>
                    <div class="overflow-y-auto flex-1 rounded-lg">
                        <table class="w-full">
                            <thead class="sticky top-0 bg-slate-700/90 backdrop-blur-sm z-10">
                                <tr class="text-slate-300 text-sm border-b border-slate-600">
                                    <th class="text-left py-2 px-3">Batch no.</th>
                                    <th class="text-left py-2 px-3">Members</th>
                                    <th class="text-left py-2 px-3">Date</th>
                                    <th class="text-center py-2 px-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-white text-sm">
                                @forelse($assignedBatches as $batch)
                                    <tr class="border-b border-slate-600 hover:bg-slate-600/30 transition">
                                        <td class="py-3 px-3 font-mono">{{ $batch['batch_no'] }}</td>
                                        <td class="py-3 px-3">
                                            <div class="text-sm text-slate-300">{{ implode(', ', $batch['members']) }}
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-xs text-blue-300">{{ $batch['date_assigned'] }}
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <button wire:click.prevent="openEditModal('{{ $batch['batch_no'] }}')"
                                                class="text-blue-400 hover:text-blue-300 p-1 rounded-full hover:bg-slate-600/50 transition"
                                                title="Edit Assignment">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
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
                                        <td colspan="4" class="py-3 px-3 text-center text-slate-400">No assigned
                                            batches</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div
                    class="bg-slate-700/50 backdrop-blur-sm rounded-xl shadow-lg p-6 flex flex-col lg:h-[calc(100vh-100px)]">
                    <h2 class="text-white text-xl font-semibold mb-4">All Batches</h2>

                    <div class="mb-6 space-y-4 flex-shrink-0">
                        <div class="flex flex-col md:flex-row gap-4">
                            <div class="w-full md:w-1/3">
                                <label for="filterStatus"
                                    class="block text-sm font-medium text-slate-400 mb-1">Status</label>
                                <select id="filterStatus" wire:model.live="filterStatus"
                                    class="w-full bg-slate-800 text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 border border-slate-600">
                                    <option value="">All</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="expired">Expired</option>
                                </select>
                            </div>

                            <div class="w-full md:w-1/3">
                                <label for="filterDateStart"
                                    class="block text-sm font-medium text-slate-400 mb-1">Date</label>
                                <input type="date" id="filterDateStart" wire:model.live="filterDateStart"
                                    class="w-full bg-slate-800 text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 border border-slate-600">
                            </div>

                            <div class="w-full md:w-1/3 hidden md:block"></div>
                        </div>

                        <label for="filterDateStart"
                            class="block text-sm font-medium text-slate-400 mb-1">Search</label>
                        <div class="flex space-x-4">
                            <div class="relative flex-1">
                                <input type="text" wire:model.live.debounce.300ms="batchSearch"
                                    placeholder="Search Batch no., notes, creator, or student name..."
                                    class="w-full bg-slate-800 text-white rounded-lg px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-blue-500 border border-slate-600">
                                <svg class="absolute right-3 top-2.5 w-5 h-5 text-slate-400" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>

                            <button wire:click="resetFilters"
                                class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200 shadow-md flex items-center justify-center whitespace-nowrap">
                                Clear Filters
                            </button>
                        </div>
                    </div>


                    <div class="overflow-y-auto flex-1 rounded-lg">
                        <table class="w-full">
                            <thead class="sticky top-0 bg-slate-700/90 backdrop-blur-sm z-10">
                                <tr class="text-slate-300 text-sm border-b border-slate-600">
                                    <th class="text-left py-2 px-3">#</th>
                                    <th class="text-left py-2 px-3">Batch no.</th>
                                    <th class="text-left py-2 px-3">Date to Serve?</th>
                                    <th class="text-left py-2 px-3">Shift notes</th>
                                    <th class="text-left py-2 px-3">Created by</th>
                                    <th class="text-left py-2 px-3">Status</th>
                                    <th class="text-center py-2 px-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-white text-sm">
                                @forelse($allBatches as $index => $batch)
                                    <tr class="border-b border-slate-600 hover:bg-slate-600/30 transition">
                                        <td class="py-3 px-3">{{ $index + 1 }}</td>
                                        <td class="py-3 px-3 font-mono">{{ $batch['batch_no'] }}</td>
                                        <td class="py-3 px-2 text-blue-400">{{ $batch['date_range'] }}</td>
                                        <td class="py-3 px-2 text-xs truncate max-w-xs text-slate-400">
                                            {{ $batch['shift_notes'] }}</td>
                                        <td class="py-3 px-3 text-slate-300">{{ $batch['created_by'] }}</td>
                                        <td class="py-3 px-3">
                                            <span
                                                class="px-2 py-1 rounded-full text-xs font-semibold
                                                        {{ $batch['status'] === 'active'
                                                            ? 'bg-green-500/20 text-green-400'
                                                            : ($batch['status'] === 'inactive'
                                                                ? 'bg-red-500/20 text-red-400'
                                                                : 'bg-slate-500/20 text-slate-400') }}">
                                                {{ ucfirst($batch['status']) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <button wire:click.prevent="openEditModal('{{ $batch['batch_no'] }}')"
                                                class="text-blue-400 hover:text-blue-300 p-1 rounded-full hover:bg-slate-600/50 transition"
                                                title="Assign/Edit Date">
                                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14">
                                                    </path>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-3 px-3 text-center text-slate-400">No batches
                                            found</td>
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

                            @if (count($selectedStudents) >= 5)
                                <div class="bg-yellow-900/50 border border-yellow-600 rounded-lg p-3 mb-3">
                                    <p class="text-yellow-300 text-sm">⚠️ Maximum of 5 students reached</p>
                                </div>
                            @endif

                            @if ($this->availableStudents->isEmpty())
                                <div class="bg-orange-900/50 border border-orange-600 rounded-lg p-4 mb-3">
                                    <p class="text-orange-300 text-sm font-medium">ℹ️ No available students</p>
                                    <p class="text-orange-200 text-xs mt-1">All active students are already
                                        assigned to batches.</p>
                                </div>
                            @endif

                            <div
                                class="max-h-64 overflow-y-auto space-y-2 border border-slate-600 bg-slate-800 rounded-lg p-3">
                                @forelse($this->availableStudents as $student)
                                    @php
                                        $isDisabled =
                                            count($selectedStudents) >= 5 && !in_array($student->id, $selectedStudents);
                                    @endphp
                                    <label
                                        class="flex items-center space-x-2 cursor-pointer hover:bg-slate-700 p-2 rounded-lg transition duration-150
                                        {{ $isDisabled ? 'opacity-50 cursor-not-allowed' : '' }}">
                                        <input type="checkbox" wire:model.live="selectedStudents"
                                            value="{{ $student->id }}" {{ $isDisabled ? 'disabled' : '' }}
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
                        {{ $this->availableStudents->isEmpty() || count($selectedStudents) === 0 ? 'disabled' : '' }}
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

                                @if (count($editingSelectedStudents) >= 5)
                                    <div class="bg-yellow-900/50 border border-yellow-600 rounded-lg p-3 mb-3">
                                        <p class="text-yellow-300 text-sm">Maximum of 5 students reached.
                                            Uncheck a student to select another.</p>
                                    </div>
                                @endif

                                <div
                                    class="max-h-64 overflow-y-auto space-y-2 border border-slate-600 bg-slate-800 rounded-lg p-3">
                                    @forelse($this->availableStudentsForEdit as $student)
                                        @php
                                            $isSelected = in_array($student->id, $editingSelectedStudents);
                                            $shouldDisable = count($editingSelectedStudents) >= 5 && !$isSelected;
                                            $isCurrentBatchMember = collect(
                                                $this->groupedLibrarians->get($editingBatchNo),
                                            )
                                                ->pluck('user_id')
                                                ->contains($student->id);
                                            $memberClass =
                                                $isCurrentBatchMember && $isSelected
                                                    ? 'bg-blue-900/20 border border-blue-600/30'
                                                    : '';
                                        @endphp

                                        <label
                                            class="flex items-center justify-between space-x-2 p-2 rounded-lg transition duration-150
                                            {{ $shouldDisable ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-slate-700' }}
                                            {{ $memberClass }}">

                                            <div class="flex items-center space-x-2 flex-1">
                                                <input type="checkbox" wire:model.live="editingSelectedStudents"
                                                    value="{{ $student->id }}"
                                                    {{ $shouldDisable ? 'disabled' : '' }}
                                                    class="rounded border-slate-500 text-blue-500 bg-slate-700 focus:ring-blue-500
                                                    {{ $shouldDisable ? 'cursor-not-allowed' : 'cursor-pointer' }}">

                                                <div class="flex flex-col">
                                                    <span class="text-sm text-white">
                                                        {{ $student->first_name }} {{ $student->last_name }}
                                                    </span>
                                                    <span class="text-xs text-slate-400">{{ $student->email }}</span>
                                                </div>
                                            </div>

                                            @if ($isCurrentBatchMember && $isSelected)
                                                <span
                                                    class="text-xs bg-blue-600/50 text-blue-200 px-2 py-1 rounded-full whitespace-nowrap">
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
                                <label class="block text-sm font-medium text-slate-300 mb-2">Serving Date</label>
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
                                    @elseif($this->isDateChanging && !$this->conflictingBatch)
                                        <div class="mt-2 bg-green-900/50 border border-green-600 rounded-lg p-3">
                                            <p class="text-green-300 text-sm">
                                                This date is available
                                            </p>
                                        </div>
                                    @endif
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
                            {{ $this->conflictingBatch ? 'disabled' : '' }}
                            class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm transition duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                            Save Assignment
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
