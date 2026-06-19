<div class="p-4 sm:p-6 lg:p-8 bg-base-200 min-h-screen">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-base-content">Admin Dashboard</h1>
        <p class="text-base-content/60">Library management overview</p>
    </div>

    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <x-mary-stat
            title="Total Users"
            :value="$this->stats['total_users']"
            icon="o-user-group"
            class="bg-base-100 shadow-lg"
            color="text-primary"
        />

        <x-mary-stat
            title="Academic Papers"
            :value="$this->stats['total_papers']"
            icon="o-document-text"
            class="bg-base-100 shadow-lg"
            color="text-secondary"
        />

        <x-mary-stat
            title="Available Copies"
            :value="$this->stats['available_copies'] . ' / ' . $this->stats['total_copies']"
            icon="o-book-open"
            class="bg-base-100 shadow-lg"
            color="text-accent"
        />

        <x-mary-stat
            title="Active Sessions"
            :value="$this->stats['active_sessions']"
            icon="o-clock"
            class="bg-base-100 shadow-lg"
            color="text-success"
        />
    </div>

    {{-- Secondary Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <x-mary-card title="Today's Attendance" class="shadow-lg hover:scale-[1.01] transition-transform duration-300">
            <div class="flex items-center gap-4">
                <div class="bg-info/10 p-3 rounded-xl">
                    <x-mary-icon name="o-user-group" class="w-10 h-10 text-info"/>
                </div>
                <div>
                    <div class="text-3xl font-bold">{{ $this->stats['today_attendance'] }}</div>
                    <div class="text-sm text-base-content/60 font-medium">Library visitors today</div>
                </div>
            </div>
        </x-mary-card>

        <x-mary-card title="Active Borrows" class="shadow-lg hover:scale-[1.01] transition-transform duration-300">
            <div class="flex items-center gap-4">
                <div class="bg-warning/10 p-3 rounded-xl">
                    <x-mary-icon name="o-arrow-path" class="w-10 h-10 text-warning"/>
                </div>
                <div>
                    <div class="text-3xl font-bold">{{ $this->stats['active_borrows'] }}</div>
                    <div class="text-sm text-base-content/60 font-medium">Currently borrowed</div>
                </div>
            </div>
        </x-mary-card>

        <x-mary-card title="Active Librarians" class="shadow-lg hover:scale-[1.01] transition-transform duration-300">
            <div class="flex items-center gap-4">
                <div class="bg-success/10 p-3 rounded-xl">
                    <x-mary-icon name="o-shield-check" class="w-10 h-10 text-success"/>
                </div>
                <div>
                    <div class="text-3xl font-bold">{{ $this->stats['active_librarians'] }}</div>
                    <div class="text-sm text-base-content/60 font-medium">On duty</div>
                </div>
            </div>
        </x-mary-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        {{-- Loan Trends (7 Days) --}}
        <x-mary-card title="Loan Trends (Last 7 Days)" class="shadow-lg lg:col-span-2">
            <x-slot:menu>
                <x-mary-badge :value="now()->format('M Y')" class="badge-primary badge-sm whitespace-nowrap shrink-0" />
            </x-slot:menu>

            <div class="h-64 flex flex-col items-center justify-center relative">
                @php 
                    $hasTrendsData = $this->loanTrends->contains(fn($t) => $t['count'] > 0);
                    $maxCount = max($this->loanTrends->pluck('count')->toArray()) ?: 1; 
                @endphp
                
                @if(!$hasTrendsData)
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-base-content/40 z-10">
                        <x-mary-icon name="o-presentation-chart-bar" class="w-12 h-12 mb-2"/>
                        <p class="text-xs font-semibold">No borrowing activity in the last 7 days</p>
                    </div>
                @endif
                
                <div class="w-full h-full flex items-end justify-between gap-2 px-2 {{ !$hasTrendsData ? 'opacity-10' : '' }}">
                    @foreach($this->loanTrends as $trend)
                        <div wire:key="trend-{{ $trend['day'] }}" class="flex-1 flex flex-col items-center group">
                            <div class="w-full relative flex flex-col items-center">
                                {{-- Bar --}}
                                <div class="w-full bg-primary/20 rounded-t-lg transition-all duration-500 group-hover:bg-primary/40 relative" 
                                    style="height: {{ ($trend['count'] / $maxCount) * 180 }}px;">
                                    {{-- Value Label (shows on hover) --}}
                                    <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-slate-800 text-white text-[10px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity">
                                        {{ $trend['count'] }}
                                    </div>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold mt-2 text-base-content/60 uppercase tracking-wider">{{ $trend['day'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-mary-card>

        {{-- Top Borrowers --}}
        <x-mary-card title="Top Borrowers" class="shadow-lg">
            <x-slot:menu>
                <x-mary-icon name="o-trophy" class="btn-ghost btn-sm text-warning"/>
            </x-slot:menu>

            <div class="space-y-4">
                @forelse($this->topBorrowers as $index => $user)
                    <div wire:key="borrower-{{ $user->id }}" class="flex items-center gap-3 p-2 bg-base-100/50 rounded-xl hover:bg-base-100 transition-colors duration-200">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary/10 text-primary font-bold text-xs">
                            {{ $index + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-sm truncate">{{ $user->first_name }} {{ $user->last_name }}</div>
                            <div class="text-[10px] text-base-content/50 font-medium">SCORE: {{ $user->credit_score }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-black">{{ $user->borrow_transactions_count }}</div>
                            <div class="text-[10px] opacity-40 leading-none">LOANS</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-base-content/60">
                        <x-mary-icon name="o-user-group" class="w-12 h-12 mx-auto mb-2"/>
                        <p>No borrowing activity yet</p>
                    </div>
                @endforelse
            </div>
        </x-mary-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Papers by Department --}}
        <x-mary-card title="Papers by Department" class="shadow-lg">
            <x-slot:menu>
                <x-mary-icon name="o-academic-cap" class="btn-ghost btn-sm"/>
            </x-slot:menu>

            <div class="space-y-4">
                @foreach($this->departmentStats as $dept)
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-xs font-bold uppercase tracking-wide opacity-70">{{ $dept['name'] }}</span>
                            <span class="text-xs font-bold text-primary">{{ $dept['value'] }}</span>
                        </div>
                        <x-mary-progress
                            :value="($dept['value'] / max($this->stats['total_papers'], 1)) * 100"
                            class="progress-primary h-2"
                        />
                    </div>
                @endforeach
            </div>

        </x-mary-card>

        {{-- Recent Borrowed Papers --}}
        <x-mary-card title="Recent Borrowed Papers" class="shadow-lg">
            <x-slot:menu>
                <x-mary-icon name="o-book-open" class="btn-ghost btn-sm"/>
            </x-slot:menu>

            <div class="space-y-3">
                @forelse($this->recentBorrowedPapers as $borrow)
                    <div wire:key="recent-{{ $borrow->id }}" class="flex items-center gap-4 p-3 bg-base-100/50 rounded-xl hover:bg-base-100 transition-colors duration-200">
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-sm truncate">{{ $borrow->inventory->academicPaper->title }}</div>
                            <div class="text-[10px] text-base-content/60 mt-0.5">
                                <span class="font-bold text-primary">{{ $borrow->user->first_name }} {{ $borrow->user->last_name }}</span>
                                <span class="mx-1">•</span>
                                <span>{{ $borrow->academicPaper->paper_type }}</span>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-[10px] font-bold text-base-content/40 mb-1">
                                {{ $borrow->created_at->diffForHumans(null, true) }}
                            </div>
                            <x-mary-badge
                                :value="strtoupper($borrow->status)"
                                class="{{ $borrow->status === 'started' ? 'badge-warning' : 'badge-success' }} badge-xs font-black px-2"
                            />
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-base-content/60">
                        <x-mary-icon name="o-inbox" class="w-12 h-12 mx-auto mb-2"/>
                        <p>No recent borrow activities</p>
                    </div>
                @endforelse
            </div>
        </x-mary-card>

    </div>
</div>
