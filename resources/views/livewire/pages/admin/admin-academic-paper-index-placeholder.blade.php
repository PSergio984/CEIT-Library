{{-- Placeholder shown during lazy loading --}}
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="h-10 bg-base-200 rounded-xl w-64 animate-pulse"></div>
        <div class="h-10 bg-base-200 rounded-xl w-32 animate-pulse"></div>
    </div>
    <x-table-skeleton rows="10" cols="6" />
</div>
