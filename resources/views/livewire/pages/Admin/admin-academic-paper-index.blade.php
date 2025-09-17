<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-base-content leading-tight">
            {{ __('Academic Paper Directory') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-base-100 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <!-- Header Actions -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
                        <div><a href="{{ route('admin.academic-paper.create') }}" class="btn btn-primary bg-primary text-base-100 font-semibold">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                Create Academic Paper
                            </a>
                        </div>
                        <div class="flex gap-2 items-center">
                         <x-mary-input label="Search Title Here" wire:model="search" placeholder="Search Title Here" inline icon="o-magnifying-glass" />
                        </div>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-base-content mb-2">Library Academic Paper Collection</h3>
                        <p class="text-sm text-base-content/70">Browse and access Academic Paper documents from the CEIT Library</p>
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
                            row-class="text-base-content hover:bg-base-200 hover:text-base-content"
                            header-class="text-base-content"
                            class="w-full"
                        >
                            @scope('actions', $row)
                            <div class="flex items-center gap-4 justify-center">
                                 <a href="/admin/academic-papers/{{ $row->id }}" wire:navigate>
                                    <x-mary-button icon="o-pencil-square" class="btn-ghost btn-sm"/>
                                </a>
                                <x-mary-button icon="o-trash" wire:click="deleteAcademicPaper({{ $row->id }})" wire:confirm="Are you sure you want to delete this article?" class="btn-ghost btn-sm"/>
                            </div>
                            @endscope

                            </x-mary-table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
