<div class="p-6">
    <x-mary-header title="Attendance Logs" subtitle="All library attendance records" separator />

    <div class="bg-base-200 p-4 rounded-lg mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <x-mary-input label="Search" wire:model.live.debounce.300ms="search"
                              placeholder="Search by name or email..." icon="o-magnifying-glass" />
            </div>

            <div>
                <x-mary-select label="Status" wire:model.live="statusFilter" :options="[
                    ['id' => '', 'name' => 'All Status'],
                    ['id' => 'active', 'name' => 'Active'],
                    ['id' => 'completed', 'name' => 'Completed'],
                ]" option-value="id" option-label="name" />
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
        Showing {{ $this->attendances->count() }} of {{ $this->attendances->total() }} results
    </div>

    {{-- Mobile Card View --}}
    <div class="block lg:hidden space-y-4">
        @foreach ($this->attendances as $attendance)
            <div class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <h3 class="font-semibold text-base">{{ $attendance['user_name'] }}</h3>
                        <p class="text-sm text-base-content/70">{{ $attendance['email'] }}</p>
                    </div>
                    <span class="badge badge-{{ $attendance['status'] == 'completed' ? 'success' : 'warning' }} badge-sm">
                        {{ ucfirst($attendance['status']) }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-4 text-xs">
                    <div>
                        <p class="text-base-content/50 font-medium">Time In</p>
                        @if ($attendance['time_in'])
                            <p class="font-medium">{{ $attendance['time_in']->format('M d, Y') }}</p>
                            <p class="text-base-content/50">{{ $attendance['time_in']->format('H:i') }}</p>
                        @else
                            <p class="text-base-content/50">N/A</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-base-content/50 font-medium">Time Out</p>
                        @if ($attendance['time_out'])
                            <p class="font-medium">{{ $attendance['time_out']->format('M d, Y') }}</p>
                            <p class="text-base-content/50">{{ $attendance['time_out']->format('H:i') }}</p>
                        @else
                            <p class="text-warning font-medium">In Library</p>
                        @endif
                    </div>
                </div>

                @if ($attendance['duration_minutes'])
                    <div class="mt-3 pt-3 border-t border-base-300">
                        <p class="text-base-content/50 font-medium text-xs">Duration</p>
                        <p class="font-medium">
                            {{ floor($attendance['duration_minutes'] / 60) }}h {{ $attendance['duration_minutes'] % 60 }}m
                        </p>
                    </div>
                @endif
            </div>
        @endforeach

        <div class="mt-6">
            {{ $this->attendances->links() }}
        </div>
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden lg:block overflow-x-auto">
        <x-mary-table :headers="$headers" :rows="$this->attendances" :sort-by="$sortBy" with-pagination striped
                      row-class="hover:bg-base-200" header-class="text-base-content bg-base-200" class="w-full min-w-fit table-auto">

            @scope('cell_user_name', $row)
            <div class="font-medium">{{ $row['user_name'] }}</div>
            @endscope

            @scope('cell_email', $row)
            <div class="text-sm text-base-content/70">{{ $row['email'] }}</div>
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
                    <span class="text-warning font-medium">In Library</span>
                @endif
            </div>
            @endscope

            @scope('cell_duration_minutes', $row)
            @if ($row['duration_minutes'])
                <div class="text-sm font-medium">
                    {{ floor($row['duration_minutes'] / 60) }}h {{ $row['duration_minutes'] % 60 }}m
                </div>
            @else
                <span class="text-base-content/50">—</span>
            @endif
            @endscope

            @scope('cell_status', $row)
            <span class="badge badge-{{ $row['status'] == 'completed' ? 'success' : 'warning' }} badge-sm">
                    {{ ucfirst($row['status']) }}
                </span>
            @endscope
        </x-mary-table>
    </div>

    @if ($this->attendances->isEmpty())
        <div class="text-center py-12">
            <h3 class="text-lg font-medium mb-2">No attendance records found</h3>
            <p class="text-base-content/70 mb-4">Try adjusting your search criteria or filters.</p>
            <x-mary-button wire:click="clearFilters" class="btn-outline">
                Clear All Filters
            </x-mary-button>
        </div>
    @endif
</div>
