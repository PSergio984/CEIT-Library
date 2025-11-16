@props(['formDrawer', 'isEditing', 'form'])

<x-mary-drawer 
    wire:model="formDrawer" 
    :title="$isEditing ? 'Edit Academic Paper' : 'Create Academic Paper'" 
    right 
    class="w-11/12 lg:w-2/5" 
    separator>
    <div class="p-6">
        <x-mary-form wire:submit="saveAcademicPaper">
            {{-- Validation Errors Display --}}
            <x-mary-errors title="Please fix the following errors:" description="Review the fields below." icon="o-exclamation-triangle" class="mb-6" />
            
            {{-- Change Indicator Header --}}
            @if($isEditing)
                <div wire:dirty wire:target="form" class="alert alert-info mb-6 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>You have unsaved changes to academic paper ID {{ $form->id ?? 'N/A' }}</span>
                </div>
            @endif

            {{-- Title Field --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.title" @endif>
                    Title @if($isEditing) <span wire:dirty wire:target="form.title" class="text-orange-400">*</span> @endif
                </label>
                <x-mary-input wire:model="form.title" required placeholder="Enter academic paper title" />
            </div>
            
            {{-- Department Field --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.department" @endif>
                    Department @if($isEditing) <span wire:dirty wire:target="form.department" class="text-orange-400">*</span> @endif
                </label>
                <x-mary-select icon="o-building-library" wire:model="form.department" :options="$form->department_choices" required />
            </div>
            
            {{-- Publication Year Field --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.publication_year" @endif>
                    Publication Year @if($isEditing) <span wire:dirty wire:target="form.publication_year" class="text-orange-400">*</span> @endif
                </label>
                <x-mary-select icon="o-calendar" wire:model="form.publication_year" :options="$form->year_choices" required />
            </div>
            
            {{-- Paper Type Field --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.paper_type" @endif>
                    Paper Type @if($isEditing) <span wire:dirty wire:target="form.paper_type" class="text-orange-400">*</span> @endif
                </label>
                <x-mary-select icon="o-document" wire:model="form.paper_type" :options="$form->type_choices" required />
            </div>
            
            {{-- Research Adviser Field --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.research_adviser_id" @endif>
                    Research Adviser @if($isEditing) <span wire:dirty wire:target="form.research_adviser_id" class="text-orange-400">*</span> @endif
                </label>
                <x-mary-choices 
                    wire:model="form.research_adviser_id" 
                    single 
                    searchable 
                    search-function="searchResearchAdvisers" 
                    icon="o-user" 
                    min-chars="0" 
                    debounce="300ms" 
                    :options="$form->research_adviser_options ?? []" 
                    hint="Start typing to search for a research adviser" 
                    placeholder="Select Research Adviser" 
                    error-field="form.research_adviser_id" />
            </div>

            {{-- Technical Adviser Field --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.technical_adviser_id" @endif>
                    Technical Adviser @if($isEditing) <span wire:dirty wire:target="form.technical_adviser_id" class="text-orange-400">*</span> @endif
                </label>
                <x-mary-choices 
                    wire:model="form.technical_adviser_id" 
                    single 
                    searchable 
                    search-function="searchTechnicalAdvisers" 
                    icon="o-user" 
                    min-chars="0" 
                    debounce="300ms" 
                    :options="$form->technical_adviser_options ?? []" 
                    hint="Start typing to search for a technical adviser" 
                    placeholder="Select Technical Adviser" 
                    error-field="form.technical_adviser_id" />
            </div>

            {{-- Dean Field --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.dean_id" @endif>
                    Dean @if($isEditing) <span wire:dirty wire:target="form.dean_id" class="text-orange-400">*</span> @endif
                </label>
                <x-mary-choices 
                    wire:model="form.dean_id" 
                    single 
                    searchable 
                    search-function="searchDeans" 
                    icon="o-user" 
                    min-chars="0" 
                    debounce="300ms" 
                    :options="$form->dean_options ?? []" 
                    hint="Start typing to search for a dean" 
                    placeholder="Select Dean" 
                    error-field="form.dean_id" />
            </div>

            {{-- Authors Field --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.author_ids" @endif>
                    Authors @if($isEditing) <span wire:dirty wire:target="form.author_ids" class="text-orange-400">*</span> @endif
                </label>
                <x-mary-choices 
                    wire:model="form.author_ids" 
                    searchable 
                    clearable
                    search-function="searchAuthors" 
                    icon="o-user-group" 
                    min-chars="0" 
                    debounce="300ms" 
                    :options="$form->author_options ?? []" 
                    hint="Start typing to search for authors" 
                    placeholder="Select Authors" 
                    error-field="form.author_ids" />
            </div>

            {{-- Number of Copies Field --}}
            <div class="mb-6">
                <label class="block text-sm font-semibold text-base-content mb-2" @if($isEditing) wire:dirty.class="text-orange-400" wire:target="form.number_of_copies" @endif>
                    Number of Copies @if($isEditing) <span wire:dirty wire:target="form.number_of_copies" class="text-orange-400">*</span> @endif
                </label>
                @if($isEditing)
                    <x-mary-input 
                        type="number" 
                        wire:model.blur="form.number_of_copies"
                        min="{{ $form->number_of_copies }}"
                        max="100" 
                        placeholder="Add more copies" 
                        icon="o-document-duplicate"
                        hint="Current: {{ $form->number_of_copies }}. Can only add copies (min: {{ $form->number_of_copies }})" 
                    />
                    <div class="text-xs text-info mt-2 flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>To remove copies, use the copy deletion button in the view modal.</span>
                    </div>
                @else
                    <x-mary-input 
                        type="number" 
                        wire:model="form.number_of_copies" 
                        min="1" 
                        max="100" 
                        placeholder="Enter number of copies" 
                        icon="o-document-duplicate" 
                        hint="How many copies of this paper should be available" 
                        required />
                @endif
            </div>

            <x-slot:actions>
                <x-mary-button label="Cancel" class="btn-ghost" @click="$wire.formDrawer = false" />
                @if($isEditing)
                    {{-- Update button: disabled by default, enabled when form is dirty --}}
                    <button 
                        x-data="{ isDirty: false }"
                        x-init="$wire.$watch('form', () => { isDirty = true })"
                        type="submit"
                        class="btn btn-primary"
                        :class="{ 'btn-disabled opacity-50': !isDirty }"
                        :disabled="!isDirty"
                        wire:loading.attr="disabled"
                        wire:target="saveAcademicPaper">
                        <span wire:loading.remove wire:target="saveAcademicPaper">Update</span>
                        <span wire:loading wire:target="saveAcademicPaper" class="loading loading-spinner loading-sm"></span>
                        <span wire:loading wire:target="saveAcademicPaper">Updating...</span>
                    </button>
                @else
                    {{-- Save button: always enabled for new records --}}
                    <button 
                        type="submit"
                        class="btn btn-primary"
                        wire:loading.attr="disabled"
                        wire:target="saveAcademicPaper">
                        <span wire:loading.remove wire:target="saveAcademicPaper">Save</span>
                        <span wire:loading wire:target="saveAcademicPaper" class="loading loading-spinner loading-sm"></span>
                        <span wire:loading wire:target="saveAcademicPaper">Saving...</span>
                    </button>
                @endif
            </x-slot:actions>
        </x-mary-form>
    </div>
</x-mary-drawer>
