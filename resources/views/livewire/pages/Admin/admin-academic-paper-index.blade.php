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
                         <x-mary-input label="Search Title Here" wire:model.live.debounce="search" placeholder="Search Title Here" inline icon="o-magnifying-glass" />
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
                            link="/academic-papers/{id}"
                            row-class="text-base-content hover:bg-base-100 hover:text-base-content transition-all duration-150 border-b border-base-200 last:border-b-0"
                            header-class="text-base-content bg-gradient-to-r from-base-200 to-base-300 font-semibold border-b-2 border-base-300"
                            class="table-enhanced rounded-lg shadow-lg overflow-hidden w-full"
                        >
                            @scope('actions', $row)
                            <div class="flex items-center gap-4 justify-center">
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

                            {{-- Create/Edit Academic Paper Drawer --}}
                            <x-mary-drawer wire:model="formDrawer" :title="$isEditing ? 'Edit Academic Paper' : 'Create Academic Paper'" right class="w-11/12 lg:w-1/3" separator>
                                <x-mary-form wire:submit="saveAcademicPaper">
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
                                    <x-mary-input wire:model="form.research_project_adviser" required class="mb-4" placeholder="Enter adviser name" />
                                    
                                    <label class="block text-sm font-medium text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.dean" @endif>
                                        Dean @if($isEditing) <span wire:dirty wire:target="form.dean" class="text-orange-400">*</span> @endif
                                    </label>
                                    <x-mary-input wire:model="form.dean" required class="mb-4" placeholder="Enter dean name" />

                                    <x-slot:actions>
                                        <x-mary-button label="Cancel" class="btn-ghost" @click="$wire.formDrawer = false" />
                                        <button 
                                            type="submit"
                                            class="btn btn-primary disabled:opacity-75 disabled:bg-blue-300"
                                            wire:dirty.class="hover:bg-blue-900"
                                            wire:dirty.remove.attr="disabled"
                                            @if($isEditing) disabled @endif>
                                            {{ $isEditing ? 'Update' : 'Save' }}
                                        </button>
                                    </x-slot:actions>
                                </x-mary-form>  
                            </x-mary-drawer>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
