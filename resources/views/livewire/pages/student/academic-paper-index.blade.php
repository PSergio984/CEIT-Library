{{-- resources/views/livewire/academic-paper-index.blade.php --}}
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
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-base-content mb-2">Library Academic Paper Collection</h3>
                            <p class="text-sm text-base-content/70">Browse and access Academic Paper documents from the
                                CEIT Library</p>
                        </div>
                        <div class="flex gap-2 items-center">
                            <x-mary-input label="Search Title Here" wire:model.live.debounce="search"
                                placeholder="Search Title Here" inline icon="o-magnifying-glass" clearable />
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <x-mary-table :headers="$headers" :rows="$this->academicPapers" with-pagination :sort-by="$sortBy"
                            :per-page="$perPage" :per-page-values="[5, 10, 25, 50]"
                            row-class="text-base-content hover:bg-base-100 hover:text-base-content transition-all duration-150 border-b border-base-200 last:border-b-0"
                            header-class="text-base-content bg-gradient-to-r from-base-200 to-base-300 font-semibold border-b-2 border-base-300"
                            class="table-enhanced rounded-lg shadow-lg overflow-hidden">
                            <x-slot:empty>
                                <div class="text-center py-8">
                                    <x-mary-icon name="o-document-magnifying-glass"
                                        class="w-16 h-16 mx-auto text-base-content/40 mb-4" />
                                    <h3 class="text-lg font-medium text-base-content mb-2">No Academic Papers Found</h3>
                                    <p class="text-sm text-base-content/70">
                                        @if ($search)
                                            There's no academic paper matching your query "{{ $search }}"
                                        @else
                                            No academic papers are available at the moment
                                        @endif
                                    </p>
                                </div>
                            </x-slot:empty>
                            @scope('cell_status', $row)
                                <x-mary-badge :value="$row->status"
                                    class="badge-outline {{ $row->status === 'Available' ? 'badge-success' : 'badge-error' }}" />
                            @endscope

                            @scope('actions', $row)
                                <x-mary-button icon="o-eye" class="btn-sm btn-primary"
                                    wire:click="showPaperDetails({{ $row->id }})" tooltip="View Details">
                                    View
                                </x-mary-button>
                            @endscope
                        </x-mary-table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Academic Paper Details - Shared Component -->
    <x-mary-modal wire:model="showModal" title="" class="backdrop-blur" box-class="w-11/12 max-w-5xl">
        <x-academic-paper-detail-modal :selectedPaper="$selectedPaper" :isAdmin="false" />
    </x-mary-modal>

    <!-- QR Code Modal -->
    <x-mary-modal wire:model="showQrModal" title="QR Code for Borrowing" box-class="max-w-md w-full">
        @if ($this->selectedCopy && $qrCode)
            <div class="space-y-6">
                <!-- QR Code Display -->
                <div class="flex flex-col items-center justify-center p-6 bg-base-200 rounded-lg">
                    <div class="bg-white p-4 rounded-lg shadow-lg">
                        <img src="data:image/svg+xml;base64,{{ $qrCode }}" alt="QR Code" class="w-64 h-64">
                    </div>
                </div>

                <!-- Copy Information -->
                <div class="space-y-2 text-center">
                    <h4 class="font-semibold text-lg">{{ $this->selectedCopy->academicPaper->title }}</h4>
                    <p class="text-sm text-base-content/70">
                        <span class="font-semibold">Copy ID:</span> {{ $this->selectedCopy->id }}
                    </p>
                    <p class="text-sm text-base-content/70">
                        <span class="font-semibold">Catalog Code:</span>
                        {{ $this->selectedCopy->academicPaper->catalog_code }}
                    </p>
                </div>

                <!-- Instructions -->
                <div class="alert alert-info">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        class="stroke-current shrink-0 w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm">Present this QR code to the librarian to borrow this academic paper.</span>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-2 justify-center">
                    <x-mary-button label="Download QR" icon="o-arrow-down-tray" class="btn-primary"
                        wire:click="downloadQr" />
                    <x-mary-button label="Close" class="btn-ghost" wire:click="closeQrModal" />
                </div>
            </div>
        @endif
    </x-mary-modal>
</div>
