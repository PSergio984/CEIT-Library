@props([
    'availableYears',
    'availablePaperTypes',
    'availableDepartments',
    'showSearchBar' => true,
])

{{-- Academic Paper Filters Component - Reusable for Admin and Student --}}
<div x-data="{
    availableYears: null,
    availablePaperTypes: null,
    availableDepartments: null,
    
    init() {
        // Synchronously assign props to Alpine state for predictable rendering
        this.availableYears = @js($availableYears->toArray());
        this.availablePaperTypes = @js($availablePaperTypes->toArray());
        this.availableDepartments = @js($availableDepartments->toArray());
    },
    
    get hasActiveFilters() {
        return !!($wire.statusFilter || $wire.paperTypeFilter || $wire.departmentFilter || $wire.yearFromFilter || $wire.yearToFilter);
    },
    get validYearsFrom() {
        const toYear = $wire.yearToFilter;
        if (!toYear) return this.availableYears;
        return this.availableYears.filter(year => year <= parseInt(toYear));
    },
    get validYearsTo() {
        const fromYear = $wire.yearFromFilter;
        if (!fromYear) return this.availableYears;
        return this.availableYears.filter(year => year >= parseInt(fromYear));
    }
}" class="mb-6">
    
    {{-- Search Bar --}}
    @if($showSearchBar)
    <div class="mb-4">
        <div class="form-control">
            <label class="input input-bordered flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="w-4 h-4 opacity-70">
                    <path fill-rule="evenodd" d="M9.965 11.026a5 5 0 1 1 1.06-1.06l2.755 2.754a.75.75 0 1 1-1.06 1.06l-2.755-2.754ZM10.5 7a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Z" clip-rule="evenodd" />
                </svg>
                <input 
                    type="text" 
                    class="grow bg-transparent focus:outline-none"
                    placeholder="Search by title, author, catalog code..." 
                    aria-label="Search papers by title, author, or catalog code"
                    wire:model.live.debounce.300ms="search"
                    x-ref="searchInput"
                />
                <button
                    type="button"
                    x-show="$wire.search"
                    @click="$wire.set('search', ''); $refs.searchInput.focus();"
                    class="btn btn-xs btn-circle btn-ghost"
                    aria-label="Clear search"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </label>
        </div>
    </div>
    @endif
    
    {{-- Filters Section - Always Visible --}}
    <div class="bg-base-200 rounded-lg p-4">
        {{-- Header with Clear Button --}}
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-base-content flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
                </svg>
                Filters
            </h3>
            
            <x-mary-button 
                x-show="hasActiveFilters"
                wire:click="clearFilters"
                x-cloak
                class="btn-ghost btn-sm gap-2"
                icon="o-x-mark"
                label="Clear"
                spinner
            />
        </div>

        {{-- Filter Controls --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            {{-- Status Filter --}}
            <select wire:model.live="statusFilter" class="select select-bordered select-sm sm:select-md w-full">
                <option value="">All Status</option>
                <option value="Available">Available</option>
                <option value="Unavailable">Unavailable</option>
            </select>
            
            {{-- Paper Type Filter --}}
            <select wire:model.live="paperTypeFilter" class="select select-bordered select-sm sm:select-md w-full">
                <option value="">All Types</option>
                <template x-for="type in availablePaperTypes" :key="type">
                    <option :value="type" x-text="type"></option>
                </template>
            </select>
            
            {{-- Department Filter --}}
            <select wire:model.live="departmentFilter" class="select select-bordered select-sm sm:select-md w-full">
                <option value="">All Departments</option>
                <template x-for="dept in availableDepartments" :key="dept">
                    <option :value="dept" x-text="dept"></option>
                </template>
            </select>
            
            {{-- Year From Filter --}}
            <select wire:model.live="yearFromFilter" class="select select-bordered select-sm sm:select-md w-full">
                <option value="" disabled selected>Year From</option>
                <template x-for="year in validYearsFrom" :key="year">
                    <option :value="year" x-text="year"></option>
                </template>
            </select>
            
            {{-- Year To Filter --}}
            <select wire:model.live="yearToFilter" class="select select-bordered select-sm sm:select-md w-full">
                <option value="" disabled selected>Year To</option>
                <template x-for="year in validYearsTo" :key="year">
                    <option :value="year" x-text="year"></option>
                </template>
            </select>
        </div>
        
        {{-- Active Filters Display --}}
        <div x-show="hasActiveFilters" x-cloak class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-base-300">
            <span class="text-xs font-medium text-base-content/70">Active Filters:</span>
            <span x-show="$wire.statusFilter" x-cloak class="badge badge-sm gap-1">
                <span>Status:</span>
                <span x-text="$wire.statusFilter"></span>
            </span>
            <span x-show="$wire.paperTypeFilter" x-cloak class="badge badge-sm gap-1">
                <span>Type:</span>
                <span x-text="$wire.paperTypeFilter"></span>
            </span>
            <span x-show="$wire.departmentFilter" x-cloak class="badge badge-sm gap-1">
                <span>Dept:</span>
                <span x-text="$wire.departmentFilter"></span>
            </span>
            <span x-show="$wire.yearFromFilter" x-cloak class="badge badge-sm gap-1">
                <span>From:</span>
                <span x-text="$wire.yearFromFilter"></span>
            </span>
            <span x-show="$wire.yearToFilter" x-cloak class="badge badge-sm gap-1">
                <span>To:</span>
                <span x-text="$wire.yearToFilter"></span>
            </span>
        </div>
    </div>
</div>
