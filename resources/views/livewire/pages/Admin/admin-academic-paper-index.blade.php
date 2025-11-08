{{-- Academic Paper Directory - Livewire + Alpine.js + DaisyUI --}}
<x-slot name="header">
    <h2 class="font-semibold text-xl text-base-content leading-tight">
        {{ __('Academic Paper Directory') }}
    </h2>
</x-slot>

<div>
    <div class="p-6">

    {{-- Header Actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <button 
            x-data="{ loading: false }"
            @click="
                loading = true;
                $wire.create().finally(() => loading = false)
            "
            :disabled="loading"
            class="btn btn-primary gap-2"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5" x-show="!loading">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            <span x-show="!loading">Create Academic Paper</span>
            <span x-show="loading" class="loading loading-spinner loading-sm"></span>
            <span x-show="loading">Loading...</span>
        </button>
    </div>

    {{-- Search and Filters with Alpine.js --}}
    <div x-data="{ 
        showFilters: true,
        get hasActiveFilters() {
            return !!($wire.statusFilter || $wire.paperTypeFilter || $wire.departmentFilter || $wire.yearFromFilter || $wire.yearToFilter);
        }
    }" class="mb-6">
        
        {{-- Search Bar and Filter Toggle --}}
        <div class="flex flex-col sm:flex-row gap-2 sm:gap-4 mb-4">
            <div class="flex-1">
                <div class="form-control">
                    <label class="input input-bordered flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4 opacity-70">
                            <path fill-rule="evenodd" d="M9.965 11.026a5 5 0 1 1 1.06-1.06l2.755 2.754a.75.75 0 1 1-1.06 1.06l-2.755-2.754ZM10.5 7a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Z" clip-rule="evenodd" />
                        </svg>
                        <input 
                            type="text" 
                            class="grow" 
                            placeholder="Search by title, author, catalog code..." 
                            wire:model.live.debounce.300ms="search"
                        />
                    </label>
                </div>
            </div>
            
            <div class="flex gap-2 flex-shrink-0">
                <button 
                    @click="showFilters = !showFilters" 
                    class="btn btn-outline btn-sm sm:btn-md gap-2 whitespace-nowrap"
                    :class="{ 'btn-active': showFilters }"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                    </svg>
                    <span class="hidden sm:inline" x-text="showFilters ? 'Hide Filters' : 'Show Filters'"></span>
                    <span class="sm:hidden">Filters</span>
                    <span x-show="hasActiveFilters && !showFilters" class="badge badge-primary badge-sm">Active</span>
                </button>
                
                <button 
                    x-show="hasActiveFilters"
                    @click="$wire.set('statusFilter', ''); $wire.set('paperTypeFilter', ''); $wire.set('departmentFilter', ''); $wire.set('yearFromFilter', ''); $wire.set('yearToFilter', '')"
                    x-transition
                    class="btn btn-ghost btn-sm sm:btn-md gap-2 whitespace-nowrap"
                    title="Clear all filters"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span class="hidden sm:inline">Clear Filters</span>
                    <span class="sm:hidden">Clear</span>
                </button>
            </div>
        </div>
        
        {{-- Collapsible Filter Section --}}
        <div 
            x-show="showFilters" 
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 transform -translate-y-2"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform -translate-y-2"
            class="bg-base-200 rounded-lg p-4"
        >
            <div class="flex flex-wrap gap-2">
                <select wire:model.live="statusFilter" class="select select-bordered select-sm sm:select-md w-full sm:w-auto">
                    <option value="">All Status</option>
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
                
                <select wire:model.live="paperTypeFilter" class="select select-bordered select-sm sm:select-md w-full sm:w-auto">
                    <option value="">All Types</option>
                    @foreach($this->availablePaperTypes as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
                
                <select wire:model.live="departmentFilter" class="select select-bordered select-sm sm:select-md w-full sm:w-auto">
                    <option value="">All Departments</option>
                    @foreach($this->availableDepartments as $dept)
                        <option value="{{ $dept }}">{{ $dept }}</option>
                    @endforeach
                </select>
                
                <select wire:model.live="yearFromFilter" class="select select-bordered select-sm sm:select-md w-full sm:w-auto">
                    <option value="">Year From</option>
                    @foreach($this->availableYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
                
                <select wire:model.live="yearToFilter" class="select select-bordered select-sm sm:select-md w-full sm:w-auto">
                    <option value="">Year To</option>
                    @foreach($this->availableYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            
            {{-- Active Filters Display --}}
            <div x-show="hasActiveFilters" class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-base-300">
                <span class="text-xs font-medium text-base-content/70">Active Filters:</span>
                <span x-show="$wire.statusFilter" class="badge badge-sm gap-1">
                    Status: <span x-text="$wire.statusFilter"></span>
                </span>
                <span x-show="$wire.paperTypeFilter" class="badge badge-sm gap-1">
                    Type: <span x-text="$wire.paperTypeFilter"></span>
                </span>
                <span x-show="$wire.departmentFilter" class="badge badge-sm gap-1">
                    Dept: <span x-text="$wire.departmentFilter"></span>
                </span>
                <span x-show="$wire.yearFromFilter" class="badge badge-sm gap-1">
                    From: <span x-text="$wire.yearFromFilter"></span>
                </span>
                <span x-show="$wire.yearToFilter" class="badge badge-sm gap-1">
                    To: <span x-text="$wire.yearToFilter"></span>
                </span>
            </div>
        </div>
    </div>

    <div class="mb-4 text-xs sm:text-sm text-base-content/70">
        Showing {{ $this->academicPapers->count() }} of {{ $this->academicPapers->total() }} results
    </div>

    {{-- Mobile/Tablet Card View (for screens smaller than 1280px) --}}
    <div class="block xl:hidden space-y-4">
        @forelse ($this->academicPapers as $paper)
            <div wire:key="mobile-paper-{{ $paper->id }}" class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="badge badge-sm {{ $paper->status === 'Available' ? 'badge-success' : 'badge-error' }}">
                                {{ $paper->status }}
                            </span>
                            <span class="badge badge-sm badge-outline">{{ $paper->catalog_code }}</span>
                        </div>
                        <h3 class="font-semibold text-sm sm:text-base line-clamp-2 break-words">{{ $paper->title }}</h3>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs sm:text-sm mt-3">
                    <div>
                        <p class="text-base-content/50 font-medium mb-1">Department</p>
                        <p class="font-medium break-words">{{ $paper->department }}</p>
                    </div>
                    <div>
                        <p class="text-base-content/50 font-medium mb-1">Year</p>
                        <p class="font-medium">{{ $paper->publication_year }}</p>
                    </div>
                    <div>
                        <p class="text-base-content/50 font-medium mb-1">Type</p>
                        <p class="font-medium break-words">{{ $paper->paper_type }}</p>
                    </div>
                    <div>
                        <p class="text-base-content/50 font-medium mb-1">Copies</p>
                        <p class="font-medium">{{ $paper->available_copies }} available</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 mt-4 pt-3 border-t border-base-300">
                    <button 
                        x-data="{ loading: false }"
                        @click="
                            loading = true;
                            $wire.showPaperDetails({{ $paper->id }}).finally(() => loading = false)
                        "
                        :disabled="loading"
                        class="btn btn-xs sm:btn-sm btn-ghost flex-1 min-w-[80px]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" x-show="!loading">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span x-show="!loading">View</span>
                        <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                    </button>
                    <button 
                        x-data="{ loading: false }"
                        @click="
                            loading = true;
                            $wire.edit({{ $paper->id }}).finally(() => loading = false)
                        "
                        :disabled="loading"
                        class="btn btn-xs sm:btn-sm btn-ghost flex-1 min-w-[80px]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" x-show="!loading">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                        <span x-show="!loading">Edit</span>
                        <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                    </button>
                    <button 
                        x-data="{ loading: false }"
                        @click="
                            loading = true;
                            $wire.confirmDelete({{ $paper->id }}).finally(() => loading = false)
                        "
                        :disabled="loading"
                        class="btn btn-xs sm:btn-sm btn-ghost text-error flex-1 min-w-[80px]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" x-show="!loading">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                        <span x-show="!loading">Delete</span>
                        <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                    </button>
                </div>
            </div>
        @empty
            <div class="text-center py-8 sm:py-12 bg-base-100 rounded-lg border border-base-300">
                <x-mary-icon name="o-document-magnifying-glass" class="w-12 h-12 sm:w-16 sm:h-16 mx-auto text-base-content/40 mb-4" />
                <h3 class="text-base sm:text-lg font-medium text-base-content mb-2 px-4">No Academic Papers Found</h3>
                <p class="text-xs sm:text-sm text-base-content/70 px-4">
                    @if($search || $statusFilter || $departmentFilter || $paperTypeFilter || $yearFromFilter || $yearToFilter)
                        No papers match your current filters
                    @else
                        No academic papers are available at the moment
                    @endif
                </p>
            </div>
        @endforelse

        {{-- Mobile/Tablet Pagination --}}
        @if($this->academicPapers->hasPages())
            <div class="mt-6">
                {{ $this->academicPapers->links() }}
            </div>
        @endif
    </div>

    {{-- Desktop Table View (for screens 1280px and wider) --}}
    <div class="hidden xl:block">
        <div class="overflow-x-auto">
            <x-mary-table
            :headers="$headers"
            :rows="$this->academicPapers"
            with-pagination
            :sort-by="$sortBy"
            per-page="perPage"
            :per-page-values="[5, 10, 25, 50]"
            striped
            row-class="hover:bg-base-200"
            header-class="text-base-content bg-base-200"
        >
            <x-slot:empty>
                <div class="text-center py-12">
                    <x-mary-icon name="o-document-magnifying-glass" class="w-16 h-16 mx-auto text-base-content/40 mb-4" />
                    <h3 class="text-lg font-medium text-base-content mb-2">No Academic Papers Found</h3>
                    <p class="text-sm text-base-content/70">
                        @if($search || $statusFilter || $departmentFilter || $paperTypeFilter || $yearFromFilter || $yearToFilter)
                            No papers match your current filters
                        @else
                            No academic papers are available at the moment
                        @endif
                    </p>
                </div>
            </x-slot:empty>

            @scope('cell_catalog_code', $row)
            <div class="font-mono text-sm">{{ $row->catalog_code }}</div>
            @endscope

            @scope('cell_title', $row)
            <div class="font-medium max-w-md">{{ $row->title }}</div>
            @endscope

            @scope('cell_status', $row)
            <span class="badge {{ $row->status === 'Available' ? 'badge-success' : 'badge-error' }}">
                {{ $row->status }}
            </span>
            @endscope

            @scope('actions', $row)
            <div class="flex items-center gap-2">
                <button 
                    x-data="{ loading: false }"
                    @click="
                        loading = true;
                        $wire.showPaperDetails({{ $row->id }}).finally(() => loading = false)
                    "
                    :disabled="loading"
                    class="btn btn-sm btn-ghost tooltip"
                    data-tip="View Details"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" x-show="!loading">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                </button>
                
                <button 
                    x-data="{ loading: false }"
                    @click="
                        loading = true;
                        $wire.edit({{ $row->id }}).finally(() => loading = false)
                    "
                    :disabled="loading"
                    class="btn btn-sm btn-ghost tooltip"
                    data-tip="Edit"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" x-show="!loading">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                    </svg>
                    <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                </button>
                
                <button 
                    x-data="{ loading: false }"
                    @click="
                        loading = true;
                        $wire.confirmDelete({{ $row->id }}).finally(() => loading = false)
                    "
                    :disabled="loading"
                    class="btn btn-sm btn-ghost text-error tooltip"
                    data-tip="Delete"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" x-show="!loading">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                    <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                </button>
            </div>
            @endscope
        </x-mary-table>
        </div>
    </div>

    </div>{{-- Close p-6 div --}}

    {{-- Alpine.js State Management for Modals --}}
    <div x-data="{
        showDeleteModal: false,
        showPaperModal: false,
        showCopyDeleteModal: false
    }" 
    @delete-modal.window="showDeleteModal = true"
    @paper-modal.window="showPaperModal = true"
    @copy-delete-modal.window="showCopyDeleteModal = true">
        
        {{-- Modals --}}
        <x-admin.delete-academic-paper-modal :deleteId="$deleteId" />
        <x-admin.paper-details-modal :selectedPaper="$this->selectedPaper" :isAdmin="true" />
        <x-admin.delete-copy-modal :copyToDelete="$copyToDelete" />
    </div>
    {{-- Create/Edit Academic Paper Drawer --}}
    <x-admin.academic-paper-form-drawer :formDrawer="$formDrawer" :isEditing="$isEditing" :form="$form" />
</div>{{-- Close root div --}}
