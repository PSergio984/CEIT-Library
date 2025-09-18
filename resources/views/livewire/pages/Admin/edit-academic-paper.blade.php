<div class="max-w-2xl mx-auto p-6">
 <h3 class="text-lg text-white font-bold  mb-3">Edit Academic Paper( ID: {{$form ->id}})</h3>
        <x-mary-form wire:submit="save">
            <x-mary-input label="Catalog Code" :value="$form->catalog_code" readonly class="mb-4" />
            <x-mary-input label="Title" wire:model="form.title" required class="mb-4" />
            <x-mary-input label="Publication Year" type="number" wire:model="form.publication_year" required class="mb-4" />
            <x-mary-input label="Paper Type" wire:model="form.paper_type" required class="mb-4" />
            <x-mary-input label="Research Project Adviser" wire:model="form.research_project_adviser" required class="mb-4" />
            <x-mary-input label="Department" wire:model="form.department" required class="mb-4" />
            <x-mary-input label="Dean" wire:model="form.dean" required class="mb-4" />

            <x-mary-button class="btn-primary" type="submit"> Save </x-mary-button>

       </x-mary-form>

</div>
