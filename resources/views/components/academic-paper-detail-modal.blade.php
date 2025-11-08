@php
    // Get department icon from config
    $departmentIcon = '';
    if ($selectedPaper && $selectedPaper->department) {
        $icons = config('departments.icons', []);
        $departmentIcon = isset($icons[$selectedPaper->department]) 
            ? asset($icons[$selectedPaper->department]) 
            : '';
    }
@endphp

@if($selectedPaper)
    <div class="space-y-8">
        {{-- Header Section with Enhanced Design --}}
        <div class="bg-gradient-to-r from-primary/10 to-secondary/10 -mx-6 -mt-6 px-6 pt-6 pb-8 rounded-t-xl border-b-2 border-primary/20">
            <div class="flex flex-col sm:flex-row items-start justify-between gap-4">
                <div class="flex-1">
                    <h3 class="text-2xl sm:text-3xl font-bold text-base-content leading-tight mb-2">
                        {{ $selectedPaper->title }}
                    </h3>
                    <div class="flex flex-wrap gap-2 mt-3">
                        <span class="badge badge-primary badge-lg">{{ $selectedPaper->catalog_code }}</span>
                        <span class="badge badge-ghost badge-lg">{{ $selectedPaper->publication_year }}</span>
                        <span class="badge badge-outline badge-lg">{{ $selectedPaper->paper_type }}</span>
                    </div>
                </div>
                @if($departmentIcon)
                    <div class="flex-shrink-0">
                        <img src="{{ $departmentIcon }}" alt="{{ $selectedPaper->department }} Logo"
                            class="w-20 h-20 sm:w-24 sm:h-24 object-contain">
                    </div>
                @endif
            </div>
        </div>

        {{-- Information Cards Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Left Card: Project Details --}}
            <div class="card bg-base-200/50 shadow-md hover:shadow-lg transition-shadow duration-300">
                <div class="card-body p-6">
                    <h4 class="card-title text-lg mb-4 text-primary flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Project Details
                    </h4>
                    <div class="space-y-4">
                        <div class="flex flex-col">
                            <span class="text-xs text-base-content/60 uppercase tracking-wide mb-1">Department</span>
                            <span class="text-base font-medium">{{ $selectedPaper->department }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs text-base-content/60 uppercase tracking-wide mb-1">Paper Type</span>
                            <span class="text-base font-medium">{{ $selectedPaper->paper_type }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs text-base-content/60 uppercase tracking-wide mb-1">Research Adviser</span>
                            <span class="text-base font-medium">{{ $selectedPaper->researchAdviser?->name ?? 'N/A' }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs text-base-content/60 uppercase tracking-wide mb-1">Technical Adviser</span>
                            <span class="text-base font-medium">{{ $selectedPaper->technicalAdviser?->name ?? 'N/A' }}</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs text-base-content/60 uppercase tracking-wide mb-1">Dean</span>
                            <span class="text-base font-medium">{{ $selectedPaper->dean?->name ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Card: Research Team --}}
            <div class="card bg-base-200/50 shadow-md hover:shadow-lg transition-shadow duration-300">
                <div class="card-body p-6">
                    <h4 class="card-title text-lg mb-4 text-secondary flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Research Team
                    </h4>
                    <div class="flex flex-col">
                        <span class="text-xs text-base-content/60 uppercase tracking-wide mb-2">Team Members</span>
                        <div class="flex flex-wrap gap-2">
                            @forelse($selectedPaper->authors as $author)
                                <span class="badge badge-outline badge-lg">{{ $author->name }}</span>
                            @empty
                                <span class="text-sm text-base-content/60">No authors listed</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Copies Section with Enhanced Table --}}
        @if($selectedPaper->copies->count() > 0)
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
                            @foreach($selectedPaper->copies as $copy)
                                <tr class="hover:bg-base-200/50 transition-all duration-200 border-b border-base-200 last:border-b-0">
                                    <td class="px-6 py-4">
                                        <span class="font-mono font-semibold text-primary">{{ $copy->id }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="badge badge-lg {{ $getStatusBadgeClass($copy->status) }} gap-2">
                                            @if($copy->status === 'Available')
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
                                        @if($isAdmin)
                                            {{-- Admin Actions: Delete Copy --}}
                                            @if($copy->status === 'Available')
                                                <x-mary-button 
                                                    icon="o-trash"
                                                    class="btn-sm btn-error gap-2 shadow-sm hover:shadow-md transition-shadow"
                                                    wire:click="confirmCopyDelete({{ $copy->id }})"
                                                    tooltip="Delete Copy">
                                                    Delete Copy
                                                </x-mary-button>
                                            @else
                                                <div class="flex items-center gap-2 text-base-content/60">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                    </svg>
                                                    <span class="text-sm font-semibold">Cannot Delete</span>
                                                </div>
                                            @endif
                                        @else
                                            {{-- Student Actions: Request QR --}}
                                            @if($copy->status === 'Available')
                                                <x-mary-button 
                                                    icon="o-qr-code"
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
