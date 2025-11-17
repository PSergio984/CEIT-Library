{{-- Academic Paper Directory - Livewire + Alpine.js + DaisyUI --}}
<x-slot name="header">
    <h2 class="font-semibold text-xl text-base-content leading-tight">
        {{ __('Academic Paper Directory') }}
    </h2>
</x-slot>

<div>
    <div class="p-6">

        {{-- Header Actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
            @if ($this->hasAdminAccess)
                <button x-data="{ loading: false }"
                    @click="
                    loading = true;
                    $wire.create().finally(() => loading = false)
                "
                    :disabled="loading" class="btn btn-primary gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-5 h-5" x-show="!loading">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span x-show="!loading">Create Academic Paper</span>
                    <span x-show="loading" class="loading loading-spinner loading-sm"></span>
                    <span x-show="loading">Loading...</span>
                </button>
            @else
                <div class="text-sm text-base-content/70">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-5 h-5 inline-block mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    Only administrators can create, edit, or delete academic papers
                </div>
            @endif
        </div>

        {{-- Search and Filters Component --}}
        <x-academic-paper-filters :availableYears="$this->availableYears" :availablePaperTypes="$this->availablePaperTypes" :availableDepartments="$this->availableDepartments" />

        <div class="mb-4 text-xs sm:text-sm text-base-content/70">
            Showing {{ $this->academicPapers->count() }} of {{ $this->academicPapers->total() }} results
        </div>

        {{-- Mobile/Tablet Card View (for screens smaller than 1280px) --}}
        <div class="block xl:hidden space-y-4">
            @forelse ($this->academicPapers as $paper)
                <div wire:key="mobile-paper-{{ $paper->id }}"
                    class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span
                                    class="badge badge-sm {{ $paper->status === 'Available' ? 'badge-success' : 'badge-error' }}">
                                    {{ $paper->status }}
                                </span>
                                <span class="badge badge-sm badge-outline">{{ $paper->catalog_code }}</span>
                            </div>
                            <h3 class="font-semibold text-sm sm:text-base line-clamp-2 break-words">{{ $paper->title }}
                            </h3>
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

                    <div class="flex flex-wrap gap-2 mt-4 pt-3 border-t border-base-300">
                        <button x-data="{ loading: false }"
                            @click="
                            loading = true;
                            $wire.showPaperDetails({{ $paper->id }}).finally(() => loading = false)
                        "
                            :disabled="loading" class="btn btn-xs sm:btn-sm btn-ghost flex-1 min-w-[80px]">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-4 h-4" x-show="!loading">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span x-show="!loading">View</span>
                            <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                        </button>
                        @if ($this->hasAdminAccess)
                            <button x-data="{ loading: false }"
                                @click="
                                loading = true;
                                $wire.edit({{ $paper->id }}).finally(() => loading = false)
                            "
                                :disabled="loading" class="btn btn-xs sm:btn-sm btn-ghost flex-1 min-w-[80px]">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4" x-show="!loading">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                                <span x-show="!loading">Edit</span>
                                <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                            </button>
                            <button x-data="{ loading: false }"
                                @click="
                                    loading = true;
                                    $wire.confirmDelete({{ $paper->id }}).finally(() => loading = false)
                            "
                                :disabled="loading || {{ $paper->can_delete ? 'false' : 'true' }}"
                                class="btn btn-xs sm:btn-sm btn-ghost {{ $paper->can_delete ? 'text-error' : 'text-base-content/40' }} flex-1 min-w-[80px]"
                                @if (!$paper->can_delete)
                                title="Cannot delete - paper has borrowed copies"
                        @endif>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-4 h-4" x-show="!loading">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                        <span x-show="!loading">Delete</span>
                        <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                        </button>
            @endif
        </div>
    </div>
@empty
    <div class="text-center py-8 sm:py-12 bg-base-100 rounded-lg border border-base-300">
        <x-mary-icon name="o-document-magnifying-glass"
            class="w-12 h-12 sm:w-16 sm:h-16 mx-auto text-base-content/40 mb-4" />
        <h3 class="text-base sm:text-lg font-medium text-base-content mb-2 px-4">No Academic Papers Found
        </h3>
        <p class="text-xs sm:text-sm text-base-content/70 px-4">
            @if (
                $search ||
                    $statusFilter ||
                    $departmentFilter ||
                    $paperTypeFilter ||
                    $yearFilter ||
                    $yearFromFilter ||
                    $yearToFilter)
                No papers match your current filters
            @else
                No academic papers are available at the moment
            @endif
        </p>
    </div>
    @endforelse

    {{-- Mobile/Tablet Pagination --}}
    @if ($this->academicPapers->hasPages())
        <div class="mt-6">
            {{ $this->academicPapers->links() }}
        </div>
    @endif
</div>

{{-- Desktop Table View (for screens 1280px and wider) --}}
<div class="hidden xl:block">
    <div class="overflow-x-auto">
        <x-mary-table :headers="$headers" :rows="$this->academicPapers" with-pagination :sort-by="$sortBy" per-page="perPage"
            :per-page-values="[5, 10, 25, 50]" striped row-class="hover:bg-base-200" header-class="text-base-content bg-base-200">
            <x-slot:empty>
                <div class="text-center py-12">
                    <x-mary-icon name="o-document-magnifying-glass"
                        class="w-16 h-16 mx-auto text-base-content/40 mb-4" />
                    <h3 class="text-lg font-medium text-base-content mb-2">No Academic Papers Found</h3>
                    <p class="text-sm text-base-content/70">
                        @if ($search || $statusFilter || $departmentFilter || $paperTypeFilter || $yearFromFilter || $yearToFilter)
                            No papers match your current filters
                        @else
                            No academic papers are available at the moment
                        @endif
                    </p>
                </div>
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
                <div class="flex items-center gap-2">
                    <button x-data="{ loading: false }"
                        @click="
                        loading = true;
                        $wire.showPaperDetails({{ $row->id }}).finally(() => loading = false)
                    "
                        :disabled="loading" class="btn btn-sm btn-ghost tooltip" data-tip="View Details">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-4 h-4" x-show="!loading">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                    </button>

                    @if ($this->hasAdminAccess)
                        <button x-data="{ loading: false }"
                            @click="
                            loading = true;
                            $wire.edit({{ $row->id }}).finally(() => loading = false)
                        "
                            :disabled="loading" class="btn btn-sm btn-ghost tooltip" data-tip="Edit">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-4 h-4" x-show="!loading">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                            </svg>
                            <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                        </button>

                        <button x-data="{ loading: false }"
                            @click="
                            @if ($row->can_delete) loading = true;
                                $wire.confirmDelete({{ $row->id }}).finally(() => loading = false) @endif
                        "
                            :disabled="loading || {{ $row->can_delete ? 'false' : 'true' }}"
                            class="btn btn-sm btn-ghost {{ $row->can_delete ? 'text-error' : 'text-base-content/40' }} tooltip"
                            data-tip="{{ $row->can_delete ? 'Delete' : 'Cannot delete - has borrowed copies' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor" class="w-4 h-4" x-show="!loading">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                            <span x-show="loading" class="loading loading-spinner loading-xs"></span>
                        </button>
                    @endif
                </div>
            @endscope
        </x-mary-table>
    </div>
</div>

</div>{{-- Close p-6 div --}}

{{-- Alpine.js State Management for Modals --}}
<div x-data="{
    showDeleteModal: false,
    showPaperModal: false,
    showCopyDeleteModal: false
}" @delete-modal.window="showDeleteModal = true"
    @paper-modal.window="showPaperModal = true" @copy-delete-modal.window="showCopyDeleteModal = true">

    {{-- Modals --}}
    <x-admin.delete-academic-paper-modal :deleteId="$deleteId" />
    <x-admin.paper-details-modal :selectedPaper="$this->selectedPaper" :isAdmin="$this->hasAdminAccess" />
    <x-admin.delete-copy-modal :copyToDelete="$copyToDelete" />
</div>
{{-- Create/Edit Academic Paper Drawer --}}
<x-admin.academic-paper-form-drawer :formDrawer="$formDrawer" :isEditing="$isEditing" :form="$form" />
</div>{{-- Close root div --}}
