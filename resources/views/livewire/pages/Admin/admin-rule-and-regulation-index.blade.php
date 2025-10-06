<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-base-content leading-tight">
            {{ __('Rules and Regulations') }}
        </h2>
    </x-slot>

    <x-mary-header title="Rules and Regulations Manager" subtitle="Manage library rules and regulations" separator>
        <x-slot:actions>
            <x-mary-button label="New Rule" icon="o-plus" class="btn-primary" link="{{ route('admin.rule-and-regulation.create') }}" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-table :headers="$headers" :rows="$this->rules()" :sort-by="$sortBy" with-pagination
                  per-page="perPage" :per-page-values="[5, 10, 20]">

        @scope('cell_ruleHeader.title', $rule)
        @if($rule->ruleHeader)
            <x-mary-badge value="{{ $rule->ruleHeader->title }}" class="badge-ghost" />
        @else
            <x-mary-badge value="No Header" class="badge-error" />
        @endif
        @endscope

        @scope('cell_content', $rule)
        <div class="max-w-md truncate">
            {{ $rule->content }}
        </div>
        @endscope

        @scope('actions', $rule)
        <div class="flex gap-2">
            <x-mary-button icon="o-pencil" wire:click="edit({{ $rule->id }})" spinner class="btn-sm btn-ghost" />
            <x-mary-button icon="o-trash" wire:click="delete({{ $rule->id }})"
                           wire:confirm="Are you sure you want to delete this rule?"
                           spinner class="btn-sm btn-ghost text-error" />
        </div>
        @endscope
    </x-mary-table>

    <!-- Edit Modal -->
    <x-mary-modal wire:model="showEditModal" title="Edit Rule" separator>
        <x-mary-form wire:submit="update">
            <x-mary-select
                label="Header"
                :options="$headers_list"
                option-value="id"
                option-label="title"
                wire:model="form.rule_header_id"
            />
            <x-mary-textarea label="Content" wire:model="form.content" rows="4" />

            <x-slot:actions>
                <x-mary-button label="Cancel" @click="$wire.showEditModal = false" />
                <x-mary-button label="Update" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</div>
