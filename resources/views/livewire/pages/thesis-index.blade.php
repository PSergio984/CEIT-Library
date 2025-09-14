{{-- resources/views/livewire/thesis-index.blade.php --}}
<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-base-content leading-tight">
            {{ __('Thesis Directory') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-base-100 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-base-content mb-2">Library Thesis Collection</h3>
                        <p class="text-sm text-base-content/70">Browse and access thesis documents from the CEIT Library</p>
                    </div>

                    <div class="overflow-x-auto">
                      <x-mary-table
                        :headers="$headers"
                        :rows="$this->theses"
                        with-pagination
                        :sort-by="$sortBy"
                        per-page="perPage"
                        :per-page-values="[5, 10, 25, 50]"
                        link="/thesis/{id}"
                        row-class="text-base-content hover:bg-base-200 hover:text-base-content"
                        header-class="text-base-content"
                        class="w-full"
                    />


                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
