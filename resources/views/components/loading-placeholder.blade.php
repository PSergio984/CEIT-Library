@props([
    'message' => 'Loading...',
    'subtext' => 'Please wait while we fetch the data',
    'minHeight' => '60vh',
    'size' => 'lg', // 'sm', 'md', 'lg'
])

@php
    $spinnerSize = match($size) {
        'sm' => 'loading-sm',
        'md' => 'loading-md',
        default => 'loading-lg',
    };
@endphp

{{-- Reusable Loading Placeholder Component with DaisyUI --}}
<div {{ $attributes->merge(['class' => 'p-6 bg-base-100']) }}>
    <div class="flex flex-col items-center justify-center" style="min-height: {{ $minHeight }}">
        <div class="flex flex-col items-center gap-4" role="status" aria-live="polite">
            {{-- Loading Spinner --}}
            <span class="loading loading-ring {{ $spinnerSize }} text-primary" aria-label="Loading content"></span>
            
            {{-- Message --}}
            <p class="text-base-content font-semibold text-lg">{{ $message }}</p>
            
            {{-- Subtext --}}
            @if($subtext)
                <p class="text-sm text-base-content/60 max-w-md text-center">{{ $subtext }}</p>
            @endif
            
            {{-- Optional Slot for Additional Content --}}
            @isset($slot)
                @if($slot->isNotEmpty())
                    <div class="mt-2">
                        {{ $slot }}
                    </div>
                @endif
            @endisset
        </div>
    </div>
</div>
