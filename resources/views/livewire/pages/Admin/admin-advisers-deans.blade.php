@php
    // Livewire Lazy Loading: If this is being rendered as a placeholder, show loading skeleton
    if (isset($placeholder) && $placeholder) {
        echo view('components.loading-placeholder');
        return;
    }
@endphp

<div class="p-6">
    <x-mary-header title="Manage Advisers, Deans & Authors" 
        subtitle="Manage research advisers, technical advisers, deans, and authors for academic papers" 
        separator />

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="stats shadow bg-base-200">
            <div class="stat">
                <div class="stat-figure text-primary">
                    <x-mary-icon name="o-user-group" class="w-8 h-8" />
                </div>
                <div class="stat-title">Total Entries</div>
                <div class="stat-value text-primary" wire:loading.remove>{{ $this->totalCount }}</div>
                <div class="stat-value text-primary" wire:loading>
                    <span class="loading loading-spinner loading-sm"></span>
                </div>
                <div class="stat-desc">In {{ ucfirst($activeTab) }}</div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div role="tablist" class="tabs tabs-boxed mb-6 bg-base-200">
        <a role="tab" 
            class="tab {{ $activeTab === 'research' ? 'tab-active' : '' }}" 
            wire:click="$set('activeTab', 'research')">
            Research Advisers
        </a>
        <a role="tab" 
            class="tab {{ $activeTab === 'technical' ? 'tab-active' : '' }}" 
            wire:click="$set('activeTab', 'technical')">
            Technical Advisers
        </a>
        <a role="tab" 
            class="tab {{ $activeTab === 'deans' ? 'tab-active' : '' }}" 
            wire:click="$set('activeTab', 'deans')">
            Deans
        </a>
        <a role="tab" 
            class="tab {{ $activeTab === 'authors' ? 'tab-active' : '' }}" 
            wire:click="$set('activeTab', 'authors')">
            Authors
        </a>
    </div>

    {{-- Search and Create --}}
    <div class="flex flex-col sm:flex-row gap-4 mb-6">
        <div class="flex-1">
            <x-mary-input 
                label="Search" 
                wire:model.live.debounce.300ms="search"
                placeholder="Search by name..." 
                icon="o-magnifying-glass" />
        </div>
        <div class="flex items-end">
            <x-mary-button 
                wire:click="openCreateModal" 
                class="btn-primary w-full sm:w-auto" 
                icon="o-plus">
                Add {{ ucfirst($activeTab === 'deans' ? 'Dean' : ($activeTab === 'authors' ? 'Author' : 'Adviser')) }}
            </x-mary-button>
        </div>
    </div>

    <div class="mb-4 text-xs sm:text-sm text-base-content/70">
        Showing {{ $this->entries->count() }} of {{ $this->entries->total() }} results
    </div>

    {{-- Mobile Card View --}}
    <div class="block lg:hidden space-y-4" wire:loading.class="opacity-50">
        @foreach ($this->entries as $entry)
            <div wire:key="mobile-entry-{{ $entry->id }}"
                class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <h3 class="font-semibold text-base">{{ $entry->name }}</h3>
                        <p class="text-sm text-base-content/70 mt-1">
                            {{ $entry->papers_count }} papers
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <x-mary-button 
                        wire:click="openEditModal({{ $entry->id }})" 
                        class="btn-sm btn-ghost flex-1"
                        icon="o-pencil"
                        wire:loading.attr="disabled"
                        wire:target="openEditModal({{ $entry->id }})">
                        <span wire:loading.remove wire:target="openEditModal({{ $entry->id }})">Edit</span>
                        <span wire:loading wire:target="openEditModal({{ $entry->id }})" class="loading loading-spinner loading-xs"></span>
                    </x-mary-button>
                    <x-mary-button 
                        wire:click="confirmDelete({{ $entry->id }})" 
                        class="btn-sm btn-ghost text-error flex-1"
                        icon="o-trash"
                        wire:loading.attr="disabled"
                        wire:target="confirmDelete({{ $entry->id }})">
                        <span wire:loading.remove wire:target="confirmDelete({{ $entry->id }})">Delete</span>
                        <span wire:loading wire:target="confirmDelete({{ $entry->id }})" class="loading loading-spinner loading-xs"></span>
                    </x-mary-button>
                </div>
            </div>
        @endforeach

        @if ($this->entries->isEmpty())
            <div class="text-center py-12">
                <x-mary-icon name="o-user-group" class="w-16 h-16 mx-auto text-base-content/30 mb-4" />
                <h3 class="text-lg font-medium mb-2">No entries found</h3>
                <p class="text-base-content/70 mb-4">
                    @if($search)
                        No entries match your search criteria.
                    @else
                        Add your first {{ $activeTab === 'deans' ? 'dean' : ($activeTab === 'authors' ? 'author' : 'adviser') }} to get started.
                    @endif
                </p>
                @if($search)
                    <x-mary-button wire:click="$set('search', '')" class="btn-outline">
                        Clear Search
                    </x-mary-button>
                @endif
            </div>
        @endif

        <div class="mt-6">
            {{ $this->entries->links() }}
        </div>
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden lg:block overflow-x-auto" wire:loading.class="opacity-50">
        <x-mary-table 
            :headers="$headers" 
            :rows="$this->entries" 
            :sort-by="$sortBy" 
            with-pagination
            striped
            row-class="hover:bg-base-200"
            header-class="text-base-content bg-base-200">

            @scope('cell_name', $row)
                <div class="font-medium">{{ $row->name }}</div>
            @endscope

            @scope('cell_papers_count', $row)
                <span class="badge badge-neutral">{{ $row->papers_count }}</span>
            @endscope

            @scope('cell_created_at', $row)
                <span class="text-sm">{{ \Carbon\Carbon::parse($row->created_at)->format('M d, Y') }}</span>
            @endscope

            @scope('cell_actions', $row)
                <div class="flex gap-1">
                    <x-mary-button 
                        wire:click="openEditModal({{ $row->id }})" 
                        class="btn-sm btn-ghost"
                        icon="o-pencil" 
                        tooltip="Edit"
                        wire:loading.attr="disabled"
                        wire:target="openEditModal({{ $row->id }})" />
                    <x-mary-button 
                        wire:click="confirmDelete({{ $row->id }})" 
                        class="btn-sm btn-ghost text-error"
                        icon="o-trash" 
                        tooltip="Delete"
                        wire:loading.attr="disabled"
                        wire:target="confirmDelete({{ $row->id }})" />
                </div>
            @endscope
        </x-mary-table>
    </div>

    @if ($this->entries->isEmpty() && !$search)
        <div class="text-center py-12">
            <x-mary-icon name="o-user-group" class="w-16 h-16 mx-auto text-base-content/30 mb-4" />
            <h3 class="text-lg font-medium mb-2">No entries found</h3>
            <p class="text-base-content/70">Add your first {{ $activeTab === 'deans' ? 'dean' : ($activeTab === 'authors' ? 'author' : 'adviser') }} to get started.</p>
        </div>
    @endif

    {{-- Create/Edit Modal --}}
    <x-mary-modal wire:model="showCreateModal" title="Add {{ ucfirst($activeTab === 'deans' ? 'Dean' : ($activeTab === 'authors' ? 'Author' : 'Adviser')) }}" class="backdrop-blur">
        <x-mary-input label="Name" wire:model="name" placeholder="Enter full name" />
        
        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="closeModals" />
            <x-mary-button label="Save" wire:click="save" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-mary-modal>

    <x-mary-modal wire:model="showEditModal" title="Edit {{ ucfirst($activeTab === 'deans' ? 'Dean' : ($activeTab === 'authors' ? 'Author' : 'Adviser')) }}" class="backdrop-blur">
        <x-mary-input label="Name" wire:model="name" placeholder="Enter full name" />
        
        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="closeModals" />
            <x-mary-button label="Update" wire:click="save" class="btn-primary" spinner="save" />
        </x-slot:actions>
    </x-mary-modal>

    <x-mary-modal wire:model="showDeleteModal" title="Delete Entry?" class="backdrop-blur">
        <div class="space-y-4">
            <div class="alert alert-warning">
                <x-mary-icon name="o-exclamation-triangle" class="w-6 h-6" />
                <span>This action cannot be undone!</span>
            </div>
            <p class="text-base-content/80">
                Are you sure you want to delete this entry? This will fail if the entry is being used by academic papers.
            </p>
        </div>
        
        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="closeModals" />
            <x-mary-button label="Delete" wire:click="delete" class="btn-error" icon="o-trash" spinner="delete" />
        </x-slot:actions>
    </x-mary-modal>
</div>
