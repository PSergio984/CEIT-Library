{{-- Placeholder shown during lazy loading --}}
<div class="p-6 space-y-6">
    {{-- Header Section Skeleton --}}
    <div class="space-y-2">
        <x-skeleton height="h-8" width="w-1/3" />
        <x-skeleton height="h-4" width="w-1/2" />
    </div>

    {{-- Filters Skeleton --}}
    <div class="bg-base-200 rounded-lg p-4">
        <div class="flex justify-between mb-4">
            <x-skeleton height="h-6" width="w-24" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <x-skeleton height="h-10" rows="1" />
            <x-skeleton height="h-10" rows="1" />
            <x-skeleton height="h-10" rows="1" />
            <x-skeleton height="h-10" rows="1" />
            <x-skeleton height="h-10" rows="1" />
        </div>
    </div>

    {{-- Content Skeleton (Table/Cards) --}}
    <div class="space-y-4">
        <x-skeleton height="h-16" rows="5" />
    </div>
</div>
