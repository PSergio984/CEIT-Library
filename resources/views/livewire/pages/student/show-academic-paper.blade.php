<div>
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
                        <span class="font-semibold">Adviser:</span> {{ $academicPaper->adviser?->name ?? 'N/A' }}
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

{{-- Borrow QR Code Modal --}}
<x-mary-modal wire:model="isQrModalOpen" title="Borrow QR Code" box-class="max-w-lg">
    @if($qrCodeDataUri)
        <div class="flex flex-col items-center space-y-4">
            {{-- Paper Info --}}
            <div class="text-center mb-2">
                <p class="text-sm text-base-content/70">Paper:</p>
                <p class="font-semibold">{{ $selectedPaperTitle }}</p>
                <p class="text-xs text-base-content/60 mt-1">Copy ID: {{ $selectedInventoryId }}</p>
            </div>

            {{-- QR Code Display --}}
            <div class="relative bg-gradient-to-br from-base-100 to-base-200 p-6 rounded-2xl shadow-lg border-2 border-primary/20">
                {{-- Corner decorations --}}
                <div class="absolute top-2 left-2 w-6 h-6 border-t-4 border-l-4 border-primary rounded-tl-lg"></div>
                <div class="absolute top-2 right-2 w-6 h-6 border-t-4 border-r-4 border-primary rounded-tr-lg"></div>
                <div class="absolute bottom-2 left-2 w-6 h-6 border-b-4 border-l-4 border-primary rounded-bl-lg"></div>
                <div class="absolute bottom-2 right-2 w-6 h-6 border-b-4 border-r-4 border-primary rounded-br-lg"></div>

                {{-- QR Code with white background --}}
                <div class="bg-white p-4 rounded-xl shadow-inner">
                    <img src="{{ $qrCodeDataUri }}"
                         alt="Borrow QR code for {{ $selectedPaperTitle }}"
                         class="w-64 h-64"
                         style="image-rendering: -moz-crisp-edges; image-rendering: -webkit-crisp-edges; image-rendering: pixelated;"/>
                </div>
            </div>

            {{-- Instructions --}}
            <div class="alert alert-info text-sm">
                <x-mary-icon name="o-information-circle" class="w-5 h-5"/>
                <span>Show this QR code to the librarian to borrow this paper.</span>
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Close" wire:click="closeQrModal" class="btn-ghost"/>
        </x-slot:actions>
    @endif
</x-mary-modal>
</div>
