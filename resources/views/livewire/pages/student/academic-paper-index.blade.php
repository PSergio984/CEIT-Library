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
                            <p class="text-sm text-base-content/70">Browse and access Academic Paper documents from the CEIT Library</p>
                        </div>
                        <div class="flex gap-2 items-center">
                            <x-mary-input label="Search Title Here" wire:model.live.debounce="search" placeholder="Search Title Here" inline icon="o-magnifying-glass" clearable />
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                      <x-mary-table
                        :headers="$headers"
                        :rows="$this->academicPapers"
                        with-pagination
                        :sort-by="$sortBy"
                        :per-page="$perPage"
                        :per-page-values="[5, 10, 25, 50]"
                        row-class="text-base-content hover:bg-base-100 hover:text-base-content transition-all duration-150 border-b border-base-200 last:border-b-0"
                        header-class="text-base-content bg-gradient-to-r from-base-200 to-base-300 font-semibold border-b-2 border-base-300"
                        class="table-enhanced rounded-lg shadow-lg overflow-hidden"
                      >
                        <x-slot:empty>
                          <div class="text-center py-8">
                            <x-mary-icon name="o-document-magnifying-glass" class="w-16 h-16 mx-auto text-base-content/40 mb-4" />
                            <h3 class="text-lg font-medium text-base-content mb-2">No Academic Papers Found</h3>
                            <p class="text-sm text-base-content/70">
                              @if($search)
                                There's no academic paper matching your query "{{ $search }}"
                              @else
                                No academic papers are available at the moment
                              @endif
                            </p>
                          </div>
                        </x-slot:empty>
                          @scope('cell_status', $row)
                          <x-mary-badge
                              :value="$row->status"
                              class="badge-outline {{ $row->status === 'Available' ? 'badge-success' : 'badge-error' }}"
                          />
                          @endscope

                          @scope('actions', $row)
                          <x-mary-button
                              icon="o-eye"
                              class="btn-sm btn-primary"
                              wire:click="showPaperDetails({{ $row->id }})"
                              tooltip="View Details"
                          >
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
        @if($selectedPaper)
            <div class="space-y-6">
                <!-- Title Section -->
                <div class="flex flex-col sm:flex-row items-start justify-between gap-4">
                    <h3 class="text-lg sm:text-xl font-bold flex-1 pr-4">{{ $selectedPaper->title }}</h3>
                    <div class="flex items-center gap-3">
                        @if($this->departmentIcon)
                            <img src="{{ $this->departmentIcon }}" alt="{{ $selectedPaper->department }} Logo"
                                 class="w-20 h-20 sm:w-24 sm:h-24 object-contain">
                        @endif
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                    <div class="space-y-2 sm:space-y-3">
                        <div>
                            <span class="font-semibold">Catalog Code:</span> {{ $selectedPaper->catalog_code }}
                        </div>
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

                    <div class="space-y-2 sm:space-y-3">
                        <div>
                            <span class="font-semibold">Adviser:</span> {{ $selectedPaper->research_project_adviser }}
                        </div>
                        <div>
                            <span class="font-semibold">Year:</span> {{ $selectedPaper->publication_year }}
                        </div>
                    </div>
                </div>

                <!-- Copies Table -->
                @if($selectedPaper->copies->count() > 0)
                    <div class="overflow-x-auto -mx-2 sm:mx-0">
                        <table class="table table-sm w-full text-sm sm:text-base border-collapse border border-base-300 rounded-lg overflow-hidden shadow-sm">
                            <thead>
                            <tr class="bg-base-200">
                                <th class="border-b border-base-300 px-4 py-3 text-left font-semibold text-base-content">Copy Id</th>
                                <th class="border-b border-base-300 px-4 py-3 text-left font-semibold text-base-content">Availability</th>
                                <th class="border-b border-base-300 px-4 py-3 text-left font-semibold text-base-content">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($selectedPaper->copies as $copy)
                                <tr class="hover:bg-base-100 transition-colors duration-150 border-b border-base-200 last:border-b-0">
                                    <td class="px-4 py-3 text-base-content font-medium">{{ $copy->id }}</td>
                                    <td class="px-4 py-3">
                                            <span
                                                class="badge px-4 py-1 {{ $this->getStatusBadgeClass($copy->status) }}">
                                                {{ $copy->status }}
                                            </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($copy->status === 'Available')
                                            <x-mary-button
                                                icon="o-qr-code"
                                                class="btn-sm btn-success"
                                                wire:click="requestQr({{ $copy->id }})"
                                                tooltip="Request QR Code"
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
</div>
