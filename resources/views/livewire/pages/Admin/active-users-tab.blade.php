<div>
    <x-mary-tab name="active-users-tab" label="Active Users" icon="o-users">
        <div class="mt-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold">Users Currently in Library (Today)</h3>
            </div>

            <div class="bg-base-200 p-4 rounded-lg mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-mary-input label="search" wire:model.live="searchActiveUsers" placeholder="Search users..."
                                      icon="o-magnifying-glass" clearable/>
                    </div>
                    <div class="flex justify-end items-end">
                        <x-mary-button wire:click="clearActiveUsersFilters" class="btn-outline btn-sm"
                                       icon="o-x-mark">
                            Clear Filters
                        </x-mary-button>
                    </div>
                </div>
            </div>

            <div class="mb-4 text-xs sm:text-sm text-base-content/70">
                Showing {{ $this->activeUsers->count() }} of {{ $this->activeUsers->total() }} results
            </div>

            {{-- Mobile Card View --}}
            <div class="block lg:hidden space-y-4">
                @foreach ($this->activeUsers as $attendance)
                    <div class="card bg-base-100 shadow-md">
                        <div class="card-body p-4">
                            <div class="flex justify-between items-start mb-2">
                                <div class="badge badge-success">Active</div>
                            </div>

                            <div class="space-y-2 text-sm">
                                <div>
                                    <span class="font-semibold">Name:</span>
                                    <span>{{ $attendance->user->first_name }} {{ $attendance->user->last_name }}</span>
                                </div>
                                <div>
                                    <span class="font-semibold">Credit Score:</span>
                                    <span
                                        class="badge badge-lg {{ $attendance->user->credit_score >= 70 ? 'badge-success' : ($attendance->user->credit_score >= 40 ? 'badge-warning' : 'badge-error') }}">
                            {{ $attendance->user->credit_score }}/100
                        </span>
                                </div>
                                <div>
                                    <span class="font-semibold">Time In:</span>
                                    <span>{{ $attendance->time_in->format('D, M d h:i A') }}</span>
                                </div>
                            </div>

                            <div class="card-actions justify-end mt-4">
                                <x-mary-button wire:click="openViolationDrawer({{ $attendance->user->id }})"
                                               class="btn-error btn-sm" icon="o-exclamation-triangle">
                                    Record Violation
                                </x-mary-button>

                                <x-mary-button
                                    wire:click="openDeclareForgotTimeoutModal({{ $attendance->id }})"
                                    class="btn-warning btn-sm"
                                    icon="o-clock"
                                    tooltip-left="Declare forgot timeout"
                                >
                                    Declare Forgot Timeout
                                </x-mary-button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Desktop Table View --}}
            <div class="hidden lg:block overflow-x-auto">
                <x-mary-table :headers="$activeUsersHeaders" :rows="$this->activeUsers"
                              :sort-by="$sortBy" with-pagination
                              :per-page="$perPageActiveUsers" :per-page-values="[10, 20, 50]" striped
                              row-class="hover:bg-base-200" header-class="text-base-content bg-base-200">

                    @scope('cell_user.name', $attendance)
                    <div>{{ $attendance->user->first_name }} {{ $attendance->user->last_name }}</div>
                    @endscope

                    @scope('cell_user.credit_score', $attendance)
                    <div
                        class="badge badge-lg {{ $attendance->user->credit_score >= 70 ? 'badge-success' : ($attendance->user->credit_score >= 40 ? 'badge-warning' : 'badge-error') }}">
                        {{ $attendance->user->credit_score }}/100
                    </div>
                    @endscope

                    @scope('cell_time_in', $attendance)
                    <div class="text-sm">
                        {{ $attendance->time_in->format('D, M d h:i A') }}
                    </div>
                    @endscope

                    @scope('actions', $attendance)
                    <div class="flex gap-2">
                        <x-mary-button wire:click="openViolationDrawer({{ $attendance->user->id }})"
                                       class="btn-error btn-sm" icon="o-exclamation-triangle"
                                       spinner
                                       tooltip-left="Record Violation">
                            Violation
                        </x-mary-button>

                        <x-mary-button
                            wire:click="openDeclareForgotTimeoutModal({{ $attendance->id }})"
                            wire:loading.attr="disabled"
                            class="btn-warning btn-sm"
                            icon="o-clock"
                            spinner
                            tooltip-left="Declare forgot timeout"
                        >
                            Declare Forgot Timeout
                        </x-mary-button>
                    </div>
                    @endscope
                </x-mary-table>
            </div>


            @if ($this->activeUsers->isEmpty())
                <div class="text-center py-12">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-base-content/30"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <p class="text-base-content/50 mt-4">No active users in library today</p>
                </div>
            @endif
        </div>
    </x-mary-tab>

    {{-- Record Violation Drawer --}}
    <x-mary-drawer wire:model="ViolationDrawer" class="w-11/12 lg:w-1/3" right>
        <div class="px-2 py-3">
            <h3 class="text-lg font-semibold mb-4">Record Violation</h3>

            @if($selectedUser)

                <div class="alert alert-info mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                         class="stroke-current shrink-0 w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <div class="font-bold">{{ $selectedUser->first_name }} {{ $selectedUser->last_name }}</div>
                        <div class="text-xs">Current Credit: {{ $selectedUser->credit_score }}/100</div>
                    </div>
                </div>

                <x-mary-form wire:submit.prevent="recordViolation" class="space-y-4">
                    <x-mary-select
                        label="Violation Type"
                        wire:model="selectedViolationId"
                        :options="$this->violationOptions"
                        placeholder="Select violation"
                        icon="o-shield-exclamation"
                        required
                    />

                    <x-mary-select
                        label="Severity"
                        wire:model="violationSeverity"
                        :options="$severityOptions"
                        icon="o-signal"
                        required
                    />

                    <x-mary-textarea
                        label="Remarks (Optional)"
                        wire:model="violationRemarks"
                        placeholder="Add any additional notes..."
                        rows="4"
                    />

                    <div class="flex justify-end gap-2 pt-2">
                        <x-mary-button type="button" label="Cancel" @click="$wire.ViolationDrawer = false"/>
                        <x-mary-button type="submit" class="btn-error" label="Record Violation" spinner/>
                    </div>
                </x-mary-form>
            @endif
        </div>
    </x-mary-drawer>

    {{-- Confirmation Modal for Forgot Timeout --}}
    <x-mary-modal wire:model="confirmForgotTimeoutModal" title="Confirm Declare Forgot Timeout">
        <div class="p-4">
            <p>Declare this user as forgot-to-timeout and apply the penalty?</p>

            <div class="flex justify-end gap-2 mt-4">
                <x-mary-button type="button" label="Cancel" class="btn-outline"
                               @click="$wire.confirmForgotTimeoutModal = false"/>
                <x-mary-button type="button" class="btn-warning" wire:click="confirmDeclareForgotTimeout" spinner>
                    Confirm
                </x-mary-button>
            </div>
        </div>
    </x-mary-modal>
</div>