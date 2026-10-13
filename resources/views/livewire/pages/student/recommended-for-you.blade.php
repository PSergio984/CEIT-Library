<div>
    @php($shelf = $this->shelf)

    @if($shelf['state'] === 'unavailable')
        <x-mary-card title="Recommended for you" class="shadow-lg">
            <div class="alert alert-warning text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <span>Recommendations unavailable right now</span>
            </div>
        </x-mary-card>
    @elseif($shelf['state'] === 'empty')
        <x-mary-card title="Recommended for you" class="shadow-lg">
            <x-empty-state
                icon="o-document-magnifying-glass"
                title="No recommendations yet"
                message="Check back after borrowing a paper, or browse the catalog."
                :show-action="false"
                size="sm"
            />
        </x-mary-card>
    @else
        <x-mary-card :title="$shelf['state'] === 'cold_start' ? 'Popular right now' : 'Recommended for you'" class="shadow-lg">
            @if($shelf['state'] === 'cold_start')
                <p class="text-sm text-base-content/60 mb-4">The most borrowed papers lately — borrow a paper to personalise this shelf.</p>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                @foreach($shelf['papers'] as $paper)
                    <a wire:key="shelf-paper-{{ $paper->id }}" href="{{ \App\Support\CitationUrl::paper($paper->id) }}"
                       class="block p-4 bg-base-200 border border-base-300 rounded-lg hover:bg-base-300 transition-colors">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="badge badge-sm {{ (($shelf['availability'][$paper->id]['available'] ?? 0) > 0) ? 'badge-success' : 'badge-error' }}">
                                {{ (($shelf['availability'][$paper->id]['available'] ?? 0) > 0) ? 'Available' : 'Unavailable' }}
                            </span>
                            <span class="badge badge-sm badge-outline">{{ $paper->catalog_code }}</span>
                        </div>
                        <h3 class="font-semibold text-sm line-clamp-2 break-words">{{ $paper->title }}</h3>

                        <div class="grid grid-cols-2 gap-3 text-xs mt-3">
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
                                <p class="font-medium">{{ ($shelf['availability'][$paper->id]['available'] ?? 0) }} of {{ ($shelf['availability'][$paper->id]['total'] ?? 0) }} available</p>
                                <p class="text-xs text-base-content/50">Checked just now</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </x-mary-card>
    @endif
</div>
