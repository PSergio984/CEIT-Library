<div class="p-6">
    <x-mary-header title="Borrow Transactions" subtitle="all borrow transactions" separator />

    <div class="bg-base-200 p-4 rounded-lg mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <x-mary-input label="Search" wire:model.live.debounce.300ms="search"
                    placeholder="Search by name, email, title..." icon="o-magnifying-glass" />
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

    <div class="block lg:hidden space-y-4">
        @foreach ($this->transactions as $transaction)
            <div class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                wire:click="showTransactionDetails({{ $transaction['id'] }})">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <h3 class="font-semibold text-base">{{ $transaction['user_name'] }}</h3>
                        <p class="text-sm text-base-content/70 font-mono">
                            {{ $transaction['user']?->student_no ?? 'N/A' }}</p>
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

                @if ($transaction['notes'] && $transaction['notes'] !== 'N/A')
                    <div class="mb-3">
                        <p class="text-base-content/50 font-medium text-xs mb-1">Notes</p>
                        <p class="text-sm line-clamp-2">{{ $transaction['notes'] }}</p>
                    </div>
                @endif
            </div>
        @endforeach

        <div class="mt-6">
            {{ $this->transactions->links() }}
        </div>
    </div>

    <div class="hidden lg:block overflow-x-auto">
        <x-mary-table :headers="$headers" :rows="$this->transactions" :sort-by="$sortBy" with-pagination striped
            @row-click="showTransactionDetails($event.detail)" row-class="hover:bg-base-200 cursor-pointer"
            header-class="text-base-content bg-base-200" class="w-full min-w-fit table-auto">
            @scope('cell_user_name', $row)
                <div class="font-medium">{{ $row['user_name'] }}</div>
            @endscope

            @scope('cell_user.student_no', $row)
                <span class="font-mono text-sm">{{ $row['user']?->student_no ?? 'N/A' }}</span>
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
        </x-mary-table>
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
</div>
