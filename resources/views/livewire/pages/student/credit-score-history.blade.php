<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Credit Score History</h1>
        <div class="text-sm text-base-content/70">
            Credit Score: <span class="font-bold text-lg">{{ $this->creditScore() }}</span>
        </div>
    </div>

    <div class="bg-base-200 p-4 rounded-lg mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="form-control w-full">
                    <div class="label">
                        <span class="label-text">Search</span>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Search violation, remarks..." class="input input-bordered w-full" />
                </label>
            </div>

            <div>
                <label class="form-control w-full">
                    <div class="label">
                        <span class="label-text">Type</span>
                    </div>
                    <select wire:model.live="typeFilter" class="select select-bordered w-full">
                        <option value="">All Types</option>
                        <option value="reward">Rewards</option>
                        <option value="penalty">Penalties</option>
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
        Showing {{ $this->history->count() }} of {{ $this->history->total() }} history records
    </div>

    <!-- Mobile View here -->
    <div class="block lg:hidden space-y-4">
        @foreach ($this->history as $item)
            <div class="bg-base-100 rounded-lg p-4 shadow-lg border border-base-300 cursor-pointer hover:shadow-xl transition-shadow"
                tabindex="0" role="button"
                wire:click="viewHistory('{{ $item['id'] }}')"
                wire:keydown.enter="viewHistory('{{ $item['id'] }}')">

                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            @if ($item['type'] === 'penalty')
                                <span class="badge 
                                    @if ($item['severity'] === 'Critical') badge-error 
                                    @elseif($item['severity'] === 'Major') badge-warning 
                                    @else badge-info 
                                    @endif font-medium">
                                    {{ $item['severity'] }}
                                </span>
                            @else
                                <span class="badge badge-success font-medium">
                                    Reward
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-lg {{ $item['points'] > 0 ? 'text-success' : 'text-error' }}">
                            {{ $item['points'] > 0 ? '+' : '' }}{{ $item['points'] }}
                        </div>
                    </div>
                </div>

                <h3 class="text-base-content font-medium text-base leading-tight mb-3">
                    {{ $item['action'] }}
                </h3>

                <div class="text-sm text-base-content/70 mb-2">
                    Date Occurred:
                    <span class="text-base-content font-medium">
                        @if ($item['occurred_at'])
                            {{ \Carbon\Carbon::parse($item['occurred_at'])->format('m/d/Y') }}
                        @else
                            N/A
                        @endif
                    </span>
                </div>

                @if ($item['description'])
                    <div class="text-sm text-base-content/70 mt-3">
                        <span class="font-medium">Details:</span><br>
                        {{ $item['description'] }}
                    </div>
                @endif
            </div>
        @endforeach

        <div class="mt-6">
            {{ $this->history->links() }}
        </div>
    </div>

    <!-- Desktop View here -->
    <div class="hidden lg:block">
        <div class="grid grid-cols-1 gap-4">
            @foreach ($this->history as $item)
                <div class="bg-base-100 rounded-lg p-4 shadow-lg border border-base-300 cursor-pointer hover:shadow-xl transition-shadow"
                    tabindex="0" role="button"
                    wire:click="viewHistory('{{ $item['id'] }}')"
                    wire:keydown.enter="viewHistory('{{ $item['id'] }}')">

                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="text-sm text-base-content/60 mb-1">
                                @if ($item['type'] === 'penalty')
                                    <span class="badge 
                                        @if ($item['severity'] === 'Critical') badge-error 
                                        @elseif($item['severity'] === 'Major') badge-warning 
                                        @else badge-info 
                                        @endif font-medium">
                                        {{ $item['severity'] }}
                                    </span>
                                @else
                                    <span class="badge badge-success font-medium">
                                        Reward
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold text-2xl {{ $item['points'] > 0 ? 'text-success' : 'text-error' }}">
                                {{ $item['points'] > 0 ? '+' : '' }}{{ $item['points'] }}
                            </div>
                        </div>
                    </div>

                    <h3 class="text-base-content text-2xl font-medium leading-tight mb-3">
                        {{ $item['action'] }}
                    </h3>

                    <div class="text-sm text-base-content/70 mb-2">
                        Date Occurred:
                        <span class="text-base-content font-medium">
                            @if ($item['occurred_at'])
                                {{ \Carbon\Carbon::parse($item['occurred_at'])->format('m/d/Y') }}
                            @else
                                N/A
                            @endif
                        </span>
                    </div>

                    @if ($item['description'])
                        <div class="text-sm text-base-content/70 mt-3">
                            <span class="font-medium">Details:</span><br>
                            {{ $item['description'] }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $this->history->links() }}
        </div>
    </div>

    @if ($this->history->isEmpty())
        <div class="text-center py-12">
            <h3 class="text-lg font-medium mb-2">No history found</h3>
            <p class="text-base-content/70 mb-4">
                @if ($search || $typeFilter || $selectedDate)
                    Try adjusting your search criteria or filters.
                @else
                    No credit score history available yet.
                @endif
            </p>
            @if ($search || $typeFilter || $selectedDate)
                <button wire:click="clearFilters" class="btn btn-outline">
                    Clear All Filters
                </button>
            @endif
        </div>
    @endif
</div>
