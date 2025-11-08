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
                                <button 
                                    x-data="{ loading: false }"
                                    @click="
                                        loading = true;
                                        $wire.showPaperDetails({{ $row->id }}).finally(() => loading = false)
                                    "
                                    :disabled="loading"
                                    class="btn btn-sm btn-primary gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" x-show="!loading">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span x-show="!loading">View</span>
                                    <span x-show="loading" class="loading loading-spinner loading-sm"></span>
                                    <span x-show="loading">Loading...</span>
                                </button>
                            @endscope
                        </x-mary-table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alpine.js Modal State Management -->
    <div 
        x-data="{
            showPaperModal: false,
            showQrModal: false
        }"
        @open-paper-modal.window="showPaperModal = true"
        @open-qr-modal.window="showQrModal = true"
        @close-qr-modal.window="showQrModal = false"
    >
        <!-- Modal for Academic Paper Details - Shared Component -->
        <dialog 
            x-ref="paperModal"
            x-show="showPaperModal"
            @click.self="showPaperModal = false"
            class="modal backdrop-blur"
            x-init="$watch('showPaperModal', value => { 
                if (value) { $refs.paperModal.showModal() } 
                else { $refs.paperModal.close() } 
            })">
            <div class="modal-box w-11/12 max-w-5xl">
                <form method="dialog">
                    <button @click="showPaperModal = false" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                </form>
                
                <x-academic-paper-detail-modal :selectedPaper="$this->selectedPaper" :isAdmin="false" />
                
                <div class="modal-action">
                    <button @click="showPaperModal = false" class="btn btn-primary">Close</button>
                </div>
            </div>
        </dialog>

        <!-- QR Code Modal -->
        <dialog 
            x-ref="qrModal"
            x-show="showQrModal"
            @click.self="showQrModal = false"
            class="modal backdrop-blur"
            x-init="$watch('showQrModal', value => { 
                if (value) { $refs.qrModal.showModal() } 
                else { $refs.qrModal.close() } 
            })">
            <div class="modal-box max-w-md w-full">
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
                    <button 
                        x-data="{ loading: false }"
                        @click="
                            loading = true;
                            $wire.downloadQr().finally(() => loading = false)
                        "
                        :disabled="loading"
                        class="btn btn-primary gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5" x-show="!loading">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <span x-show="!loading">Download QR</span>
                        <span x-show="loading" class="loading loading-spinner loading-sm"></span>
                    </button>
                    
                    <button 
                        @click="showQrModal = false; $wire.closeQrModal()" 
                        class="btn btn-ghost">
                        Close
                    </button>
                </div>
            </div>
        @endif
            </div>
        </dialog>
    </div>
</div>
