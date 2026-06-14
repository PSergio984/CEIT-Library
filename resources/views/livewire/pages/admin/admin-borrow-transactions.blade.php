@php
    // Livewire Lazy Loading: If this is being rendered as a placeholder, show loading skeleton
    if (isset($placeholder) && $placeholder) {
        echo view('components.table-skeleton', ['rows' => 10, 'cols' => 8]);
        return;
    }
@endphp

<div class="p-6">
    {{-- Load QR libraries first --}}
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

    <x-mary-header title="Borrow Transactions" subtitle="all borrow transactions" separator>
        <x-slot:actions>
            <x-mary-button wire:click="openQrModal" class="btn-primary" icon="o-qr-code">
                Scan QR Code
            </x-mary-button>
        </x-slot:actions>
    </x-mary-header>

    <div class="bg-base-200 p-4 rounded-lg mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <x-mary-input label="Search" wire:model.live.debounce.300ms="search"
                    placeholder="Search by name, title..." icon="o-magnifying-glass" />
            </div>

            <div>
                <x-mary-select label="Paper Type" wire:model.live="paperTypeFilter" :options="collect($this->paperTypes)->map(fn($type) => ['id' => $type, 'name' => $type])"
                    placeholder="All Types" option-value="id" option-label="name" />
            </div>

            <div>
                <x-mary-select label="Status" wire:model.live="statusFilter" :options="[
                    ['id' => '', 'name' => 'All Status'],
                    ['id' => 'started', 'name' => 'Started'],
                    ['id' => 'completed', 'name' => 'Completed'],
                ]" option-value="id"
                    option-label="name" />
            </div>

            <div>
                <x-mary-datetime label="Filter by Date" wire:model.live="selectedDate" type="date"
                    max="{{ date('Y-m-d') }}" />
            </div>

            <div class="flex items-end">
                <x-mary-button wire:click="clearFilters" class="btn-outline w-full" icon="o-x-mark">
                    Clear Filters
                </x-mary-button>
            </div>
        </div>
    </div>

    <div class="mb-4 text-xs sm:text-sm text-base-content/70">
        Showing {{ $this->transactions->count() }} of {{ $this->transactions->total() }} results
    </div>

    <div class="block lg:hidden space-y-4 relative">
        {{-- Skeleton loader for mobile card updates --}}
        <div wire:loading.block 
            wire:target="search, paperTypeFilter, statusFilter, selectedDate, clearFilters, gotoPage, nextPage, previousPage"
            class="space-y-4">
            @for($i = 0; $i < 5; $i++)
                <div class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm animate-pulse">
                    <div class="flex justify-between mb-3">
                        <div class="h-5 bg-base-200 rounded w-1/3"></div>
                        <div class="h-5 bg-base-200 rounded w-1/4"></div>
                    </div>
                    <div class="h-4 bg-base-200 rounded w-3/4 mb-3"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="h-3 bg-base-200 rounded w-full"></div>
                        <div class="h-3 bg-base-200 rounded w-full"></div>
                    </div>
                </div>
            @endfor
        </div>
        
        <div wire:loading.remove 
            wire:target="search, paperTypeFilter, statusFilter, selectedDate, clearFilters, gotoPage, nextPage, previousPage">
            @foreach ($this->transactions as $transaction)
                <div class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <h3 class="font-semibold text-base">{{ $transaction['user_name'] }}</h3>
                        </div>
                        <span
                            class="badge badge-{{ $transaction['status'] == 'completed' ? 'success' : 'warning' }} badge-sm">
                            {{ ucfirst($transaction['status']) }}
                        </span>
                    </div>

                    <div class="mb-3">
                        <p class="font-medium text-sm mb-1" title="{{ $transaction['title'] }}">
                            {{ Str::limit($transaction['title'], 60) }}
                        </p>
                        <span class="badge badge-outline badge-xs">{{ $transaction['paper_type'] }}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-3 text-xs">
                        <div>
                            <p class="text-base-content/50 font-medium">Time In</p>
                            @if ($transaction['time_in'])
                                <p class="font-medium">{{ $transaction['time_in']->format('M d, Y') }}</p>
                                <p class="text-base-content/50">{{ $transaction['time_in']->format('H:i') }}</p>
                            @else
                                <p class="text-base-content/50">N/A</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-base-content/50 font-medium">Time Out</p>
                            @if ($transaction['time_out'])
                                <p class="font-medium">{{ $transaction['time_out']->format('M d, Y') }}</p>
                                <p class="text-base-content/50">{{ $transaction['time_out']->format('H:i') }}</p>
                            @else
                                <p class="text-warning font-medium">Active</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-3 border-t border-base-200">
                        <div class="flex-1 text-xs italic text-base-content/60 truncate">
                            {{ $transaction['notes'] ?: 'No notes' }}
                        </div>
                        @if ($this->canEdit)
                            <button wire:click="openEditModal({{ $transaction['id'] }})"
                                class="btn btn-xs btn-ghost gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                Edit
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach

            <div class="mt-4">
                {{ $this->transactions->links() }}
            </div>
        </div>
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden lg:block overflow-hidden relative">
        {{-- Skeleton loader for desktop table updates --}}
        <div wire:loading.block 
            wire:target="search, paperTypeFilter, statusFilter, selectedDate, clearFilters, gotoPage, nextPage, previousPage">
            <x-table-skeleton rows="10" cols="8" />
        </div>

        <div wire:loading.remove 
            wire:target="search, paperTypeFilter, statusFilter, selectedDate, clearFilters, gotoPage, nextPage, previousPage"
            class="overflow-x-visible">
            <x-mary-table :headers="$headers" :rows="$this->transactions" with-pagination :sort-by="$sortBy" per-page="perPage"
                striped row-class="hover:bg-base-200" header-class="text-base-content bg-base-200">

                @scope('cell_user_name', $row)
                    <div class="font-medium">{{ $row['user_name'] }}</div>
                @endscope

                @scope('cell_title', $row)
                    <div class="max-w-64 truncate" title="{{ $row['title'] }}">
                        {{ $row['title'] }}
                    </div>
                @endscope

                @scope('cell_paper_type', $row)
                    <span class="">{{ $row['paper_type'] }}</span>
                @endscope

                @scope('cell_time_in', $row)
                    <div class="text-sm">
                        @if ($row['time_in'])
                            <div>{{ $row['time_in']->format('M d, Y') }}</div>
                            <div class="text-xs text-base-content/50">{{ $row['time_in']->format('H:i') }}</div>
                        @else
                            <span class="text-base-content/50">N/A</span>
                        @endif
                    </div>
                @endscope

                @scope('cell_time_out', $row)
                    <div class="text-sm">
                        @if ($row['time_out'])
                            <div>{{ $row['time_out']->format('M d, Y') }}</div>
                            <div class="text-xs text-base-content/50">{{ $row['time_out']->format('H:i') }}</div>
                        @else
                            <span class="text-warning">Active</span>
                        @endif
                    </div>
                @endscope

                @scope('cell_status', $row)
                    <span class="badge badge-{{ $row['status'] == 'completed' ? 'success' : 'warning' }} badge-sm">
                        {{ ucfirst($row['status']) }}
                    </span>
                @endscope

                @scope('cell_notes', $row)
                    <div class="min-w-24 max-w-32 text-sm" title="{{ $row['notes'] }}">
                        @if ($row['notes'] && $row['notes'] !== 'N/A')
                            <span class="line-clamp-2">{{ $row['notes'] }}</span>
                        @else
                            <span class="text-base-content/50 italic">No notes</span>
                        @endif
                    </div>
                @endscope

                @scope('cell_actions', $row)
                    <div class="flex items-center justify-center">
                        @if ($this->canEdit)
                            <button wire:click="openEditModal({{ $row['id'] }})"
                                class="btn btn-sm btn-square btn-ghost tooltip tooltip-left" data-tip="Edit Transaction">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                    stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                            </button>
                        @else
                            <span class="text-xs text-base-content/50">View</span>
                        @endif
                    </div>
                @endscope
            </x-mary-table>
        </div>
    </div>

    @if ($this->transactions->isEmpty())
        <div class="text-center py-12">
            <h3 class="text-lg font-medium mb-2">No transactions found</h3>
            <p class="text-base-content/70 mb-4">Try adjusting your search criteria or filters.</p>
            <x-mary-button wire:click="clearFilters" class="btn-outline">
                Clear All Filters
            </x-mary-button>
        </div>
    @endif

    {{-- Edit Transaction Modal --}}
    <x-mary-modal wire:model="showEditModal" title="Edit Transaction" persistent class="backdrop-blur">
        <div class="space-y-4">
            <div>
                <x-mary-select label="Status" wire:model="form.status" :options="[['id' => 'started', 'name' => 'Started'], ['id' => 'completed', 'name' => 'Completed']]" option-value="id"
                    option-label="name" required />
            </div>

            <div>
                <x-mary-datetime label="Time Out" wire:model="form.time_out" type="datetime-local"
                    hint="Leave empty for active transactions" />
            </div>

            <div>
                <x-mary-textarea label="Notes" wire:model="form.notes" placeholder="Add notes..." rows="3" />
            </div>

            @if ($form->status === 'completed' && empty($form->time_out))
                <div class="alert alert-warning">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>Setting status to <b>Completed</b> without a Time Out will use the current time.</span>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" @click="$wire.showEditModal = false" />
            <x-mary-button label="Save Changes" wire:click="save" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- QR Scanner Modal --}}
    <x-mary-modal wire:model="showQrModal" title="Scan Transaction QR" persistent class="backdrop-blur">
        <livewire:qr-scanner />
        <x-slot:actions>
            <x-mary-button label="Close" @click="$wire.showQrModal = false" />
        </x-slot:actions>
    </x-mary-modal>
</div>
