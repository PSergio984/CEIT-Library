{{-- PLV eLib Transaction History - Modernized Design --}}
<div class="min-h-screen bg-base-200/30 p-4 md:p-6">
    {{-- Header Section --}}
    <div class="max-w-7xl mx-auto mb-6">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-3xl md:text-4xl font-bold text-base-content flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Your Transaction History
                </h1>
                <p class="text-sm text-base-content/60 mt-1">Track all your borrowed materials and their status</p>
            </div>
            <div class="stats shadow bg-base-100">
                <div class="stat py-4 px-6">
                    <div class="stat-title text-xs">Total Transactions</div>
                    <div class="stat-value text-2xl text-primary">{{ $this->transactions->total() }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters Section --}}
    <div class="max-w-7xl mx-auto mb-6">
        <div class="bg-base-100 rounded-box shadow-lg p-6">
            <div class="flex items-center gap-2 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-base-content/70">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                </svg>
                <h2 class="font-semibold text-base-content">Filter Transactions</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                {{-- Search --}}
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">Search</span>
                    </label>
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search"
                        placeholder="Title, author, keywords..." 
                        class="input input-bordered focus:input-primary transition-all" 
                    />
                </div>

                {{-- Paper Type Filter --}}
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">Paper Type</span>
                    </label>
                    <select wire:model.live="paperTypeFilter" class="select select-bordered focus:select-primary">
                        <option value="">All Types</option>
                        @foreach ($this->paperTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status Filter --}}
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-medium">Status</span>
                    </label>
                    <select wire:model.live="statusFilter" class="select select-bordered focus:select-primary">
                        <option value="">All Status</option>
                        <option value="started">Borrowed</option>
                        <option value="completed">Returned</option>
                        <option value="overdue">Overdue</option>
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
                <div class="form-control">
                    <label class="label">
                        <span class="label-text opacity-0">Clear</span>
                    </label>
                    <button 
                        wire:click="clearFilters" 
                        class="btn btn-outline btn-error gap-2 hover:scale-105 transition-transform"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Clear Filters
                    </button>
                </div>
            </div>

            {{-- Results Count --}}
            <div class="mt-4 text-sm text-base-content/70">
                Showing <span class="font-semibold text-base-content">{{ $this->transactions->count() }}</span> of 
                <span class="font-semibold text-base-content">{{ $this->transactions->total() }}</span> results
            </div>
        </div>
    </div>

    {{-- Transactions List (Unified for Mobile & Desktop) --}}
    <div class="max-w-7xl mx-auto">
        @forelse ($this->transactions as $transaction)
            <div 
                wire:key="transaction-{{ $transaction['id'] }}"
                class="bg-base-100 rounded-box shadow-lg hover:shadow-2xl transition-all duration-300 mb-6 overflow-hidden border border-base-300 hover:border-primary/30"
            >
                {{-- Card Header --}}
                <div class="bg-gradient-to-r from-primary/10 to-secondary/10 p-4 md:p-6 border-b border-base-300">
                    <div class="flex items-start justify-between flex-wrap gap-4">
                        <div class="flex-1 min-w-0">
                            {{-- Department & Type --}}
                            <div class="flex items-center gap-2 mb-2 flex-wrap">
                                <span class="badge badge-outline badge-sm">
                                    {{ $transaction['department'] }}
                                </span>
                                <span class="badge badge-neutral badge-sm">
                                    {{ $transaction['paper_type'] }}
                                </span>
                            </div>

                            {{-- Title --}}
                            <h3 class="text-xl md:text-2xl font-bold text-base-content leading-tight mb-2">
                                {{ $transaction['title'] }}
                            </h3>

                            {{-- Copy Info with Better Labeling --}}
                            <div class="flex items-center gap-2 text-sm text-base-content/70">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" />
                                </svg>
                                <span class="font-medium">Copy #{{ $transaction['copy_number'] }}</span>
                                <span class="text-base-content/50">•</span>
                                <div x-data="{ open: false }" class="relative">
                                    <button type="button"
                                        class="font-mono text-xs underline decoration-dotted focus:outline-none focus:ring-2 focus:ring-primary/60"
                                        aria-describedby="tooltip-inventory-{{ $transaction['id'] }}"
                                        @mouseenter="open = true" @mouseleave="open = false"
                                        @focus="open = true" @blur="open = false"
                                    >
                                        ID: {{ $transaction['inventory']->id }}
                                    </button>
                                    <div
                                        x-show="open"
                                        id="tooltip-inventory-{{ $transaction['id'] }}"
                                        role="tooltip"
                                        class="absolute left-1/2 z-20 mt-2 w-56 -translate-x-1/2 rounded bg-base-200 px-3 py-2 text-xs text-base-content shadow-lg border border-base-300 transition-opacity duration-150"
                                        x-cloak
                                    >
                                        Internal inventory tracking number
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Status Badge --}}
                        <div class="flex-shrink-0">
                            <x-transaction-status :status="$transaction['status']" />
                        </div>
                    </div>
                </div>

                {{-- Card Body --}}
                <div class="p-4 md:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {{-- Borrowed Date with Full DateTime --}}
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-sm font-semibold text-base-content/60">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                </svg>
                                Date Borrowed
                            </div>
                            <div class="text-base md:text-lg font-bold text-base-content">
                                @if ($transaction['time_in'])
                                    {{ $transaction['time_in']->format('M d, Y') }}
                                    <span class="text-sm font-normal text-base-content/70">
                                        {{ $transaction['time_in']->format('h:i A') }}
                                    </span>
                                @else
                                    <span class="text-base-content/50">N/A</span>
                                @endif
                            </div>
                        </div>

                        {{-- Due Date / Return Date --}}
                        @if ($transaction['status'] === 'completed')
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 text-sm font-semibold text-success/80">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Date Returned
                                </div>
                                <div class="text-base md:text-lg font-bold text-success">
                                    @if ($transaction['time_out'])
                                        {{ $transaction['time_out']->format('M d, Y') }}
                                        <span class="text-sm font-normal text-success/70">
                                            {{ $transaction['time_out']->format('h:i A') }}
                                        </span>
                                    @else
                                        <span class="text-base-content/50">Not Returned</span>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 text-sm font-semibold {{ $transaction['is_overdue'] ? 'text-error/80' : 'text-warning/80' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Due Date
                                </div>
                                <div class="text-base md:text-lg font-bold {{ $transaction['is_overdue'] ? 'text-error' : 'text-warning' }}">
                                    @if ($transaction['expires_at'])
                                        {{ $transaction['expires_at']->format('M d, Y') }}
                                        <span class="text-sm font-normal">
                                            {{ $transaction['expires_at']->format('h:i A') }}
                                        </span>
                                    @else
                                        <span class="text-base-content/50">N/A</span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Countdown Timer / Overdue Duration --}}
                        <div class="space-y-1">
                            @if (in_array($transaction['status'], ['started', 'overdue']))
                                @if ($transaction['is_overdue'])
                                    {{-- Overdue Duration Display --}}
                                    <div class="flex items-center gap-2 text-sm font-semibold text-error/80">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        </svg>
                                        Overdue By
                                    </div>
                                    <div class="text-base md:text-lg font-bold text-error">
                                        {{ $transaction['overdue_duration'] }}
                                    </div>
                                @else
                                    @if ($transaction['expires_at'])
                                        {{-- Real-time Countdown Timer --}}
                                        <div class="flex items-center gap-2 text-sm font-semibold text-info/80">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 animate-pulse">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Time Remaining
                                        </div>
                                        <div
                                            x-data="countdownTimer('{{ $transaction['expires_at']->toIso8601String() }}')"
                                            class="flex items-center gap-2"
                                            aria-live="polite"
                                        >
                                            <div class="grid grid-flow-col gap-2 text-center auto-cols-max">
                                                <div class="flex flex-col p-2 bg-info/10 rounded-box text-info">
                                                    <span class="countdown font-mono text-2xl" x-text="String(hours).padStart(2, '0')"></span>
                                                    <span class="text-xs">hours</span>
                                                </div>
                                                <div class="flex flex-col p-2 bg-info/10 rounded-box text-info">
                                                    <span class="countdown font-mono text-2xl" x-text="String(minutes).padStart(2, '0')"></span>
                                                    <span class="text-xs">min</span>
                                                </div>
                                                <div class="flex flex-col p-2 bg-info/10 rounded-box text-info">
                                                    <span class="countdown font-mono text-2xl" x-text="String(seconds).padStart(2, '0')"></span>
                                                    <span class="text-xs">sec</span>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-base-content/50 italic">No due date</div>
                                    @endif
                                @endif
                            @else
                                {{-- Duration for completed/other transactions --}}
                                <div class="flex items-center gap-2 text-sm font-semibold text-base-content/60">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Duration
                                </div>
                                <div class="text-base md:text-lg font-bold text-base-content">
                                    {{ $transaction['duration'] }}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Notes Section --}}
                    @if ($transaction['notes'])
                        <div class="mt-6 p-4 bg-base-200/50 rounded-box border border-base-300">
                            <div class="flex items-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-base-content/60 flex-shrink-0 mt-0.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                                <div class="flex-1">
                                    <div class="text-sm font-semibold text-base-content/70 mb-1">Notes</div>
                                    <div class="text-sm text-base-content/80">{{ $transaction['notes'] }}</div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            {{-- Empty State --}}
            <div class="bg-base-100 rounded-box shadow-lg p-12 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-24 h-24 mx-auto text-base-content/20 mb-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
                
                <h3 class="text-2xl font-bold text-base-content mb-2">No Transactions Found</h3>
                <p class="text-base-content/60 mb-6">
                    @if ($search || $statusFilter || $paperTypeFilter || $selectedDate)
                        No transactions match your current filters. Try adjusting your search criteria.
                    @else
                        You haven't borrowed any materials yet. Visit the library to get started!
                    @endif
                </p>
                
                @if ($search || $statusFilter || $paperTypeFilter || $selectedDate)
                    <button wire:click="clearFilters" class="btn btn-primary gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Clear All Filters
                    </button>
                @endif
            </div>
        @endforelse

        {{-- Pagination --}}
        @if ($this->transactions->hasPages())
            <div class="mt-8">
                {{ $this->transactions->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Alpine.js countdownTimer component for transaction timers --}}
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('countdownTimer', (expiresAtIso) => {
            let interval = null;
            return {
                expiresAt: new Date(expiresAtIso),
                hours: 0,
                minutes: 0,
                seconds: 0,
                elapsed: false,
                init() {
                    this.update();
                    interval = setInterval(() => this.update(), 1000);
                },
                update() {
                    const now = new Date();
                    let diff = this.expiresAt - now;
                    if (diff <= 0) {
                        this.hours = 0;
                        this.minutes = 0;
                        this.seconds = 0;
                        this.elapsed = true;
                        clearInterval(interval);
                        return;
                    }
                    this.hours = Math.floor(diff / (1000 * 60 * 60));
                    this.minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    this.seconds = Math.floor((diff % (1000 * 60)) / 1000);
                },
                destroy() {
                    if (interval) {
                        clearInterval(interval);
                        interval = null;
                    }
                }
            };
        });
    });
</script>
