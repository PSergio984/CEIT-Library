<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-foreground leading-tight">
            {{ __('Academic Paper Directory') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-background overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <!-- Header Actions -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
                        <div>
                            <button wire:click="create"
                                class="btn btn-primary bg-primary text-primary-content font-semibold">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                Create Academic Paper
                            </button>
                        </div>
                        <div class="flex gap-2 items-center">
                         <x-mary-input label="Search Title Here" wire:model.live.debounce="search" placeholder="Search Title Here" inline icon="o-magnifying-glass" clearable />
                        </div>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-foreground mb-2">
                            Library Academic Paper Collection
                            @if($this->dept)
                                <span class="text-muted-foreground">— {{ strtoupper($this->dept) }}</span>
                            @endif
                        </h3>
                        <p class="text-sm text-muted-foreground">Browse and access Academic Paper documents from the
                            CEIT Library</p>
                    </div>

                    <div class="overflow-x-auto">
                        <x-mary-table
                            :headers="$headers"
                            :rows="$this->academicPapers"
                            with-pagination
                            :sort-by="$sortBy"
                            per-page="perPage"
                            :per-page-values="[5, 10, 25, 50]"
                            row-class="text-base-content hover:bg-base-100 hover:text-base-content transition-all duration-150 border-b border-base-200 last:border-b-0"
                            header-class="text-base-content bg-gradient-to-r from-base-200 to-base-300 font-semibold border-b-2 border-base-300"
                            class="table-enhanced rounded-lg shadow-lg overflow-hidden w-full"
                        >
                            <x-slot:empty>
                              <div class="text-center py-8">
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
                            @scope('cell_status', $row)
                            <span class="badge {{ $row->status === 'Available' ? 'badge-success' : 'badge-error' }}">
                                {{ $row->status }}
                            </span>
                            @endscope

                            @scope('actions', $row)
                            <div class="flex items-center gap-2 justify-center">
                                <x-mary-button icon="o-eye" wire:click="showPaperDetails({{ $row->id }})"
                                               class="btn-ghost btn-sm" tooltip="View Details"/>
                                <x-mary-button icon="o-pencil-square" wire:click="edit({{ $row->id }})"
                                               class="btn-ghost btn-sm" tooltip="Edit"/>
                                <x-mary-button icon="o-trash" wire:click="confirmDelete({{ $row->id }})"
                                               class="btn-ghost btn-sm" tooltip="Delete"/>
                            </div>
                            @endscope

                            </x-mary-table>

                            {{-- Delete Confirmation Modal --}}
                            <x-mary-modal wire:model="deleteModal" title="Delete Academic Paper"  class="backdrop-blur">
                                <p>Are you sure you want to delete this academic paper?</p>
                                <p class="text-sm text-muted-foreground">This action cannot be undone.</p>
                                <x-slot:actions>
                                    <x-mary-button label="Cancel" class="btn-ghost" @click="$wire.deleteModal = false" />
                                    <x-mary-button label="Delete" class="btn-error" wire:click="performDelete" />
                                </x-slot:actions>
                            </x-mary-modal>

                            {{-- Academic Paper Details Modal --}}
                            <x-mary-modal wire:model="showModal" title="" box-class="max-w-5xl w-full">
                                @if($selectedPaper)
                                    <div class="space-y-6">
                                        <!-- Title Section -->
                                        <div class="flex flex-col sm:flex-row items-start justify-between gap-4">
                                            <h3 class="text-lg sm:text-xl font-bold flex-1 pr-4">{{ $selectedPaper->title }}</h3>
                                            <div class="flex items-center gap-3">
                                                @if($this->departmentIcon)
                                                    <img src="{{ $this->departmentIcon }}" alt="{{ $selectedPaper->department }} Logo"
                                                         class="w-20 h-20 sm:w-24 sm:h-24 object-contain">
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Details Grid -->
                                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                                            <div class="space-y-2 sm:space-y-3">
                                                <div>
                                                    <span class="font-semibold">Catalog Code:</span> {{ $selectedPaper->catalog_code }}
                                                </div>
                                                <div>
                                                    <span class="font-semibold">Department:</span> {{ $selectedPaper->department }}
                                                </div>
                                                <div>
                                                    <span class="font-semibold">Members:</span>
                                                    @forelse($selectedPaper->authors as $author)
                                                        {{ $author->name }}@if(!$loop->last)
                                                            ,
                                                        @endif
                                                    @empty
                                                        No authors listed
                                                    @endforelse
                                                </div>
                                            </div>

                                            <div class="space-y-2 sm:space-y-3">
                                                <div>
                                                    <span class="font-semibold">Adviser:</span> {{ $selectedPaper->research_project_adviser }}
                                                </div>
                                                <div>
                                                    <span class="font-semibold">Year:</span> {{ $selectedPaper->publication_year }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Copies Table -->
                                        @if($selectedPaper->copies->count() > 0)
                                            <div class="overflow-x-auto -mx-2 sm:mx-0">
                                                <table class="table table-sm w-full text-sm sm:text-base border-collapse border border-base-300 rounded-lg overflow-hidden shadow-sm">
                                                    <thead>
                                                    <tr class="bg-base-200">
                                                        <th class="border-b border-base-300 px-4 py-3 text-left font-semibold text-base-content">Copy Id</th>
                                                        <th class="border-b border-base-300 px-4 py-3 text-left font-semibold text-base-content">Availability</th>
                                                        <th class="border-b border-base-300 px-4 py-3 text-left font-semibold text-base-content">Action</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    @foreach($selectedPaper->copies as $copy)
                                                        <tr class="hover:bg-base-100 transition-colors duration-150 border-b border-base-200 last:border-b-0">
                                                            <td class="px-4 py-3 text-base-content font-medium">{{ $copy->id }}</td>
                                                            <td class="px-4 py-3">
                                                                <span
                                                                    class="badge px-4 py-1 {{ $this->getStatusBadgeClass($copy->status) }}">
                                                                    {{ $copy->status }}
                                                                </span>
                                                            </td>
                                                            <td class="px-4 py-3">
                                                                <div class="flex items-center gap-2">
                                                                    @if($copy->status === 'Available')
                                                                        <x-mary-button
                                                                            icon="o-qr-code"
                                                                            class="btn-sm btn-success"
                                                                            wire:click="requestQr({{ $copy->id }})"
                                                                            tooltip="Generate QR Code"
                                                                        />
                                                                        <x-mary-button
                                                                            icon="o-trash"
                                                                            class="btn-sm btn-error"
                                                                            wire:click="confirmCopyDelete({{ $copy->id }})"
                                                                            tooltip="Delete Copy"
                                                                        />
                                                                    @else
                                                                        <span class="text-error text-sm font-bold">Not Available</span>
                                                                    @endif
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </x-mary-modal>

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
                                    
                                                                        <label class="block text-sm font-medium text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.research_project_adviser" @endif>
                                        Research Project Adviser @if($isEditing) <span wire:dirty wire:target="form.research_project_adviser" class="text-orange-400">*</span> @endif
                                    </label>
                                    <x-mary-choices 
                                        wire:model="form.research_project_adviser" 
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
                                        error-field="form.research_project_adviser" />
                                   

                                    <label class="block text-sm font-medium text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.dean" @endif>
                                    
                                    <label class="block text-sm font-medium text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.dean" @endif>
                                        Dean @if($isEditing) <span wire:dirty wire:target="form.dean" class="text-orange-400">*</span> @endif
                                    </label>
                                    <x-mary-choices 
                                        wire:model="form.dean" 
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
                                        error-field="form.dean" />
                                   

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
                                            @if($isEditing || trim($form->research_project_adviser ?? '') === '' || trim($form->dean ?? '') === '' || $errors->has('form.research_project_adviser') || $errors->has('form.dean')) disabled @endif>
                                            {{ $isEditing ? 'Update' : 'Save' }}
                                        </button>
                                    </x-slot:actions>
                                </x-mary-form>  
                            </x-mary-drawer>

                            {{-- Copy Deletion Confirmation Modal --}}
                            <x-mary-modal wire:model="copyDeleteModal" title="Delete Copy" class="backdrop-blur">
                                <p>Are you sure you want to delete copy #{{ $copyToDelete }}?</p>
                                <p class="text-sm text-muted-foreground">This action cannot be undone. Only available copies can be deleted.</p>
                                <x-slot:actions>
                                    <x-mary-button label="Cancel" class="btn-ghost" @click="$wire.copyDeleteModal = false" />
                                    <x-mary-button label="Delete Copy" class="btn-error" wire:click="performCopyDelete" />
                                </x-slot:actions>
                            </x-mary-modal>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
