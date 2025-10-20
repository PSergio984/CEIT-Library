<div class="p-4 sm:p-6 lg:p-8 min-h-screen">
    <div class="mb-6 sm:mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-base-content">Student Dashboard</h1>
        <p class="text-base-content/60 text-sm sm:text-base">Welcome back, {{ Auth::user()->first_name }}!</p>
    </div>

    {{-- Stats Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 sm:gap-6 mb-6 sm:mb-8">
        <x-mary-stat
            title="Credit Score"
            :value="$this->stats['credit_score']"
            icon="o-star"
            class="bg-base-100 shadow-lg"
            color="text-warning"
        />

        <x-mary-stat
            title="Active Borrows"
            :value="$this->stats['active_borrows']"
            icon="o-book-open"
            class="bg-base-100 shadow-lg"
            color="text-primary"
        />

        <x-mary-stat
            title="Total Borrows"
            :value="$this->stats['total_borrows']"
            icon="o-archive-box"
            class="bg-base-100 shadow-lg"
            color="text-secondary"
        />

        <x-mary-stat
            title="Library Visits"
            :value="$this->stats['library_visits']"
            icon="o-building-library"
            class="bg-base-100 shadow-lg"
            color="text-info"
        />

        @if($this->stats['overdue_count'] > 0)
            <x-mary-stat
                title="Overdue Items"
                :value="$this->stats['overdue_count']"
                icon="o-exclamation-triangle"
                class="bg-base-100 shadow-lg"
                color="text-error"
            />
        @else
            <x-mary-stat
                title="Status"
                value="Good Standing"
                icon="o-check-circle"
                class="bg-base-100 shadow-lg"
                color="text-success"
            />
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
        {{-- Active Borrows --}}
        <x-mary-card title="My Active Borrows" class="shadow-lg">
            <x-slot:menu>
                <x-mary-button icon="o-book-open" link="/transactions" class="btn-ghost btn-sm" label="View All" />
            </x-slot:menu>

            <div class="space-y-3">
                @forelse($this->activeBorrows as $borrow)
                    <div class="flex items-start gap-3 p-3 bg-base-200 rounded-lg hover:bg-base-300 transition-colors">
                        <x-mary-icon name="o-document-text" class="w-10 h-10 text-primary mt-1" />
                        <div class="flex-1 min-w-0">
                            <div class="font-medium truncate">{{ $borrow->inventory?->academicPaper?->title ?? 'N/A' }}</div>
                            <div class="text-sm text-base-content/60">
                                Copy #{{ $borrow->inventory?->copy_number ?? 'N/A' }}
                            </div>
                            <div class="text-xs text-base-content/50 mt-1">
                                Due: {{ $borrow->expires_at->format('M d, Y') }}
                                @if($borrow->expires_at->isPast())
                                    <span class="text-error font-semibold ml-2">OVERDUE</span>
                                @elseif($borrow->expires_at->diffInDays(now()) <= 3)
                                    <span class="text-warning font-semibold ml-2">DUE SOON</span>
                                @endif
                            </div>
                        </div>
                    </div>
                      @empty
                    <div class="text-center py-8 text-base-content/60">
                        <x-mary-icon name="o-inbox" class="w-12 h-12 mx-auto mb-2"/>
                        <p>No active borrows</p>
                        <a href="/academic-papers" class="text-primary hover:underline text-sm mt-2 inline-block">
                            Browse academic papers →
                        </a>
                    </div>
                @endforelse
            </div>
        </x-mary-card>

        {{-- Upcoming Due Dates --}}
        <x-mary-card title="Upcoming Due Dates" class="shadow-lg">
            <x-slot:menu>
                <x-mary-button icon="o-calendar" class="btn-ghost btn-sm"/>
            </x-slot:menu>

            <div class="space-y-3">
                @forelse($this->upcomingDueDates as $item)
                    <div class="flex items-center gap-3 p-3 bg-base-200 rounded-lg">
                        <div class="flex-shrink-0 w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
                            <x-mary-icon name="o-calendar-days" class="w-6 h-6 text-primary"/>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-sm truncate">{{ $item->inventory?->academicPaper?->title ?? 'N/A' }}</div>
                            <div class="text-xs text-base-content/60">
                                {{ $item->expires_at->format('M d, Y') }}
                                <span class="ml-1">
                                    ({{ $item->expires_at->diffForHumans() }})
                                </span>
                            </div>
                        </div>
                        @if($item->expires_at->isPast())
                            <x-mary-badge value="Overdue" class="badge-error badge-sm"/>
                        @elseif($item->expires_at->diffInDays(now()) <= 3)
                            <x-mary-badge value="Soon" class="badge-warning badge-sm"/>
                        @endif
                    </div>
                      @empty
                    <div class="text-center py-8 text-base-content/60">
                        <x-mary-icon name="o-check-circle" class="w-12 h-12 mx-auto mb-2 text-success"/>
                        <p>No upcoming due dates</p>
                    </div>
                @endforelse
            </div>
        </x-mary-card>

        {{-- Recent Activity --}}
        <x-mary-card title="Recent Activity" class="shadow-lg">
            <x-slot:menu>
                <x-mary-button icon="o-clock" class="btn-ghost btn-sm"/>
            </x-slot:menu>

            <div class="space-y-3">
                @forelse($this->recentActivity as $activity)
                    <div class="flex items-start gap-3 p-3 bg-base-200 rounded-lg">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-base-300 flex items-center justify-center">
                            <x-mary-icon :name="$activity['icon']" class="w-5 h-5 {{ $activity['color'] }}"/>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-sm">{{ $activity['title'] }}</div>
                            <div class="text-xs text-base-content/60">{{ $activity['description'] }}</div>
                            <div class="text-xs text-base-content/50 mt-1">
                                {{ $activity['date']->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-base-content/60">
                        <x-mary-icon name="o-inbox" class="w-12 h-12 mx-auto mb-2"/>
                        <p>No recent activity</p>
                    </div>
                @endforelse
            </div>
        </x-mary-card>

        {{-- Credit Score History --}}
        <x-mary-card title="Credit Score History" class="shadow-lg">
            <x-slot:menu>
                <x-mary-button icon="o-chart-bar" link="/credit-score-history" class="btn-ghost btn-sm" label="View All"/>
            </x-slot:menu>

            <div class="space-y-3">
                @forelse($this->creditScoreHistory as $history)
                    <div class="flex items-center gap-3 p-3 bg-base-200 rounded-lg">
                        <div class="flex-shrink-0 w-10 h-10 rounded-full 
                            {{ $history['type'] === 'reward' ? 'bg-success/10' : 'bg-error/10' }} 
                            flex items-center justify-center">
                            <span class="font-bold {{ $history['type'] === 'reward' ? 'text-success' : 'text-error' }}">
                                {{ $history['points'] }}
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-sm">{{ $history['reason'] }}</div>
                            <div class="text-xs text-base-content/60">
                                {{ $history['date']->format('M d, Y') }}
                            </div>
                        </div>
                        <x-mary-badge 
                            :value="ucfirst($history['type'])" 
                            class="{{ $history['type'] === 'reward' ? 'badge-success' : 'badge-error' }} badge-sm"
                        />
                    </div>
                @empty
                    <div class="text-center py-8 text-base-content/60">
                        <x-mary-icon name="o-chart-bar" class="w-12 h-12 mx-auto mb-2"/>
                        <p>No credit score history</p>
                    </div>
                @endforelse
            </div>
        </x-mary-card>
    </div>

    {{-- Available Papers Section --}}
    <div class="mt-6 sm:mt-8">
        <x-mary-card title="Recently Added Papers" class="shadow-lg">
            <x-slot:menu>
                <x-mary-button icon="o-magnifying-glass" link="/academic-papers" class="btn-ghost btn-sm" label="Browse All"/>
            </x-slot:menu>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                @forelse($this->availablePapers as $paper)
                    <a href="/academic-papers/{{ $paper->department }}" 
                       class="block p-4 bg-base-200 rounded-lg hover:bg-base-300 transition-colors">
                        <div class="flex items-start gap-3">
                            <x-mary-icon name="o-document-text" class="w-8 h-8 text-primary flex-shrink-0"/>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-medium text-sm line-clamp-2 mb-2">{{ $paper->title }}</h3>
                                <div class="text-xs text-base-content/60 space-y-1">
                                    <div>{{ $paper->department }}</div>
                                    <div>{{ $paper->paper_type }}</div>
                                    <div class="flex items-center gap-1">
                                        <x-mary-badge 
                                            :value="$paper->available_count . ' available'" 
                                            class="badge-success badge-xs"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full text-center py-8 text-base-content/60">
                        <x-mary-icon name="o-inbox" class="w-12 h-12 mx-auto mb-2"/>
                        <p>No papers available</p>
                    </div>
                @endforelse
            </div>
        </x-mary-card>
    </div>

    {{-- Quick Actions --}}
    <div class="mt-6 sm:mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="/academic-papers" class="btn btn-primary gap-2">
            <x-mary-icon name="o-magnifying-glass" class="w-5 h-5"/>
            Browse Papers
        </a>
        <a href="/transactions" class="btn btn-outline gap-2">
            <x-mary-icon name="o-archive-box" class="w-5 h-5"/>
            My Transactions
        </a>
        <a href="/credit-score-history" class="btn btn-outline gap-2">
            <x-mary-icon name="o-chart-bar" class="w-5 h-5"/>
            Credit History
        </a>
        <a href="/rule-and-regulation" class="btn btn-outline gap-2">
            <x-mary-icon name="o-clipboard-document-list" class="w-5 h-5"/>
            Rules & Policies
        </a>
    </div>
</div>
