<div>
        <x-mary-tab name="violations-tab" label="Violations" icon="o-shield-exclamation">
            {{-- Violations CRUD Content --}}
            <div class="mt-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold">Violation Types</h3>
                    <x-mary-button wire:click="openCreateDrawer" class="btn-primary btn-sm" icon="o-plus" spinner
                                   tooltip-left="Create a Violation">
                        Add Violation
                    </x-mary-button>
                </div>

                <div class="bg-base-200 p-4 rounded-lg mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-mary-input label="Search" wire:model.live.debounce.200ms="search"
                                          placeholder="Search by name or description..." icon="o-magnifying-glass"/>
                        </div>
                        <div class="flex justify-end items-end">
                            <x-mary-button wire:click="clearFilters" class="btn-outline " icon="o-x-mark">
                                Clear Filters
                            </x-mary-button>
                        </div>
                    </div>
                </div>

                <div class="mb-4 text-xs sm:text-sm text-base-content/70">
                    Showing {{ $this->violations->count() }} of {{ $this->violations->total() }} results
                </div>

                {{-- Mobile Card View --}}
                <div class="block lg:hidden space-y-4">
                    @foreach ($this->violations as $violation)
                        <div
                            class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h3 class="font-semibold text-base">{{ $violation->name }}</h3>
                                    <p class="text-sm text-base-content/70 mt-2">{{ $violation->description }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 text-xs mt-3">
                                <div>
                                    <p class="text-base-content/50 font-medium">Penalty Score</p>
                                    <p class="font-bold text-lg text-error">{{ $violation->penalty_score }}</p>
                                </div>
                                <div>
                                    <p class="text-base-content/50 font-medium">Last Updated</p>
                                    <p class="font-medium">{{ $violation->updated_at->format('M d, Y') }}</p>
                                    <p class="text-base-content/50">{{ $violation->updated_at->format('H:i') }}</p>
                                </div>
                            </div>

                            <div class="flex gap-2 mt-4 pt-3 border-t border-base-300">
                                <x-mary-button icon="o-pencil" wire:click="openEditDrawer({{ $violation->id }})"
                                               class="btn-sm btn-ghost flex-1" label="Edit"/>
                                <x-mary-button icon="o-trash" wire:click="confirmDelete({{ $violation->id }})"
                                               class="btn-sm btn-ghost text-error flex-1" label="Delete"/>
                            </div>
                        </div>
                    @endforeach

                    <div class="mt-6">
                        {{ $this->violations->links() }}
                    </div>
                </div>

                {{-- Desktop Table View --}}
                <div class="hidden lg:block overflow-x-auto">
                    <x-mary-table :headers="$headers" :rows="$this->violations" :sort-by="$sortBy"
                                  with-pagination
                                  :per-page="$perPage" :per-page-values="[10, 20, 50]" striped
                                  row-class="hover:bg-base-200" header-class="text-base-content bg-base-200">

                        @scope('cell_name', $row)
                        <div class="font-medium">{{ $row->name }}</div>
                        @endscope

                        @scope('cell_description', $row)
                        <div class="text-sm text-base-content/70 max-w-md truncate">{{ $row->description }}</div>
                        @endscope

                        @scope('cell_penalty_score', $row)
                        <div class="font-bold text-error">-{{ $row->penalty_score }}</div>
                        @endscope

                        @scope('cell_updated_at', $row)
                        <div class="text-sm">
                            <div>{{ $row->updated_at->format('M d, Y') }}</div>
                            <div class="text-xs text-base-content/50">{{ $row->updated_at->format('H:i') }}</div>
                        </div>
                        @endscope

                        @scope('actions', $row)
                        <div class="flex gap-2">
                            <x-mary-button icon="o-pencil" wire:click="openEditDrawer({{ $row->id }})" spinner
                                           class="btn-sm btn-ghost" tooltip-left="Edit"/>
                            <x-mary-button icon="o-trash" wire:click="confirmDelete({{ $row->id }})" spinner
                                           class="btn-sm btn-ghost text-error" tooltip-left="Delete"/>
                        </div>
                        @endscope
                    </x-mary-table>
                </div>

                @if ($this->violations->isEmpty())
                    <div class="text-center py-12">
                        <h3 class="text-lg font-medium mb-2">No violations found</h3>
                        <p class="text-base-content/70 mb-4">Try adjusting your search criteria or add a new
                            violation.</p>
                        <x-mary-button wire:click="clearFilters" class="btn-outline">
                            Clear All Filters
                        </x-mary-button>
                    </div>
                @endif
            </div>
        </x-mary-tab>

        {{-- Create/Edit Violation Drawer For Violation Data --}}
        <x-mary-drawer wire:model="openDrawer" class="w-11/12 lg:w-1/3" right>
            <div class="px-2 py-3">
                <h3 class="text-lg font-semibold mb-4">
                    {{ $isEdit ? 'Edit Violation' : 'Create Violation' }}
                </h3>

                <x-mary-form wire:submit.prevent="save" class="space-y-4">
                    <x-mary-input
                        label="Name"
                        wire:model="form.name"
                        placeholder="Enter violation name"
                        required
                    />

                    <x-mary-textarea
                        label="Description"
                        rows="6"
                        wire:model="form.description"
                        placeholder="Enter violation description"
                        required
                    />

                    <x-mary-input
                        label="Penalty Score"
                        type="number"
                        wire:model="form.penalty_score"
                        placeholder="Enter penalty score"
                        min="0"
                        required
                    />

                    <div class="flex justify-end gap-2 pt-2">
                        <x-mary-button type="button" label="Cancel" @click="$wire.openDrawer = false"/>
                        <x-mary-button type="submit" class="btn-primary" label="{{ $isEdit ? 'Update' : 'Create' }}"
                                       spinner/>
                    </div>
                </x-mary-form>
            </div>
        </x-mary-drawer>

        {{-- Delete Confirmation Modal --}}
        <x-mary-modal wire:model="confirmDeleteModal" position="center" centered>
            <div class="p-4">
                <h3 class="text-lg font-semibold mb-2">Delete Violation</h3>
                <p class="text-sm text-base-content/70">
                    Are you sure you want to delete this violation? This action cannot be undone.
                </p>
                <div class="flex justify-end gap-2 mt-4">
                    <x-mary-button type="button" label="Cancel" @click="$wire.confirmDeleteModal = false"/>
                    <x-mary-button type="button" class="btn-error" label="Delete" wire:click="deleteConfirmed" spinner/>
                </div>
            </div>
        </x-mary-modal>
</div>
