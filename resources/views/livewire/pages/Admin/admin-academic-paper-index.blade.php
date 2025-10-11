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
                            row-class="text-foreground hover:bg-muted hover:text-foreground"
                            header-class="text-foreground"
                            class="w-full"
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
                                    <button type="button" wire:click="performDelete" class="btn-delete-modal">
                                        Delete
                                    </button>
                                </x-slot:actions>
                            </x-mary-modal>

                            {{-- Create/Edit Academic Paper Drawer --}}
                            <x-mary-drawer wire:model="formDrawer" :title="$isEditing ? 'Edit Academic Paper' : 'Create Academic Paper'" right class="w-11/12 lg:w-1/3" separator>
                                <x-mary-form wire:submit="saveAcademicPaper">
                                    <x-mary-input label="Title" wire:model="form.title" required class="mb-4" />
                                    <x-mary-select label="Department" icon="o-building-library" wire:model="form.department" :options="$form->department_choices" class="mb-4" placeholder="Select Department" required />
                                    <x-mary-select label="Publication Year" wire:model="form.publication_year" :options="$form->year_choices" icon="o-calendar" required class="mb-4" placeholder="Select Year" />
                                    <x-mary-select label="Paper Type" wire:model="form.paper_type" icon="o-document" :options="$form->type_choices" class="mb-4" placeholder="Select Paper Type" required />
                                    <x-mary-input label="Research Project Adviser" wire:model="form.research_project_adviser" required class="mb-4" />
                                    <x-mary-input label="Dean" wire:model="form.dean" required class="mb-4" />

                                    <x-slot:actions>
                                        <x-mary-button label="Cancel" class="btn-ghost" @click="$wire.formDrawer = false" />
                                        <x-mary-button :label="$isEditing ? 'Update' : 'Save'" class="btn-primary" type="submit" spinner="saveAcademicPaper" />
                                    </x-slot:actions>
                                </x-mary-form>
                            </x-mary-drawer>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
