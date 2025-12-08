<div class="p-4 sm:p-6">
    <div class="mb-4 sm:mb-6">
        <x-mary-header title="Violation Management" subtitle="Manage violations and user violations" separator />
    </div>

    {{-- Tabs --}}
    <div role="tablist" class="tabs tabs-boxed mb-4 sm:mb-6 bg-base-200">
        <a role="tab" 
            class="tab {{ $selectedTab === 'violations-tab' ? 'tab-active' : '' }}" 
            wire:click="$set('selectedTab', 'violations-tab')">
            Violations
        </a>
        <a role="tab" 
            class="tab {{ $selectedTab === 'transactions-tab' ? 'tab-active' : '' }}" 
            wire:click="$set('selectedTab', 'transactions-tab')">
            Violation Transactions
        </a>
        <a role="tab" 
            class="tab {{ $selectedTab === 'active-users-tab' ? 'tab-active' : '' }}" 
            wire:click="$set('selectedTab', 'active-users-tab')">
            Active Users
        </a>
    </div>

    {{-- Tab Content --}}
    <div x-data="{ tab: @entangle('selectedTab') }">
        <div x-show="tab === 'violations-tab'">
            <livewire:pages.admin.violations-tab key="violations-tab" />
        </div>
        
        <div x-show="tab === 'transactions-tab'" x-cloak>
            <livewire:pages.admin.violation-transactions-tab key="transactions-tab" />
        </div>
        
        <div x-show="tab === 'active-users-tab'" x-cloak>
            <livewire:pages.admin.active-users-tab key="active-users-tab" />
        </div>
    </div>
</div>