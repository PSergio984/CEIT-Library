<!-- Modal for Academic Paper Details -->
<x-mary-modal wire:model="showModal" title="" box-class="max-w-6xl w-full">
    @if($selectedPaper)
        <div class="space-y-6">
            <!-- Title Section -->
            <div class="flex items-start justify-between">
                <h3 class="text-xl font-bold pr-8 flex-1">{{ $selectedPaper->title }}</h3>
                <div class="text-right text-sm">
                    <div class="font-semibold">{{ $selectedPaper->publication_year }}</div>
                    <div class="text-xs">{{ $selectedPaper->catalog_code }}</div>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <div>
                        <span class="font-semibold">Department:</span> {{ $selectedPaper->department }}
                    </div>
                    <div>
                        <span class="font-semibold">Members:</span>
                        @forelse($selectedPaper->authors as $author)
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
                        <span class="font-semibold">Adviser:</span> {{ $selectedPaper->research_project_adviser }}
                    </div>
                    <div>
                        <span class="font-semibold">Year:</span> {{ $selectedPaper->publication_year }}
                    </div>
                    <div>
                        <span class="font-semibold">Total Copies:</span> {{ $selectedPaper->copies->count() }}
                    </div>
                    <div>
                        <span
                            class="font-semibold">Available Copies:</span> {{ $selectedPaper->copies->where('status', 'Available')->count() }}
                    </div>
                </div>
            </div>

            <!-- Copies Table -->
            @if($selectedPaper->copies->count() > 0)
                <div class="overflow-x-auto">
                    <table class="table table-sm w-full">
                        <thead>
                        <tr>
                            <th>Copy Id</th>
                            <th>Availability</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($selectedPaper->copies as $copy)
                            <tr>
                                <td>{{ $copy->id }}</td>
                                <td>
                                        <span
                                            class="badge px-4 py-1 {{ $copy->status === 'Available' ? 'badge-success' : ($copy->status === 'Borrowed' ? 'badge-warning' : 'badge-error') }}">
                                            {{ $copy->status }}
                                        </span>
                                </td>
                                <td>
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
