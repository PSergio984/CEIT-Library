<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-foreground leading-tight">
            {{ __('Librarians') }}
        </h2>
    </x-slot>

    <div class="min-h-screen p-5">
        <div class="max-w-9xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Create batch shii-->
            <div class="space-y-6">
                <div class="bg-slate-700/50 backdrop-blur-sm rounded-lg shadow-lg p-6">
                    <h2 class="text-white text-xl font-semibold mb-4">Create a Batch</h2>
                    <div class="space-y-4">
                        <button wire:click="openCreateModal"
                            class="w-full bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded transition duration-200">
                            Create New Batch
                        </button>
                    </div>
                </div>

                <!-- Available sec -->
                <div class="bg-slate-700/50 backdrop-blur-sm rounded-lg shadow-lg p-6 flex flex-col max-h-[400px]">
                    <h2 class="text-white text-xl font-semibold mb-4">Available Batches</h2>
                    <div class="overflow-y-auto flex-1">
                        <table class="w-full">
                            <thead class="sticky top-0 bg-slate-700/90 backdrop-blur-sm">
                                <tr class="text-slate-300 text-sm border-b border-slate-600">
                                    <th class="text-left py-2 px-3">Batch no.</th>
                                    <th class="text-left py-2 px-3">Members</th>
                                </tr>
                            </thead>
                            <tbody class="text-white text-sm">
                                @forelse($availableBatches as $batch)
                                    <tr class="border-b border-slate-600 hover:bg-slate-600/30 transition">
                                        <td class="py-3 px-3">{{ $batch['batch_no'] }}</td>
                                        <td class="py-3 px-3">
                                            <div class="text-sm">{!! $batch['members'] !!}</div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="py-3 px-3 text-center text-slate-400">No available
                                            batches</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Assigned sec -->
                <div class="bg-slate-700/50 backdrop-blur-sm rounded-lg shadow-lg p-6 flex flex-col max-h-[400px]">
                    <h2 class="text-white text-xl font-semibold mb-4">Assigned Batches</h2>
                    <div class="overflow-y-auto flex-1">
                        <table class="w-full">
                            <thead class="sticky top-0 bg-slate-700/90 backdrop-blur-sm">
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
                                        <td class="py-3 px-3">{{ $batch['batch_no'] }}</td>
                                        <td class="py-3 px-3">
                                            <div class="text-sm">{!! $batch['members'] !!}</div>
                                        </td>
                                        <td class="py-3 px-3 text-xs">{{ $batch['date_assigned'] }}</td>
                                        <td class="py-3 px-3 text-center">
                                            <button wire:click="openEditModal('{{ $batch['batch_no'] }}')"
                                                class="text-blue-400 hover:text-blue-300 p-1">
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

            <!-- All batches sect -->
            <div class="lg:col-span-2">
                <div class="bg-slate-700/50 backdrop-blur-sm rounded-lg shadow-lg p-6 flex flex-col h-full">
                    <h2 class="text-white text-xl font-semibold mb-4">All Batches</h2>

                    <div class="mb-4">
                        <div class="relative">
                            <input type="text" wire:model.live="batchSearch" placeholder="Search Batch no."
                                class="w-full bg-slate-600 text-white rounded px-3 py-2 pr-10 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <svg class="absolute right-3 top-2.5 w-5 h-5 text-slate-400" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="overflow-y-auto flex-1">
                        <table class="w-full">
                            <thead class="sticky top-0 bg-slate-700/90 backdrop-blur-sm">
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
                                        <td class="py-3 px-3">{{ $batch['batch_no'] }}</td>
                                        <td class="py-3 px-2">{{ $batch['date_range'] }}</td>
                                        <td class="py-3 px-2 text-xs truncate max-w-xs">{{ $batch['shift_notes'] }}</td>
                                        <td class="py-3 px-3">{{ $batch['created_by'] }}</td>
                                        <td class="py-3 px-3">
                                            <span
                                                class="px-2 py-1 rounded text-xs {{ $batch['status'] === 'active' ? 'bg-green-500/20 text-green-400' : 'bg-slate-500/20 text-slate-400' }}">
                                                {{ ucfirst($batch['status']) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-3 text-center">
                                            <button wire:click="openEditModal('{{ $batch['batch_no'] }}')"
                                                class="text-blue-400 hover:text-blue-300">
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
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog"
            aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity"
                    wire:click="$set('showCreateModal', false)"></div>

                <div
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Create New Batch</h3>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Batch Number</label>
                                <input type="text" wire:model="newBatchNo" placeholder="e.g., 2025001"
                                    class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('newBatchNo')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Students</label>
                                <div class="max-h-64 overflow-y-auto space-y-2 border border-gray-300 rounded p-3">
                                    @forelse($availableStudents as $student)
                                        <label
                                            class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                                            <input type="checkbox" wire:model="selectedStudents"
                                                value="{{ $student->id }}"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            <span class="text-sm">{{ $student->first_name }}
                                                {{ $student->last_name }} ({{ $student->email }})</span>
                                        </label>
                                    @empty
                                        <p class="text-sm text-gray-500 text-center py-4">No available students</p>
                                    @endforelse
                                </div>
                                @error('selectedStudents')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button type="button" wire:click="createBatch"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Create Batch
                        </button>
                        <button type="button" wire:click="$set('showCreateModal', false)"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showEditModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog"
            aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity"
                    wire:click="$set('showEditModal', false)"></div>

                <div
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Assign Batch to Date</h3>

                        <div class="space-y-4">
                            <div class="bg-blue-50 p-3 rounded">
                                <p class="text-sm font-medium">Batch Number: <span
                                        class="font-bold">{{ $editingBatchNo }}</span></p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                                <input type="date" wire:model="editingDateStart"
                                    class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('editingDateStart')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                                <input type="date" wire:model="editingDateEnd"
                                    class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('editingDateEnd')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Shift Notes</label>
                                <textarea wire:model="editingShiftNotes" placeholder="Add notes about this shift..." rows="3"
                                    class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button type="button" wire:click="saveBatchAssignment"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                            Save Assignment
                        </button>
                        <button type="button" wire:click="$set('showEditModal', false)"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
