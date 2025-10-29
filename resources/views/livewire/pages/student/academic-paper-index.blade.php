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

    <!-- Modal for Academic Paper Details -->
    <x-mary-modal wire:model="showModal" title="" box-class="max-w-5xl w-full">
        @if ($selectedPaper)
            <div class="space-y-8">
                <!-- Header Section with Enhanced Design -->
                <div
                    class="bg-gradient-to-r from-primary/10 to-secondary/10 -mx-6 -mt-6 px-6 pt-6 pb-8 rounded-t-xl border-b-2 border-primary/20">
                    <div class="flex flex-col sm:flex-row items-start justify-between gap-4">
                        <div class="flex-1">
                            <h3 class="text-2xl sm:text-3xl font-bold text-base-content leading-tight mb-2">
                                {{ $selectedPaper->title }}
                            </h3>
                            <div class="flex flex-wrap gap-2 mt-3">
                                <span class="badge badge-primary badge-lg">{{ $selectedPaper->catalog_code }}</span>
                                <span class="badge badge-ghost badge-lg">{{ $selectedPaper->publication_year }}</span>
                            </div>
                        </div>
                        @if ($this->departmentIcon())
                            <div class="flex-shrink-0">
                                <img src="{{ $this->departmentIcon() }}" alt="{{ $selectedPaper->department }} Logo"
                                    class="w-20 h-20 sm:w-24 sm:h-24 object-contain">
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Information Cards Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Left Card -->
                    <div class="card bg-base-200/50 shadow-md hover:shadow-lg transition-shadow duration-300">
                        <div class="card-body p-6">
                            <h4 class="card-title text-lg mb-4 text-primary flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Project Details
                            </h4>
                            <div class="space-y-4">
                                <div class="flex flex-col">
                                    <span
                                        class="text-xs uppercase tracking-wide text-base-content/60 font-semibold mb-1">Department</span>
                                    <span class="text-base font-medium">{{ $selectedPaper->department }}</span>
                                </div>
                                <div class="flex flex-col">
                                    <span
                                        class="text-xs uppercase tracking-wide text-base-content/60 font-semibold mb-1">Research
                                        Adviser</span>
                                    <span
                                        class="text-base font-medium">{{ $selectedPaper->research_project_adviser }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Card -->
                    <div class="card bg-base-200/50 shadow-md hover:shadow-lg transition-shadow duration-300">
                        <div class="card-body p-6">
                            <h4 class="card-title text-lg mb-4 text-secondary flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                Research Team
                            </h4>
                            <div class="flex flex-col">
                                <span
                                    class="text-xs uppercase tracking-wide text-base-content/60 font-semibold mb-2">Team
                                    Members</span>
                                <div class="flex flex-wrap gap-2">
                                    @forelse($selectedPaper->authors as $author)
                                        <span class="badge badge-outline badge-lg">{{ $author->name }}</span>
                                    @empty
                                        <span class="text-base-content/60 italic">No authors listed</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Copies Section with Enhanced Table -->
                @if ($selectedPaper->copies->count() > 0)
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <h4 class="text-xl font-bold text-base-content">Available Copies</h4>
                            <div class="badge badge-neutral">{{ $selectedPaper->copies->count() }}
                                {{ Str::plural('copy', $selectedPaper->copies->count()) }}</div>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-base-300 shadow-md">
                            <table class="table w-full text-sm sm:text-base">
                                <thead>
                                    <tr class="bg-base-300">
                                        <th class="px-6 py-4 text-left font-bold text-base-content">
                                            <div class="flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                                </svg>
                                                Copy ID
                                            </div>
                                        </th>
                                        <th class="px-6 py-4 text-left font-bold text-base-content">
                                            <div class="flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Status
                                            </div>
                                        </th>
                                        <th class="px-6 py-4 text-left font-bold text-base-content">
                                            <div class="flex items-center gap-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                </svg>
                                                Action
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($selectedPaper->copies as $copy)
                                        <tr
                                            class="hover:bg-base-200/50 transition-all duration-200 border-b border-base-200 last:border-b-0">
                                            <td class="px-6 py-4">
                                                <span
                                                    class="font-mono font-semibold text-primary">{{ $copy->id }}</span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span
                                                    class="badge badge-lg {{ $this->getStatusBadgeClass($copy->status) }} gap-2">
                                                    @if ($copy->status === 'Available')
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
                                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    @else
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
                                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    @endif
                                                    {{ $copy->status }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                @if ($copy->status === 'Available')
                                                    <x-mary-button icon="o-qr-code"
                                                        class="btn-sm btn-success gap-2 shadow-sm hover:shadow-md transition-shadow"
                                                        wire:click="requestQr({{ $copy->id }})"
                                                        tooltip="Request QR Code">
                                                        Request QR
                                                    </x-mary-button>
                                                @else
                                                    <div class="flex items-center gap-2 text-error">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                        </svg>
                                                        <span class="text-sm font-semibold">Not Available</span>
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif
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
