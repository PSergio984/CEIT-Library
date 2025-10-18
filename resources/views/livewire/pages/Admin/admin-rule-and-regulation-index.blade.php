<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-base-content leading-tight">
            {{ __('Rules and Regulations') }}
        </h2>
    </x-slot>

    <x-mary-header title="Rules and Regulations Manager" subtitle="Manage library rules and regulations" separator/>

    <div class="mt-3 mb-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-mary-select
                label="Header"
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
                wire:model.live.debounce.100ms="search"
                placeholder="Search rules..."
                class="input input-bordered w-full sm:w-80"
            />
            <x-mary-button label="Add New Rule" class="btn-warning" wire:click="openCreateDrawer" tooltip="Add a New Rule" />
        </div>
    </div>

    <x-mary-table
        :headers="$headers"
        :rows="$this->rules()"
        :sort-by="$sortBy"
        with-pagination
        :per-page="$perPage"
        :per-page-values="[5, 10, 20]"
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


        @scope('actions', $rule)
        <div class="flex gap-2">
            <x-mary-button icon="o-pencil" wire:click="openEditDrawer({{ $rule->id }})" spinner
                           class="btn-sm btn-ghost"  tooltip-left="Edit a Rule"/>
            <x-mary-button icon="o-trash" wire:click="confirmDelete({{ $rule->id }})" spinner
                           class="btn-sm btn-ghost text-error" tooltip-left="Delete a Rule"/>
        </div>
        @endscope
    </x-mary-table>

    <x-mary-drawer wire:model="openDrawer" class="w-11/12 lg:w-1/3" right>
        <div class="px-2 py-3">
            <h3 class="text-lg font-semibold mb-4">
                {{ $isEdit ? 'Edit Rule' : 'Create Rule' }}
            </h3>

            <x-mary-form wire:submit.prevent="save" class="space-y-4">
                <x-mary-select
                    label="Header"
                    :options="$headers_list"
                    option-label="title"
                    option-value="id"
                    placeholder="Select a header"
                    wire:model="form.rule_header_id"
                    required
                />

                <x-mary-textarea
                    label="Content"
                    rows="6"
                    wire:model.blur="form.content"
                    placeholder="Enter rule content"
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

    <x-mary-modal
        wire:model="confirmDeleteModal"
        position="center"
        centered
    >
        <div class="p-4">
            <h3 class="text-lg font-semibold mb-2">Delete rule</h3>
            <p class="text-sm text-base-content/70">
                Are you sure you want to delete this rule? This action cannot be undone.
            </p>
            <div class="flex justify-end gap-2 mt-4">
                <x-mary-button type="button" label="Cancel" @click="$wire.confirmDeleteModal = false"/>
                <x-mary-button type="button" class="btn-error" label="Delete" wire:click="deleteConfirmed" spinner/>
            </div>
        </div>
    </x-mary-modal>
</div>
