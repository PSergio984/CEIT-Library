{{-- resources/views/livewire/thesis-index.blade.php --}}
<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Thesis Directory') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Library Thesis Collection</h3>
                        <p class="text-sm text-gray-600">Browse and access thesis documents from the CEIT Library</p>
                    </div>

                    <div class="overflow-x-auto">
                        <x-mary-table :headers="$headers"
                                      :rows="$this->theses"
                                      with-pagination
                                      :sort-by="$sortBy"
                                      :per-page="$perPage"
                                      :per-page-values="[5, 10, 25, 50]"
                                       link="/thesis/{id}"
                                      class="w-full [&_td]:text-gray-900 [&_th]:text-gray-900 [&_tbody_tr]:text-gray-900 [&_tbody_tr:hover]:bg-white [&_tbody_tr:hover]:text-gray-900"
                        >
                        </x-mary-table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
