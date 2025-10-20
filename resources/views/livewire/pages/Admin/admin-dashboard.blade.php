<div class="p-8 bg-base-200 min-h-screen">
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
        <x-mary-card title="Today's Attendance" class="shadow-lg">
            <div class="flex items-center gap-4">
                <x-mary-icon name="o-user-group" class="w-12 h-12 text-info"/>
                <div>
                    <div class="text-3xl font-bold">{{ $this->stats['today_attendance'] }}</div>
                    <div class="text-sm text-base-content/60">Library visitors today</div>
                </div>
            </div>
        </x-mary-card>

        <x-mary-card title="Active Borrows" class="shadow-lg">
            <div class="flex items-center gap-4">
                <x-mary-icon name="o-arrow-path" class="w-12 h-12 text-warning"/>
                <div>
                    <div class="text-3xl font-bold">{{ $this->stats['active_borrows'] }}</div>
                    <div class="text-sm text-base-content/60">Currently borrowed</div>
                </div>
            </div>
        </x-mary-card>

        <x-mary-card title="Active Librarians" class="shadow-lg">
            <div class="flex items-center gap-4">
                <x-mary-icon name="o-shield-check" class="w-12 h-12 text-success"/>
                <div>
                    <div class="text-3xl font-bold">{{ $this->stats['active_librarians'] }}</div>
                    <div class="text-sm text-base-content/60">On duty</div>
                </div>
            </div>
        </x-mary-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Papers by Department --}}
        <x-mary-card title="Papers by Department" class="shadow-lg">
            <x-slot:menu>
                <x-mary-button icon="o-academic-cap" link="#" class="btn-ghost btn-sm"/>
            </x-slot:menu>

            <div class="space-y-4">
                @foreach($this->departmentStats as $dept)
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium">{{ $dept['name'] }}</span>
                            <span class="text-sm text-base-content/60">{{ $dept['value'] }}</span>
                        </div>
                        <x-mary-progress
                            :value="($dept['value'] / $this->stats['total_papers']) * 100"
                            class="progress-primary"
                        />
                    </div>
                @endforeach
            </div>

        </x-mary-card>

        <x-mary-card title="Academic Papers By Category" class="shadow-lg">
            {{-- Academic Papers By Categiry --}}
            <x-slot:menu>
                <x-mary-button icon="o-document-chart-bar" link="#" class="btn-ghost btn-sm"/>
            </x-slot:menu>

            <div class="space-y-4">
                @foreach($this->academicPaperStats as $paper)
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium">{{ $paper['name'] }}</span>
                            <span class="text-sm text-base-content/60">{{ $paper['value'] }}</span>
                        </div>
                        <x-mary-progress
                            :value="($paper['value'] / $this->stats['total_papers']) * 100"
                            class="progress-primary"
                        />
                    </div>
                @endforeach
            </div>

        </x-mary-card>

        {{-- Recent Borrowed Papers --}}
        <x-mary-card title="Recent Borrowed Papers" class="shadow-lg">
            <x-slot:menu>
                <x-mary-button icon="o-book-open" link="#" class="btn-ghost btn-sm"/>
            </x-slot:menu>

            <div class="space-y-3">
                @forelse($this->recentBorrowedPapers as $borrow)
                    <div class="flex items-center gap-3 p-3 bg-base-200 rounded-lg">
                        <div class="flex-1">
                            <div class="font-medium">{{ $borrow->inventory->academicPaper->title }}</div>
                            <div class="text-sm text-base-content/60">
                                Borrowed by: {{ $borrow->user->first_name }} {{ $borrow->user->last_name }}
                            </div>
                            <div class="text-sm text-base-content/60">
                                Paper Type: {{ $borrow->academicPaper->paper_type }} |
                                Publication Year: {{ $borrow->academicPaper->publication_year }}
                            </div>
                            <div class="text-sm text-base-content/60">
                                Author's: {{ $borrow->academicPaper->authors->first()?->name ?? 'N/A' }}
                            </div>
                        </div>
                        <div class="text-right">
                            <x-mary-badge
                                :value="ucfirst($borrow->status)"
                                class="{{ $borrow->status === 'started' ? 'badge-warning' : 'badge-success' }} badge-sm"
                            />
                            <div class="text-xs text-base-content/60 mt-1">
                                {{ $borrow->created_at->diffForHumans() }}
                            </div>
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

        {{-- Top Borrowers --}}
        <x-mary-card title="Top Borrowers" class="shadow-lg">
            <x-slot:menu>
                <x-mary-button icon="o-trophy" link="#" class="btn-ghost btn-sm"/>
            </x-slot:menu>

            <div class="space-y-3">
                @forelse($this->topBorrowers as $index => $user)
                    <div class="flex items-center gap-3 p-3 bg-base-200 rounded-lg">
                        <div
                            class="flex items-center justify-center w-8 h-8 rounded-full bg-primary text-primary-content font-bold">
                            {{ $index + 1 }}
                        </div>
                        <div class="flex-1">
                            <div class="font-medium">{{ $user->first_name }} {{ $user->last_name }}</div>
                            <div class="text-sm text-base-content/60">Credit Score: {{ $user->credit_score }}</div>
                        </div>
                        <x-mary-badge :value="$user->borrow_transactions_count . ' borrows'" class="badge-primary"/>
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
</div>
