{{-- Credit Score History - Modernized Design --}}
<div class="min-h-screen bg-base-200/30 p-4 md:p-6">
    {{-- Header Section --}}
    <div class="max-w-7xl mx-auto mb-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-3xl md:text-4xl font-bold text-base-content">Credit Score History</h1>
                <p class="text-sm text-base-content/60 mt-1">Track your rewards and penalties history</p>
            </div>
            <div class="stats shadow bg-base-100">
                <div class="stat py-4 px-6">
                    <div class="stat-title text-xs">Credit Score</div>
                    <div class="stat-value text-2xl text-primary">{{ $this->creditScore() }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters Section --}}
    <div class="max-w-7xl mx-auto mb-6">
        <div class="bg-base-100 rounded-box shadow-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Search --}}
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">Search</span>
                    </label>
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search violation, remarks..." 
                        class="input input-bordered focus:input-primary transition-all" 
                    />
                </div>

                {{-- Type Filter --}}
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">Type</span>
                    </label>
                    <select wire:model.live="typeFilter" class="select select-bordered focus:select-primary">
                        <option value="">All Types</option>
                        <option value="reward">Rewards</option>
                        <option value="penalty">Penalties</option>
                    </select>
                </div>

                {{-- Date Filter --}}
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">Filter by Date</span>
                    </label>
                    <input 
                        type="date" 
                        wire:model.live="selectedDate" 
                        max="{{ date('Y-m-d') }}"
                        class="input input-bordered focus:input-primary" 
                    />
                </div>

                {{-- Clear Filters Button --}}
                <div class="flex items-end justify-end w-full h-full">
                    <x-clear-filters-button />
                </div>
            </div>
            <div class="mt-4 text-sm text-base-content/70">
                Showing <span class="font-semibold text-base-content">{{ $this->history->count() }}</span> of 
                <span class="font-semibold text-base-content">{{ $this->history->total() }}</span> history records
            </div>
        </div>
    </div>

    {{-- History List (Unified for Mobile & Desktop) --}}
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 gap-4">
            @foreach ($this->history as $item)
                <div 
                    wire:key="history-{{ $item['id'] }}"
                    class="bg-base-100 rounded-lg shadow-lg hover:shadow-2xl transition-all duration-300 mb-4 overflow-hidden border border-base-300 hover:border-primary/30 cursor-pointer"
                    tabindex="0" 
                    role="button"
                    wire:click="viewHistory('{{ $item['id'] }}')"
                    wire:keydown.enter="viewHistory('{{ $item['id'] }}')">
                    
                    {{-- Card Header with Gradient --}}
                    <div class="bg-gradient-to-r from-primary/10 to-secondary/10 p-4 md:p-6 border-b border-base-300">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    @if ($item['type'] === 'penalty')
                                        <span class="badge badge-error font-medium">
                                            Penalty
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
                    </div>

                    {{-- Card Body --}}
                    <div class="p-4 md:p-6">
                        <h3 class="text-xl md:text-2xl font-bold text-base-content leading-tight mb-3">
                            {{ $item['action'] }}
                        </h3>
                        
                        <div class="text-sm text-base-content/70 mb-2">
                            <span class="font-semibold">Date Occurred:</span>
                            <span class="text-base-content font-medium">
                                @if ($item['occurred_at'])
                                    {{ \Carbon\Carbon::parse($item['occurred_at'])->format('M d, Y') }}
                                @else
                                    N/A
                                @endif
                            </span>
                        </div>
                        
                        @if ($item['description'])
                            <div class="mt-4 p-3 bg-base-200/50 rounded-box border border-base-300">
                                <div class="text-sm font-semibold text-base-content/70 mb-1">Details</div>
                                <div class="text-sm text-base-content/80">{{ $item['description'] }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if ($this->history->hasPages())
            <div class="mt-8">
                {{ $this->history->links() }}
            </div>
        @endif

        {{-- Empty State --}}
        @if ($this->history->count() === 0)
            @if ($search || $typeFilter || $selectedDate)
                <x-empty-state
                    icon="o-magnifying-glass-circle"
                    title="No History Found"
                    message="No credit score history matches your current filters. Try adjusting your search criteria or clearing filters to see all results."
                    action-label="Clear All Filters"
                    action-wire="clearFilters"
                    size="default"
                />
            @else
                <x-empty-state
                    icon="o-trophy"
                    title="No History Yet"
                    message="No credit score history available yet. Your rewards and penalties will appear here."
                    :show-action="false"
                    size="default"
                />
            @endif
        @endif
    </div>
</div>
