<div class="max-w-2xl mx-auto p-6" x-data="{
    get isFormValid() {
        const form = $wire.form;
        return form.title && form.title.length > 0 &&
               form.department && 
               form.publication_year &&
               form.paper_type &&
               form.research_project_adviser && form.research_project_adviser.length > 0 &&
               form.dean && form.dean.length > 0;
    }
}">

        <x-mary-form wire:submit="save">
            <x-mary-input label="Title" wire:model="form.title" required class="mb-4" />
            <x-mary-select label="Department" icon="o-building-library" wire:model="form.department" :options="$form->department_choices" class="mb-4" required />
            <x-mary-select label="Publication Year" wire:model="form.publication_year" :options="$form->year_choices" icon="o-calendar" required class="mb-4" />
            <x-mary-select label="Paper Type" wire:model="form.paper_type" icon="o-document" :options="$form->type_choices" inline required />
            <x-mary-input label="Research Project Adviser" wire:model="form.research_project_adviser" required class="mb-4" />
            <x-mary-input label="Dean" wire:model="form.dean" required class="mb-4" />

            <x-mary-button class="btn-primary" type="submit"
                x-bind:disabled="!isFormValid"
                x-bind:class="{ 'opacity-50 cursor-not-allowed': !isFormValid }"> Save </x-mary-button>

       </x-mary-form>

</div>
