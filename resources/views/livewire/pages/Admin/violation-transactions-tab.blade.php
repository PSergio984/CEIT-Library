<div>
    <x-mary-tab name="transactions-tab" label="Violation Transactions" icon="o-document-text">
        <div class="mt-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold">Violation Records</h3>
            </div>

            <div class="bg-base-200 p-4 rounded-lg mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <x-mary-input label="search" wire:model.live="searchTransaction"
                                      placeholder="Search user or violation..."
                                      icon="o-magnifying-glass" clearable/>
                    </div>
                    <div>
                        <x-mary-select label="Filter By Severity" wire:model.live="severityFilter" :options="[
                            ['id' => '', 'name' => 'All Severities'],
                            ['id' => 'Minor', 'name' => 'Minor'],
                            ['id' => 'Major', 'name' => 'Major'],
                            ['id' => 'Critical', 'name' => 'Critical']
                        ]" placeholder="Filter by severity" icon="o-funnel"/>
                    </div>
                    <div>
                        <x-mary-datetime label="Filter by Date" wire:model.live="dateFilter" type="date"
                                         max="{{ date('Y-m-d') }}"/>
                    </div>
                    <div class="flex justify-end items-end">
                        <x-mary-button wire:click="clearTransactionFilters" class="btn-outline btn-sm" icon="o-x-mark">
                            Clear Filters
                        </x-mary-button>
                    </div>
                </div>
            </div>

            <div class="mb-4 text-xs sm:text-sm text-base-content/70">
                Showing {{ $this->violationTransactions->count() }} of {{ $this->violationTransactions->total() }}
                results
            </div>

            {{-- Mobile Card View --}}
            <div class="block lg:hidden space-y-4">
                @foreach ($this->violationTransactions as $transaction)
                    <div class="card bg-base-100 shadow-md">
                        <div class="card-body p-4">
                            <div class="flex justify-between items-start mb-2">
                                <div class="badge badge-primary">{{ $transaction->id }}</div>
                                <x-mary-badge :value="$transaction->severity" class="badge-sm"
                                              :class="[
                                            'Minor' => 'badge-info',
                                            'Major' => 'badge-warning',
                                            'Critical' => 'badge-error badge-outline'
                                        ][$transaction->severity] ?? 'badge-ghost'"/>
                            </div>

                            <div class="space-y-2 text-sm">
                                <div>
                                    <span class="font-semibold">User:</span>
                                    <span>{{ $transaction->user->first_name }} {{ $transaction->user->last_name }}</span>
                                </div>
                                <div>
                                    <span class="font-semibold">Violation:</span>
                                    <span>{{ $transaction->violation->name }}</span>
                                </div>
                                <div>
                                    <span class="font-semibold">Penalty:</span>
                                    <span class="text-error">-{{ $transaction->violation_penalty }} points</span>
                                </div>
                                <div>
                                    <span class="font-semibold">Date:</span>
                                    <span>{{ $transaction->date_occurred->format('M d, Y') }}</span>
                                </div>
                                @if($transaction->remarks)
                                    <div>
                                        <span class="font-semibold">Remarks:</span>
                                        <p class="text-xs mt-1">{{ $transaction->remarks }}</p>
                                    </div>
                                @endif
                            </div>

                            <div class="card-actions justify-end mt-4">
                                <x-mary-button wire:click="undoTransaction({{ $transaction->id }})"
                                               wire:confirm="Are you sure you want to undo this violation? The penalty will be restored to the user's credit score."
                                               class="btn-error btn-sm" icon="o-arrow-uturn-left" spinner>
                                    Undo
                                </x-mary-button>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="mt-6">
                    {{ $this->violationTransactions->links() }}
                </div>
            </div>

            {{-- Desktop Table View --}}
            <div class="hidden lg:block overflow-x-auto">
                <x-mary-table :headers="$transactionHeaders" :rows="$this->violationTransactions"
                              :sort-by="$sortBy" with-pagination link="transactionsPage"
                              :per-page="$perPageTransaction" :per-page-values="[10, 20, 50]" striped
                              row-class="hover:bg-base-200" header-class="text-base-content bg-base-200">
                    @scope('cell_user.name', $transaction)
                    <div>
                        <div
                            class="font-medium">{{ $transaction->user->first_name }} {{ $transaction->user->last_name }}</div>
                    </div>
                    @endscope

                    @scope('cell_violation.name', $transaction)
                    <div>
                        <div class="font-medium">{{ $transaction->violation->name }}</div>
                        @if($transaction->remarks)
                            <div class="text-xs text-base-content/70 line-clamp-2">{{ $transaction->remarks }}</div>
                        @endif
                    </div>
                    @endscope

                    @scope('cell_violation_penalty', $transaction)
                    <span class="text-error font-semibold">-{{ $transaction->violation_penalty }}</span>
                    @endscope

                    @scope('cell_severity', $transaction)
                    <x-mary-badge :value="$transaction->severity" class="badge-sm"
                                  :class="[
                                    'Minor' => 'badge-info',
                                    'Major' => 'badge-warning',
                                    'Critical' => 'badge-error badge-outline'
                                ][$transaction->severity] ?? 'badge-ghost'"/>
                    @endscope

                    @scope('cell_date_occurred', $transaction)
                    <div class="text-sm">
                        {{ $transaction->date_occurred->format('M d, Y') }}
                        <div
                            class="text-xs text-base-content/70">{{ $transaction->date_occurred->format('h:i A') }}</div>
                    </div>
                    @endscope

                    @scope('actions', $transaction)
                    <x-mary-button wire:click="undoTransaction({{ $transaction->id }})"
                                   wire:confirm="Are you sure you want to undo this violation? The penalty will be restored to the user's credit score."
                                   class="btn-error btn-sm" icon="o-arrow-uturn-left" spinner
                                   tooltip-left="Undo Violation"/>
                    @endscope
                </x-mary-table>
            </div>

            @if ($this->violationTransactions->isEmpty())
                <div class="text-center py-12">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-base-content/30"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-base-content/50 mt-4">No violation records found</p>
                </div>
            @endif
        </div>
    </x-mary-tab>
</div>
