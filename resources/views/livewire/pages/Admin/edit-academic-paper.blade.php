<div class="max-w-2xl mx-auto p-6" x-data="{
    isDirty: false,
    get isFormValid() {
        const form = $wire.form;
        return this.isDirty &&
               form.title && form.title.length > 0 &&
               form.publication_year &&
               form.paper_type && form.paper_type.length > 0 &&
               form.research_project_adviser && form.research_project_adviser.length > 0 &&
               form.department && form.department.length > 0 &&
               form.dean && form.dean.length > 0;
    }
}" x-init="$wire.$watch('form', () => { isDirty = true })">
 <h3 class="text-lg text-white font-bold  mb-3">Edit Academic Paper( ID: {{$form ->id}})</h3>
        <x-mary-form wire:submit="save">
            <x-mary-input label="Catalog Code" :value="$form->catalog_code" readonly class="mb-4" />
            <x-mary-input label="Title" wire:model="form.title" required class="mb-4" />
            <x-mary-input label="Publication Year" type="number" wire:model="form.publication_year" required class="mb-4" />
            <x-mary-input label="Paper Type" wire:model="form.paper_type" required class="mb-4" />
            <x-mary-input label="Research Project Adviser" wire:model="form.research_project_adviser" required class="mb-4" />
            <x-mary-input label="Department" wire:model="form.department" required class="mb-4" />
            <x-mary-input label="Dean" wire:model="form.dean" required class="mb-4" />

            <x-mary-button class="btn-primary" type="submit"
                x-bind:disabled="!isFormValid"
                x-bind:class="{ 'opacity-50 cursor-not-allowed': !isFormValid }"> Save </x-mary-button>

       </x-mary-form>

</div>
