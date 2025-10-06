<div>
    <x-mary-header title="Create Rule Regulation" with-anchor separator />

    <x-mary-form wire:submit="save">
        <x-mary-select
            label="Header"
            :options="$headers"
            option-value="id"
            option-label="title"
            wire:model="form.rule_header_id"
            placeholder="Select a header"
        />
        <x-mary-input label="Content" wire:model="form.content" />

        <x-slot:actions>
            <x-mary-button label="Cancel" link="{{ route('admin.rule-and-regulation.index') }}" />
            <x-mary-button label="Create" type="submit" icon="o-paper-airplane" class="btn-primary" />
        </x-slot:actions>
    </x-mary-form>
</div>
