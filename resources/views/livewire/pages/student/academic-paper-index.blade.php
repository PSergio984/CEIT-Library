{{-- resources/views/livewire/academic-paper-index.blade.php --}}
<x-slot name="header">
    <h2 class="font-semibold text-xl text-base-content leading-tight">
        {{ __("Academic Paper Directory") }}
    </h2>
</x-slot>

<div>
    {{-- Full Page Loading Spinner - Shows ONLY during initial navigation (academicPapers change) --}}
    <div wire:loading.flex wire:target="academicPapers" 
        class="fixed inset-0 left-64 bg-base-100 z-50 items-center justify-center">
        <div class="flex flex-col items-center gap-4">
            <span class="loading loading-spinner loading-lg text-primary"></span>
            <p class="text-base-content font-medium">Loading academic papers...</p>
        </div>
    </div>

    {{-- Main Content - Only hidden during navigation, subtle opacity for filters --}}
    <div class="p-6" 
        wire:loading.remove 
        wire:target="academicPapers"
        wire:loading.class="opacity-50"
        wire:loading.class.remove="opacity-100">
        {{-- Header Section --}}
        <div class="mb-6">
            <h3 class="text-lg font-medium text-base-content mb-2">Library Academic Paper Collection</h3>
            <p class="text-sm text-base-content/70">Browse and access Academic Paper documents from the CEIT Library</p>
        </div>

        {{-- Search and Filters Component --}}
        <x-academic-paper-filters 
            :availableYears="$this->availableYears"
            :availablePaperTypes="$this->availablePaperTypes"
            :availableDepartments="$this->availableDepartments"
        />

        {{-- Results Summary and Per-Page Control removed: using MaryUI table's built-in paginator --}}

        {{-- Mobile/Tablet Card View (for screens smaller than 1280px) --}}
        <div class="block xl:hidden space-y-4 relative">
            {{-- Localized loading overlay for card updates (filters, pagination, per-page) --}}
            <div wire:loading.flex 
                wire:target="perPage, search, statusFilter, departmentFilter, paperTypeFilter, yearFilter, yearFromFilter, yearToFilter, clearFilters, gotoPage, nextPage, previousPage"
                class="absolute inset-0 bg-base-100/80 backdrop-blur-sm z-10 items-center justify-center rounded-lg">
                <div class="flex flex-col items-center gap-2">
                    <span class="loading loading-spinner loading-lg text-primary"></span>
                    <p class="text-base-content font-medium text-sm">Updating results...</p>
                </div>
            </div>
            
            @forelse ($this->academicPapers as $paper)
                <div wire:key="mobile-paper-{{ $paper->id }}" class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="badge badge-sm {{ $paper->status === 'Available' ? 'badge-success' : 'badge-error' }}">
                                    {{ $paper->status }}
                                </span>
                                <span class="badge badge-sm badge-outline">{{ $paper->catalog_code }}</span>
                            </div>
                            <h3 class="font-semibold text-sm sm:text-base line-clamp-2 break-words">{{ $paper->title }}</h3>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs sm:text-sm mt-3">
                        <div>
                            <p class="text-base-content/50 font-medium mb-1">Department</p>
                            <p class="font-medium break-words">{{ $paper->department }}</p>
                        </div>
                        <div>
                            <p class="text-base-content/50 font-medium mb-1">Year</p>
                            <p class="font-medium">{{ $paper->publication_year }}</p>
                        </div>
                        <div>
                            <p class="text-base-content/50 font-medium mb-1">Type</p>
                            <p class="font-medium break-words">{{ $paper->paper_type }}</p>
                        </div>
                        <div>
                            <p class="text-base-content/50 font-medium mb-1">Copies</p>
                            <p class="font-medium">{{ $paper->available_copies }} available</p>
                        </div>
                    </div>

                    <div class="flex gap-2 mt-4 pt-3 border-t border-base-300">
                        <button x-data="{ loading: false }"
                            @click="loading = true; $wire.showPaperDetails({{ $paper->id }}).finally(() => loading = false)"
                            :disabled="loading"
                            class="btn btn-sm btn-primary gap-2 flex-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" x-show="!loading">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span x-show="!loading">View Details</span>
                            <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                        </button>
                        <button 
                            x-data="{ loading: false }"
                            @click="
                                loading = true;
                                $wire.showPaperDetails({{ $paper->id }}).finally(() => loading = false)
                            "
                            :disabled="loading"
                            class="btn btn-xs sm:btn-sm btn-primary flex-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4" x-show="!loading">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span x-show="!loading">View</span>
                            <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                        </button>
                    </div>
                </div>
            @empty
                {{-- Empty State Using Reusable Component --}}
                @if($search || $statusFilter || $departmentFilter || $paperTypeFilter || $yearFromFilter || $yearToFilter)
                    <x-empty-state
                        icon="o-document-magnifying-glass"
                        title="No Academic Papers Found"
                        message="No papers match your current filters. Try adjusting your search criteria to find more results."
                        :show-action="false"
                        size="sm"
                    />
                @else
                    <x-empty-state
                        icon="o-document-text"
                        title="No Academic Papers Available"
                        message="The library collection is currently empty. Please check back later for updates."
                        :show-action="false"
                        size="sm"
                    />
                @endif
            @endforelse

            {{-- Mobile/Tablet Pagination (left-aligned) --}}
            @if($this->academicPapers->hasPages())
                <div class="mt-6 flex justify-start">
                    {{ $this->academicPapers->links() }}
                </div>
            @endif
        </div>

        {{-- Desktop Table View (for screens 1280px and wider) --}}
        <div class="hidden xl:block overflow-hidden relative">
            {{-- Localized loading overlay for table updates (filters, pagination, per-page) --}}
            <div wire:loading.flex 
                wire:target="perPage, search, statusFilter, departmentFilter, paperTypeFilter, yearFilter, yearFromFilter, yearToFilter, clearFilters, gotoPage, nextPage, previousPage"
                class="absolute inset-0 bg-base-100/80 backdrop-blur-sm z-10 items-center justify-center rounded-lg">
                <div class="flex flex-col items-center gap-2">
                    <span class="loading loading-spinner loading-lg text-primary"></span>
                    <p class="text-base-content font-medium">Updating results...</p>
                </div>
            </div>
            <div class="overflow-x-visible">
                <x-mary-table :headers="$headers" :rows="$this->academicPapers" with-pagination :sort-by="$sortBy"
                    per-page="perPage" :per-page-values="[5, 10, 25, 50]" 
                    striped
                    row-class="hover:bg-base-200"
                    header-class="text-base-content bg-base-200">
                        <x-slot:empty>
                            @if($search || $statusFilter || $yearFilter || $departmentFilter || $paperTypeFilter || $yearFromFilter || $yearToFilter)
                                <x-empty-state
                                    icon="o-document-magnifying-glass"
                                    title="No Academic Papers Found"
                                    message="No papers match your current filters. Try adjusting your search criteria."
                                    :show-action="false"
                                    size="default"
                                    class="border-0"
                                />
                            @else
                                <x-empty-state
                                    icon="o-document-text"
                                    title="No Academic Papers Available"
                                    message="The library collection is currently empty. Please check back later."
                                    :show-action="false"
                                    size="default"
                                    class="border-0"
                                />
                            @endif
                        </x-slot:empty>

                        @scope('cell_catalog_code', $row)
                        <div class="font-mono text-sm">{{ $row->catalog_code }}</div>
                        @endscope

                        @scope('cell_title', $row)
                        <div class="font-medium max-w-md">{{ $row->title }}</div>
                        @endscope

                        @scope('cell_status', $row)
                        <span class="badge {{ $row->status === 'Available' ? 'badge-success' : 'badge-error' }}">
                            {{ $row->status }}
                        </span>
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

    </div>{{-- Close p-6 div --}}

    <!-- Alpine.js Modal State Management -->
    <div 
        x-data="{
            showPaperModal: false,
            showQrModal: false,
            openModal(modal) {
                this.showPaperModal = false;
                this.showQrModal = false;
                if (modal === 'paper') this.showPaperModal = true;
                if (modal === 'qr') this.showQrModal = true;
            },
            closeAllModals() {
                this.showPaperModal = false;
                this.showQrModal = false;
            }
        }"
        @open-paper-modal.window="openModal('paper')"
        @open-qr-modal.window="openModal('qr')"
        @close-qr-modal.window="showQrModal = false"
    >
        <!-- Modal for Academic Paper Details - Shared Component -->
        <dialog 
            x-ref="paperModal"
            x-show="showPaperModal"
            @click.self="showPaperModal = false"
            @keydown.escape="showPaperModal = false"
            class="modal backdrop-blur"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-init="$watch('showPaperModal', value => { 
                if (value) { $refs.paperModal.showModal() } 
                else { $refs.paperModal.close() } 
            })">
            <div class="modal-box w-11/12 max-w-5xl"
                @click.stop>
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
            @click.self="showQrModal = false; $wire.closeQrModal()"
            class="modal backdrop-blur"
            style="z-index: 9999;"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-init="$watch('showQrModal', value => { 
                if (value) { $refs.qrModal.showModal() } 
                else { $refs.qrModal.close() } 
            })">
            <div class="modal-box max-w-md w-full"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-95"
                @click.stop>
                <form method="dialog">
                    <button @click="showQrModal = false; $wire.closeQrModal()" 
                        class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
                </form>
                
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
                    @if($this->downloadUrl)
                        <a 
                            href="{{ $this->downloadUrl }}"
                            download
                            class="btn btn-primary gap-2"
                            @click="showQrModal = false; $wire.closeQrModal()"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            <span>Download QR</span>
                        </a>
                    @endif
                    
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
