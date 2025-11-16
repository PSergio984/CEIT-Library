@props([
    'message' => 'Loading...',
    'subtext' => 'Please wait while we fetch the data',
    'minHeight' => '60vh'
])

{{-- Reusable Loading Placeholder Component --}}
<div class="p-6">
    <div class="flex flex-col items-center justify-center" style="min-height: {{ $minHeight }}">
        <div class="flex flex-col items-center gap-4">
            <span class="loading loading-spinner loading-lg text-primary" role="status" aria-label="Loading content"></span>            <p class="text-base-content font-medium">{{ $message }}</p>
            @if($subtext)
                <p class="text-sm text-base-content/60">{{ $subtext }}</p>
            @endif
        </div>
    </div>
</div>
