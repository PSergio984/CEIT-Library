<div class="bg-base text-base-content">
    <x-slot name="header">
        <h2 class="font-semibold text-xl leading-tight">
            {{ __('Academic Paper Details') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-base overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-base">
                    <div class="mb-6">
                        <h3 class="text-lg font-bold mb-2">{{ $academicPaper->title }}</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <span class="font-semibold">Catalog Code:</span> {{ $academicPaper->catalog_code }}
                            </div>
                            <div>
                                <span class="font-semibold">Year:</span> {{ $academicPaper->publication_year }}
                            </div>
                            <div>
                                <span class="font-semibold">Department:</span> {{ $academicPaper->department }}
                            </div>
                            <div>
                                <span class="font-semibold">Adviser:</span> {{ $academicPaper->research_project_adviser }}
                            </div>
                            <div>
                                <span class="font-semibold">Dean:</span> {{ $academicPaper->dean }}
                            </div>
                        </div>
                    </div>
                    <div class="mb-6">
                        <h4 class="font-semibold mb-2">Members</h4>
                        <ul class="list-disc ml-6">
                            @forelse($academicPaper->authors as $author)
                                <li>{{ $author->name }}</li>
                            @empty
                                <li>No authors listed.</li>
                            @endforelse
                        </ul>
                    </div>
                    <div class="mb-6">
                        <h4 class="font-semibold mb-2">Copies</h4>
                        <div>
                            <span class="font-semibold">Total Copies:</span> {{ $academicPaper->copies->count() }}<br>
                            <span class="font-semibold">Available Copies:</span> {{ $academicPaper->copies->where('status', 'Available')->count() }}
                        </div>
                        <div class="w-full mt-4">
                            <x-mary-table
                                :headers="$headers"
                                :rows="$this->rows"
                                :sort-by="$sortBy"
                                per-page="perPage"
                                row-class="text-base-content hover:bg-base-200 hover:text-base-content"
                                header-class="text-base-content"
                                class="w-full"
                            >
                                  @scope('actions', $row)
                                        <x-mary-button icon="o-qr-code" spinner class="bg-green-700 hover:bg-green-900 text-white btn-sm" wire:click="requestQr({{ $row['id'] }})" />
                                  @endscope

                            </x-mary-table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
