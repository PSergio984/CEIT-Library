<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-base-content leading-tight">
            {{ __('Rules and Regulations') }}
        </h2>
    </x-slot>

    <x-mary-header title="Rules and Regulations Manager" subtitle="Manage library rules and regulations" separator />

    <div class="mt-3 mb-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-mary-select
                :options="$headers_list"
                option-label="title"
                option-value="id"
                placeholder="All headers"
                wire:model.live.debounce.100ms="filterHeaderId"
                clearable
                class="w-full sm:w-64"
            />
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search rules..."
                class="input input-bordered w-full sm:w-80"
            />
            <button @click="$wire.openCreateDrawer()" class="btn btn-warning w-full sm:w-auto">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add New Rule
            </button>
        </div>
    </div>

    @if ($this->rules()->isNotEmpty())
        <!-- Mobile Card View -->
        <div class="block lg:hidden space-y-4 mb-6">
            @foreach ($this->rules() as $rule)
                <div class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow" wire:key="rule-card-{{ $rule->id }}">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            @if($rule->ruleHeader)
                                <span class="badge badge-ghost mb-2">{{ $rule->ruleHeader->title }}</span>
                            @else
                                <span class="badge badge-error mb-2">No Header</span>
                            @endif
                        </div>
                    </div>

                    <div class="mb-3">
                        @if($rule->content === null)
                            <div class="italic text-sm text-base-content/70">No content</div>
                        @else
                            <p class="text-sm text-base-content/80 line-clamp-3">
                                {{ $rule->content }}
                            </p>
                        @endif
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-base-300">
                        <span class="text-xs text-base-content/50">
                            {{ $rule->updated_at ? $rule->updated_at->format('M d, Y H:i A') : '—' }}
                        </span>
                        <div class="flex gap-2">
                            <button @click="$wire.openEditDrawer({{ $rule->id }})" 
                                class="btn btn-sm btn-ghost"
                                wire:loading.attr="disabled"
                                wire:target="openEditDrawer({{ $rule->id }})">
                                <span wire:loading.remove wire:target="openEditDrawer({{ $rule->id }})">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                </span>
                                <span wire:loading wire:target="openEditDrawer({{ $rule->id }})" class="loading loading-spinner loading-xs"></span>
                            </button>
                            <button @click="$wire.confirmDelete({{ $rule->id }})" 
                                class="btn btn-sm btn-ghost text-error"
                                wire:loading.attr="disabled"
                                wire:target="confirmDelete({{ $rule->id }})">
                                <span wire:loading.remove wire:target="confirmDelete({{ $rule->id }})">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </span>
                                <span wire:loading wire:target="confirmDelete({{ $rule->id }})" class="loading loading-spinner loading-xs"></span>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Desktop Table View -->
        <div class="hidden lg:block">
            <x-mary-table
                :headers="$headers"
                :rows="$this->rules()"
                :sort-by="$sortBy"
                with-pagination
            >
                @scope('cell_ruleHeader.title', $rule)
                @if($rule->ruleHeader)
                    <x-mary-badge value="{{ $rule->ruleHeader->title }}" class="badge-ghost"/>
                @else
                    <x-mary-badge value="No Header" class="badge-error"/>
                @endif
                @endscope

                @scope('cell_content', $rule)
                @if($rule->content === null)
                    <div class="italic text-sm text-base-content/70">No content</div>
                @else
                    <div class="max-w-md truncate">
                        {{ $rule->content }}
                    </div>
                @endif
                @endscope

                @scope('cell_updated_at', $rule)
                {{ $rule->updated_at ? $rule->updated_at->format('M d, Y H:i A') : '—' }}
                @endscope

                @scope('actions', $rule)
                <div class="flex gap-1">
                    <button @click="$wire.openEditDrawer({{ $rule->id }})" 
                        class="btn btn-sm btn-ghost tooltip"
                        data-tip="Edit Rule"
                        wire:loading.attr="disabled"
                        wire:target="openEditDrawer({{ $rule->id }})">
                        <span wire:loading.remove wire:target="openEditDrawer({{ $rule->id }})">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="openEditDrawer({{ $rule->id }})" class="loading loading-spinner loading-xs"></span>
                    </button>
                    <button @click="$wire.confirmDelete({{ $rule->id }})" 
                        class="btn btn-sm btn-ghost text-error tooltip"
                        data-tip="Delete Rule"
                        wire:loading.attr="disabled"
                        wire:target="confirmDelete({{ $rule->id }})">
                        <span wire:loading.remove wire:target="confirmDelete({{ $rule->id }})">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </span>
                        <span wire:loading wire:target="confirmDelete({{ $rule->id }})" class="loading loading-spinner loading-xs"></span>
                    </button>
                </div>
                @endscope
            </x-mary-table>
        </div>

    @else
        <div class="text-center py-12">
            <h3 class="text-lg font-medium mb-2">No Rules & Regulations found</h3>
            <p class="text-base-content/70 mb-4">Try adjusting your search criteria or filters.</p>
        </div>
    @endif

    <x-mary-drawer wire:model="openDrawer" class="w-11/12 lg:w-1/3" right>
        <div class="px-2 py-3">
            <h3 class="text-lg font-semibold mb-4">
                {{ $isEdit ? 'Edit Rule' : 'Create Rule' }}
            </h3>

            <x-mary-form wire:submit.prevent="save" class="space-y-4">
                <x-mary-select label="Header" :options="$headers_list" option-label="title" option-value="id"
                    placeholder="Select a header" wire:model="form.rule_header_id" required />

                <x-mary-textarea label="Content" rows="6" wire:model.blur="form.content"
                    placeholder="Enter rule content" required />

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="$wire.openDrawer = false" class="btn">Cancel</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">{{ $isEdit ? 'Update' : 'Create' }}</span>
                        <span wire:loading wire:target="save" class="loading loading-spinner loading-xs"></span>
                    </button>
                </div>
            </x-mary-form>
        </div>
    </x-mary-drawer>

    <x-mary-modal wire:model="confirmDeleteModal" position="center" centered>
        <div class="p-4">
            <h3 class="text-lg font-semibold mb-2">Delete rule</h3>
            <p class="text-sm text-base-content/70">
                Are you sure you want to delete this rule? This action cannot be undone.
            </p>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" @click="$wire.confirmDeleteModal = false" class="btn">Cancel</button>
                <button type="button" @click="$wire.deleteConfirmed" class="btn btn-error" wire:loading.attr="disabled" wire:target="deleteConfirmed">
                    <span wire:loading.remove wire:target="deleteConfirmed">Delete</span>
                    <span wire:loading wire:target="deleteConfirmed" class="loading loading-spinner loading-xs"></span>
                </button>
            </div>
        </div>
    </x-mary-modal>
</div>
