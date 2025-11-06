{{-- Alpine state for client-side UI management --}}
<div x-data="{
    deleteModalOpen: false,
    paperModalOpen: false,
    copyDeleteModalOpen: false
}" 
@open-delete-modal.window="deleteModalOpen = true"
@open-paper-modal.window="paperModalOpen = true"
@open-copy-delete-modal.window="copyDeleteModalOpen = true"
@close-modals.window="deleteModalOpen = false; paperModalOpen = false; copyDeleteModalOpen = false"
class="p-6">
    <x-mary-header 
        title="Academic Paper Directory" 
        subtitle="Browse and manage Academic Paper documents from the CEIT Library"
        separator 
        class="mb-6" 
    />

    {{-- Header Actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <x-mary-button 
            wire:click="create" 
            class="btn-primary" 
            icon="o-plus"
            wire:loading.attr="disabled"
            wire:target="create"
        >
            <span wire:loading.remove wire:target="create">Create Academic Paper</span>
            <span wire:loading wire:target="create">Loading...</span>
        </x-mary-button>
        <div class="flex-1 sm:max-w-md">
            <x-mary-input 
                label="Search Title" 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search by title..." 
                icon="o-magnifying-glass" 
                clearable 
            />
        </div>
    </div>

    @if($this->dept)
        <div class="alert alert-info mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span>Filtered by department: <strong>{{ strtoupper($this->dept) }}</strong></span>
        </div>
    @endif

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
                    <x-mary-button 
                        icon="o-eye" 
                        wire:click="showPaperDetails({{ $paper->id }})"
                        class="btn-xs sm:btn-sm btn-ghost flex-1 min-w-[80px]" 
                        label="View"
                        wire:loading.attr="disabled"
                        wire:target="showPaperDetails({{ $paper->id }})"
                    />
                    <x-mary-button 
                        icon="o-pencil" 
                        wire:click="edit({{ $paper->id }})"
                        class="btn-xs sm:btn-sm btn-ghost flex-1 min-w-[80px]" 
                        label="Edit"
                        wire:loading.attr="disabled"
                        wire:target="edit({{ $paper->id }})"
                    />
                    <x-mary-button 
                        icon="o-trash" 
                        wire:click="$set('deleteId', {{ $paper->id }})"
                        @click="$dispatch('open-delete-modal')"
                        class="btn-xs sm:btn-sm btn-ghost text-error flex-1 min-w-[80px]" 
                        label="Delete"
                    />
                </div>
            </div>
        @empty
            <div class="text-center py-8 sm:py-12 bg-base-100 rounded-lg border border-base-300">
                <x-mary-icon name="o-document-magnifying-glass" class="w-12 h-12 sm:w-16 sm:h-16 mx-auto text-base-content/40 mb-4" />
                <h3 class="text-base sm:text-lg font-medium text-base-content mb-2 px-4">No Academic Papers Found</h3>
                <p class="text-xs sm:text-sm text-base-content/70 px-4">
                    @if($search)
                        There's no academic paper matching your query "{{ $search }}"
                    @else
                        No academic papers are available at the moment
                    @endif
                </p>
            </div>
        @endforelse

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
                        @if($search)
                            There's no academic paper matching your query "{{ $search }}"
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
                <x-mary-button 
                    wire:click="showPaperDetails({{ $row->id }})"
                    icon="o-eye"
                    class="btn-sm btn-ghost"
                    tooltip-left="View Details"
                    wire:loading.attr="disabled"
                    wire:target="showPaperDetails({{ $row->id }})"
                />
                <x-mary-button 
                    wire:click="edit({{ $row->id }})"
                    icon="o-pencil"
                    class="btn-sm btn-ghost"
                    tooltip-left="Edit"
                    wire:loading.attr="disabled"
                    wire:target="edit({{ $row->id }})"
                />
                <x-mary-button 
                    @click="$wire.deleteId = {{ $row->id }}; $dispatch('open-delete-modal')"
                    icon="o-trash"
                    class="btn-sm btn-ghost text-error"
                    tooltip-left="Delete"
                />
            </div>
            @endscope
        </x-mary-table>
        </div>

        @if($this->academicPapers->hasPages())
            <div class="mt-6">
                {{ $this->academicPapers->links() }}
            </div>
        @endif
    </div>

    {{-- Delete Confirmation Modal (Alpine + Livewire events) --}}
    <div x-show="deleteModalOpen" 
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="deleteModalOpen = false"
         @close-modals.window="deleteModalOpen = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            {{-- Backdrop --}}
            <div x-show="deleteModalOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="deleteModalOpen = false"
                 class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm"></div>

            {{-- Modal Content --}}
            <div x-show="deleteModalOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-base-100 rounded-lg shadow-xl max-w-md w-full p-6 z-50">
                
                <h3 class="text-lg font-bold mb-2">Delete Academic Paper</h3>
                <p class="text-sm text-base-content/70 mb-4">Are you sure?</p>
                <p class="mb-6">Are you sure you want to delete this academic paper? This action cannot be undone.</p>
                
                <div class="flex justify-end gap-2">
                    <button @click="deleteModalOpen = false" class="btn btn-ghost">Cancel</button>
                    <button 
                        wire:click="performDelete"
                        wire:loading.attr="disabled"
                        wire:target="performDelete"
                        class="btn btn-error">
                        <span wire:loading.remove wire:target="performDelete">Delete</span>
                        <span wire:loading wire:target="performDelete" class="loading loading-spinner loading-sm"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Academic Paper Details Modal (Alpine + Livewire events) --}}
    <div x-show="paperModalOpen" 
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="paperModalOpen = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            {{-- Backdrop --}}
            <div x-show="paperModalOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="paperModalOpen = false"
                 class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm"></div>

            {{-- Modal Content --}}
            <div x-show="paperModalOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-base-100 rounded-lg shadow-xl w-11/12 max-w-5xl p-6 z-50 max-h-[90vh] overflow-y-auto">
                
                <h3 class="text-lg font-bold mb-4">{{ $this->selectedPaper->title ?? 'Academic Paper Details' }}</h3>
                
                @if($this->selectedPaper)
                                    <div class="space-y-6">
                                        <!-- Title Section -->
                                        <div class="flex flex-col sm:flex-row items-start justify-between gap-4">
                                            <div class="flex-1">
                                                <h3 class="text-lg font-bold">{{ $this->selectedPaper->title }}</h3>
                                            </div>
                                            @if($this->departmentIcon)
                                                <img src="{{ $this->departmentIcon }}" alt="{{ $this->selectedPaper->department }} Logo" class="w-20 h-20 object-contain">
                                            @endif
                                        </div>

                                        <!-- Details Grid -->
                                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                            <div class="space-y-2">
                                                <div><span class="font-semibold">Catalog Code:</span> {{ $this->selectedPaper->catalog_code }}</div>
                                                <div><span class="font-semibold">Department:</span> {{ $this->selectedPaper->department }}</div>
                                                <div>
                                                    <span class="font-semibold">Members:</span>
                                                    @forelse($this->selectedPaper->authors as $author)
                                                        {{ $author->name }}{{ !$loop->last ? ', ' : '' }}
                                                    @empty
                                                        No authors listed
                                                    @endforelse
                                                </div>
                                            </div>

                                            <div class="space-y-2">
                                                <div><span class="font-semibold">Adviser:</span> {{ $this->selectedPaper->adviser?->name ?? 'N/A' }}</div>
                                                <div><span class="font-semibold">Dean:</span> {{ $this->selectedPaper->dean?->name ?? 'N/A' }}</div>
                                                <div><span class="font-semibold">Year:</span> {{ $this->selectedPaper->publication_year }}</div>
                                            </div>
                                        </div>

                                        <!-- Copies Table -->
                                        @if($this->selectedPaper->copies->count() > 0)
                                            <div class="overflow-x-auto">
                                                <table class="table table-sm w-full">
                                                    <thead>
                                                        <tr>
                                                            <th>Copy ID</th>
                                                            <th>Availability</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($this->selectedPaper->copies as $copy)
                                                            <tr wire:key="copy-{{ $copy->id }}">
                                                                <td>{{ $copy->id }}</td>
                                                                <td>
                                                                    <span class="badge {{ $this->getStatusBadgeClass($copy->status) }}">
                                                                        {{ $copy->status }}
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    @if($copy->status === 'Available')
                                                                        <div class="flex gap-2">
                                                                            <x-mary-button 
                                                                                wire:click="requestQr({{ $copy->id }})"
                                                                                icon="o-qr-code"
                                                                                class="btn-success btn-sm"
                                                                                tooltip="Generate QR"
                                                                                wire:loading.attr="disabled"
                                                                                wire:target="requestQr({{ $copy->id }})"
                                                                            />
                                                                            <x-mary-button 
                                                                                wire:click="$set('copyToDelete', {{ $copy->id }})"
                                                                                @click="$dispatch('open-copy-delete-modal')"
                                                                                icon="o-trash"
                                                                                class="btn-error btn-sm"
                                                                                tooltip="Delete Copy"
                                                                            />
                                                                        </div>
                                                                    @else
                                                                        <span class="text-error text-sm">Not Available</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    </div>
                @endif
                
                <div class="flex justify-end mt-6">
                    <button @click="paperModalOpen = false" class="btn btn-primary">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Create/Edit Academic Paper Drawer --}}
    <x-mary-drawer wire:model="formDrawer" :title="$isEditing ? 'Edit Academic Paper' : 'Create Academic Paper'" right class="w-11/12 lg:w-1/3" separator>
                                <x-mary-form wire:submit="saveAcademicPaper">
                                    {{-- Validation Errors Display --}}
                                    <x-mary-errors title="Please fix the following errors:" description="Review the fields below." icon="o-exclamation-triangle" class="mb-4" />
                                    
                                    {{-- Change Indicator Header --}}
                                    @if($isEditing)
                                        <div wire:dirty wire:target="form" class="alert alert-info mb-4">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            <span>You have unsaved changes to academic paper ID {{ $form->id ?? 'N/A' }}</span>
                                        </div>
                                    @endif

                                    <label class="block text-sm font-medium text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.title" @endif>
                                        Title @if($isEditing) <span wire:dirty wire:target="form.title" class="text-orange-400">*</span> @endif
                                    </label>
                                    <x-mary-input wire:model="form.title" required class="mb-4" placeholder="Enter title" />
                                    
                                    <label class="block text-sm font-medium text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.department" @endif>
                                        Department @if($isEditing) <span wire:dirty wire:target="form.department" class="text-orange-400">*</span> @endif
                                    </label>
                                    <x-mary-select icon="o-building-library" wire:model="form.department" :options="$form->department_choices" class="mb-4" placeholder="Select Department" required />
                                    
                                    <label class="block text-sm font-medium text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.publication_year" @endif>
                                        Publication Year @if($isEditing) <span wire:dirty wire:target="form.publication_year" class="text-orange-400">*</span> @endif
                                    </label>
                                    <x-mary-select icon="o-calendar" wire:model="form.publication_year" :options="$form->year_choices" required class="mb-4" placeholder="Select Year" />
                                    
                                    <label class="block text-sm font-medium text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.paper_type" @endif>
                                        Paper Type @if($isEditing) <span wire:dirty wire:target="form.paper_type" class="text-orange-400">*</span> @endif
                                    </label>
                                    <x-mary-select icon="o-document" wire:model="form.paper_type" :options="$form->type_choices" class="mb-4" placeholder="Select Paper Type" required />
                                    
                                                                        <label class="block text-sm font-medium text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.adviser_id" @endif>
                                        Research Project Adviser @if($isEditing) <span wire:dirty wire:target="form.adviser_id" class="text-orange-400">*</span> @endif
                                    </label>
                                    <x-mary-choices 
                                        wire:model="form.adviser_id" 
                                        placeholder="Search Research Adviser..." 
                                        single 
                                        searchable 
                                        search-function="searchAdvisers" 
                                        icon="o-user"
                                        min-chars="1"
                                        debounce="300ms"
                                        :options="$form->adviser_options ?? []" 
                                        clearable
                                        hint="Start typing to search for an adviser"
                                        error-field="form.adviser_id" />
                                   
                                    
                                    <label class="block text-sm font-medium text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.dean_id" @endif>
                                        Dean @if($isEditing) <span wire:dirty wire:target="form.dean_id" class="text-orange-400">*</span> @endif
                                    </label>
                                    <x-mary-choices 
                                        wire:model="form.dean_id" 
                                        placeholder="Search dean..." 
                                        single 
                                        searchable 
                                        search-function="searchDeans" 
                                        icon="o-user-circle"
                                        min-chars="1"
                                        debounce="300ms"
                                        :options="$form->dean_options ?? []"
                                        clearable
                                        hint="Start typing to search for a dean"
                                        error-field="form.dean_id" />
                                   

                                    <label class="block text-sm font-medium text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.author_names" @endif>
                                        Authors @if($isEditing) <span wire:dirty wire:target="form.author_names" class="text-orange-400">*</span> @endif
                                    </label>
                                    <x-mary-tags 
                                        wire:model="form.author_names" 
                                        placeholder="Enter author names and hit enter" 
                                        icon="o-user-group" 
                                        clearable
                                        hint="Press Enter after typing each author name"
                                        error-field="form.author_names" />
                                   

                                    <label class="block text-sm font-medium text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.number_of_copies" @endif>
                                        Number of Copies @if($isEditing) <span wire:dirty wire:target="form.number_of_copies" class="text-orange-400">*</span> @endif
                                    </label>
                                    @if($isEditing)
                                        <div class="mb-4">
                                            <x-mary-input type="number" wire:model="form.number_of_copies" min="1" max="100" placeholder="Enter number of copies" icon="o-document-duplicate" disabled />
                                            <div class="text-sm text-warning mt-1 flex items-center gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                                </svg>
                                                Copies cannot be modified here. Manage individual copies in the view modal.
                                            </div>
                                        </div>
                                    @else
                                        <x-mary-input type="number" wire:model="form.number_of_copies" min="1" max="100" placeholder="Enter number of copies" icon="o-document-duplicate" hint="How many copies of this paper should be available" required />
                                    @endif

                                    <x-slot:actions>
                                        <x-mary-button label="Cancel" class="btn-ghost" @click="$wire.formDrawer = false" />
                                        <button 
                                            type="submit"
                                            class="btn btn-primary disabled:opacity-75 disabled:bg-blue-300"
                                            wire:dirty.class="hover:bg-blue-900"
                                            wire:dirty.remove.attr="disabled"
                                            wire:loading.attr="disabled"
                                            wire:target="saveAcademicPaper"
                                            @if(!$form->adviser_id || !$form->dean_id || $errors->has('form.adviser_id') || $errors->has('form.dean_id')) disabled @endif>
                                            <span wire:loading.remove wire:target="saveAcademicPaper">{{ $isEditing ? 'Update' : 'Save' }}</span>
                                            <span wire:loading wire:target="saveAcademicPaper" class="loading loading-spinner loading-sm"></span>
                                        </button>
                                    </x-slot:actions>
                                </x-mary-form>  
                            </x-mary-drawer>

    {{-- Copy Deletion Confirmation Modal (Alpine + Livewire events) --}}
    <div x-show="copyDeleteModalOpen" 
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         @keydown.escape.window="copyDeleteModalOpen = false">
        <div class="flex items-center justify-center min-h-screen px-4">
            {{-- Backdrop --}}
            <div x-show="copyDeleteModalOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="copyDeleteModalOpen = false"
                 class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm"></div>

            {{-- Modal Content --}}
            <div x-show="copyDeleteModalOpen"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative bg-base-100 rounded-lg shadow-xl max-w-md w-full p-6 z-50">
                
                <h3 class="text-lg font-bold mb-2">Delete Copy</h3>
                <p class="text-sm text-base-content/70 mb-4">Are you sure?</p>
                <p class="mb-6">Are you sure you want to delete copy #{{ $copyToDelete ?? 'N/A' }}? This action cannot be undone. Only available copies can be deleted.</p>
                
                <div class="flex justify-end gap-2">
                    <button @click="copyDeleteModalOpen = false" class="btn btn-ghost">Cancel</button>
                    <button 
                        wire:click="performCopyDelete"
                        @click="copyDeleteModalOpen = false"
                        wire:loading.attr="disabled"
                        wire:target="performCopyDelete"
                        class="btn btn-error">
                        <span wire:loading.remove wire:target="performCopyDelete">Delete Copy</span>
                        <span wire:loading wire:target="performCopyDelete" class="loading loading-spinner loading-sm"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
