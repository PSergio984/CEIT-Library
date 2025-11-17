<div class="p-6">
    <x-mary-header title="Violation Management" subtitle="Manage violations and user violations" separator class="mb-6" />

    <x-mary-tabs wire:model="selectedTab">
        <x-mary-tab name="violations-tab" label="Violations" icon="o-shield-exclamation">
            <livewire:pages.admin.violations-tab key="violations-tab" />
        </x-mary-tab>

        <x-mary-tab name="transactions-tab" label="Violation Transactions" icon="o-document-text">
            <livewire:pages.admin.violation-transactions-tab key="transactions-tab" />
        </x-mary-tab>

        <x-mary-tab name="active-users-tab" label="Active Users" icon="o-users">
            <livewire:pages.admin.active-users-tab key="active-users-tab" />
        </x-mary-tab>
    </x-mary-tabs>
</div>