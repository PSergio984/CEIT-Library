<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Your Transaction History</h1>
        <div class="text-sm text-base-content/70">
            Total Transactions: {{ $this->transactions->total() }}
        </div>
    </div>

    <div class="bg-base-200 p-4 rounded-lg mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="form-control w-full">
                    <div class="label">
                        <span class="label-text">Search</span>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Search by title, author..." class="input input-bordered w-full" />
                </label>
            </div>

            <div>
                <label class="form-control w-full">
                    <div class="label">
                        <span class="label-text">Paper Type</span>
                    </div>
                    <select wire:model.live="paperTypeFilter" class="select select-bordered w-full">
                        <option value="">All Types</option>
                        @foreach ($this->paperTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div>
                <label class="form-control w-full">
                    <div class="label">
                        <span class="label-text">Status</span>
                    </div>
                    <select wire:model.live="statusFilter" class="select select-bordered w-full">
                        <option value="">All Status</option>
                        <option value="started">Started</option>
                        <option value="completed">Completed</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </label>
            </div>

            <div>
                <label class="form-control w-full">
                    <div class="label">
                        <span class="label-text">Filter by Date</span>
                    </div>
                    <input type="date" wire:model.live="selectedDate" max="{{ date('Y-m-d') }}"
                        class="input input-bordered w-full" />
                </label>
            </div>

            <div class="flex items-end">
                <button wire:click="clearFilters" class="btn btn-outline w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>

                    Clear Filters
                </button>
            </div>
        </div>
    </div>

    <div class="mb-4 text-xs sm:text-sm text-base-content/70">
        Showing {{ $this->transactions->count() }} of {{ $this->transactions->total() }} results
    </div>

    <!-- Mobile View here -->
    <div class="block lg:hidden space-y-4">
        @foreach ($this->transactions as $transaction)
            <div class="bg-base-100 rounded-lg p-4 shadow-lg border border-base-300 cursor-pointer hover:shadow-xl transition-shadow"
                tabindex="0" role="button"
                wire:click="viewTransaction({{ $transaction['id'] }})"
                wire:keydown.enter="viewTransaction({{ $transaction['id'] }})">

                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <div class="text-xs text-base-content/60 mb-1">
                            {{ $transaction['academic_paper']->department ?? 'N/A' }}
                        </div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="bg-base-200 text-base-content px-2 py-1 rounded text-xs font-medium">
                                {{ $transaction['paper_type'] }}
                            </span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-right mt-1">
                            <x-transaction-status :status="$transaction['status']" />
                        </div>
                    </div>
                </div>

                <h3 class="text-base-content font-medium text-base leading-tight mb-3">
                    {{ $transaction['title'] }}
                </h3>

                <div class="text-sm text-base-content/70 mb-3">
                    Copy: {{ $transaction['inventory']->copy_number ?? 'N/A' }}<br>
                    Inventory ID: {{ $transaction['inventory']->id ?? 'N/A' }}
                </div>

                <div class="text-sm text-base-content/70 mb-2">
                    Date Borrowed:
                    <span class="text-base-content font-medium">
                        @if ($transaction['time_in'])
                            {{ $transaction['time_in']->format('m/d/Y') }}
                        @else
                            N/A
                        @endif
                    </span>
                </div>

                <div class="text-sm text-base-content/70 mb-4">
                    Due Date:
                    <span
                        class="font-medium @if ($transaction['status'] === 'started') font-bold text-red-200 px-2 py-1 rounded @else text-base-content @endif">
                        @if ($transaction['expires_at'])
                            {{ $transaction['expires_at']->format('m/d/Y') }}
                        @else
                            N/A
                        @endif
                    </span>
                </div>

                @if ($transaction['status'] === 'completed')
                    <div class="text-sm text-base-content/70 mb-4">
                        Date Returned:
                        <span class="text-green-300 font-medium">
                            @if ($transaction['time_out'])
                                {{ $transaction['time_out']->format('m/d/Y') }}
                            @else
                                N/A
                            @endif
                        </span>
                    </div>
                @endif
            </div>
        @endforeach

        <div class="mt-6">
            {{ $this->transactions->links() }}
        </div>
    </div>

    <!-- Desktop View here -->
    <div class="hidden lg:block">
        <div class="grid grid-cols-1 gap-4">
            @foreach ($this->transactions as $transaction)
                <div class="bg-base-100 rounded-lg p-4 shadow-lg border border-base-300 cursor-pointer hover:shadow-xl transition-shadow"
                    tabindex="0" role="button"
                    wire:click="viewTransaction({{ $transaction['id'] }})"
                    wire:keydown.enter="viewTransaction({{ $transaction['id'] }})">

                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="text-sm text-base-content/60 mb-1">
                                {{ $transaction['academic_paper']->department ?? 'N/A' }}
                                <span class="bg-base-200 text-base-content px-2 py-1 rounded text-xs font-medium">
                                    {{ $transaction['paper_type'] }}
                                </span>
                            </div>

                        </div>
                        <div class="text-right">
                            <div class="text-right mt-1">
                                <x-transaction-status :status="$transaction['status']" />
                            </div>
                        </div>
                    </div>

                    <h3 class="text-base-content text-3xl font-medium leading-tight mb-3">
                        {{ $transaction['title'] }}
                    </h3>

                    <div class="text-sm text-base-content/70 mb-3">
                        Copy: {{ $transaction['inventory']->copy_number ?? 'N/A' }}<br>
                        Inventory ID: {{ $transaction['inventory']->id ?? 'N/A' }}
                    </div>

                    <div class="text-sm text-base-content/70 mb-2">
                        Date Borrowed:
                        <span class="text-base-content font-medium">
                            @if ($transaction['time_in'])
                                {{ $transaction['time_in']->format('m/d/Y') }}
                            @else
                                N/A
                            @endif
                        </span>
                    </div>

                    <div class="text-sm text-base-content/70 mb-4">
                        Due Date:
                        <span
                            class="font-medium @if ($transaction['status'] === 'started') font-bold text-red-200 px-2 py-1 rounded @else text-base-content @endif">
                            @if ($transaction['expires_at'])
                                {{ $transaction['expires_at']->format('m/d/Y') }}
                            @else
                                N/A
                            @endif
                        </span>
                    </div>

                    @if ($transaction['status'] === 'completed')
                        <div class="text-sm text-base-content/70 mb-4">
                            Date Returned:
                            <span class="text-base-content text-green-300 font-medium">
                                @if ($transaction['time_out'])
                                    {{ $transaction['time_out']->format('m/d/Y') }}
                                @else
                                    N/A
                                @endif
                            </span>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $this->transactions->links() }}
        </div>
    </div>

    @if ($this->transactions->isEmpty())
        <div class="text-center py-12">
            <h3 class="text-lg font-medium mb-2">No transactions found</h3>
            <p class="text-base-content/70 mb-4">
                @if ($search || $statusFilter || $paperTypeFilter || $selectedDate)
                    Try adjusting your search criteria or filters.
                @else
                    You haven't borrowed any papers yet.
                @endif
            </p>
            @if ($search || $statusFilter || $paperTypeFilter || $selectedDate)
                <button wire:click="clearFilters" class="btn btn-outline">
                    Clear All Filters
                </button>
            @endif
        </div>
    @endif
</div>
