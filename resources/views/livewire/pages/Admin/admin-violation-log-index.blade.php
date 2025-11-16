@if(isset($placeholder) && $placeholder)
    @include('components.loading-placeholder')
@else
<div class="min-h-screen bg-base-200/30 p-4 md:p-6">
    <div class="max-w-7xl mx-auto">
        <x-mary-header title="Violation Management" subtitle="Manage violations and user violations" separator class="mb-6" />

        <div class="bg-base-100 rounded-box shadow-lg p-6">
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
    </div>
</div>
@endif
