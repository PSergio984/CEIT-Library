<!-- Modal for Academic Paper Details -->
<x-mary-modal wire:model="isModalOpen" title="" box-class="max-w-6xl w-full">
    @if($academicPaper)
        <div class="space-y-6">
            <!-- Title Section -->
            <div class="flex items-start justify-between">
                <h3 class="text-xl font-bold pr-8 flex-1">{{ $academicPaper->title }}</h3>
                <div class="text-right text-sm">
                    <div class="font-semibold">{{ $academicPaper->publication_year }}</div>
                    <div class="text-xs">{{ $academicPaper->catalog_code }}</div>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <div>
                        <span class="font-semibold">Department:</span> {{ $academicPaper->department }}
                    </div>
                    <div>
                        <span class="font-semibold">Members:</span>
                        @forelse($academicPaper->authors as $author)
                            {{ $author->name }}@if(!$loop->last)
                                ,
                            @endif
                        @empty
                            No authors listed
                        @endforelse
                    </div>
                </div>

                <div class="space-y-3">
                    <div>
                        <span class="font-semibold">Adviser:</span> {{ $academicPaper->research_project_adviser }}
                    </div>
                    <div>
                        <span class="font-semibold">Year:</span> {{ $academicPaper->publication_year }}
                    </div>
                    <div>
                        <span class="font-semibold">Total Copies:</span> {{ $academicPaper->copies->count() }}
                    </div>
                    <div>
                        <span
                            class="font-semibold">Available Copies:</span> {{ $academicPaper->copies->where('status', 'Available')->count() }}
                    </div>
                </div>
            </div>

            <!-- Copies Table -->
            @if($academicPaper->copies->count() > 0)
                <div class="overflow-x-auto">
                    <table class="table table-sm w-full border-collapse border border-base-300 rounded-lg overflow-hidden shadow-sm">
                        <thead>
                        <tr class="bg-base-200">
                            <th class="border-b border-base-300 px-4 py-3 text-left font-semibold text-base-content">Copy Id</th>
                            <th class="border-b border-base-300 px-4 py-3 text-left font-semibold text-base-content">Availability</th>
                            <th class="border-b border-base-300 px-4 py-3 text-left font-semibold text-base-content">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($academicPaper->copies as $copy)
                            <tr class="hover:bg-base-100 transition-colors duration-150 border-b border-base-200 last:border-b-0">
                                <td class="px-4 py-3 text-base-content font-medium">{{ $copy->id }}</td>
                                <td class="px-4 py-3">
                                        <span
                                            class="badge px-4 py-1 {{ $copy->status === 'Available' ? 'badge-success' : ($copy->status === 'Borrowed' ? 'badge-warning' : 'badge-error') }}">
                                            {{ $copy->status }}
                                        </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($copy->status === 'Available')
                                        <x-mary-button
                                            icon="o-qr-code"
                                            class="btn-sm btn-success"
                                            wire:click="requestQr({{ $copy->id }})"
                                        >
                                        </x-mary-button>
                                    @else
                                        <span class="text-error text-sm font-bold">Not Available</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

</x-mary-modal>
