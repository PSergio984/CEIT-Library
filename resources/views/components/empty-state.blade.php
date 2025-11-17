@props([
    'icon' => 'o-inbox',
    'title' => 'No Data Available',
    'message' => 'There is currently no data to display.',
    'actionLabel' => null,
    'actionWire' => null,
    'actionHref' => null,
    'actionClass' => 'btn-primary',
    'showAction' => true,
    'size' => 'default', // 'sm', 'default', 'lg'
])

@php
    $sizeClasses = match($size) {
        'sm' => 'py-6 sm:py-8',
        'lg' => 'py-16 sm:py-20',
        default => 'py-8 sm:py-12',
    };
    
    $iconSize = match($size) {
        'sm' => 'w-12 h-12 sm:w-14 sm:h-14',
        'lg' => 'w-20 h-20 sm:w-24 sm:h-24',
        default => 'w-16 h-16 sm:w-20 sm:h-20',
    };
    
    $titleSize = match($size) {
        'sm' => 'text-base sm:text-lg',
        'lg' => 'text-2xl sm:text-3xl',
        default => 'text-lg sm:text-xl',
    };
    
    $messageSize = match($size) {
        'sm' => 'text-xs sm:text-sm',
        'lg' => 'text-base sm:text-lg',
        default => 'text-sm sm:text-base',
    };
@endphp

{{-- Reusable Empty State Component with DaisyUI styling --}}
<div {{ $attributes->merge(['class' => "text-center {$sizeClasses} bg-base-100 rounded-lg border border-base-300"]) }}>
    {{-- Icon --}}
    <x-mary-icon :name="$icon" class="{{ $iconSize }} mx-auto text-base-content/30 mb-4" />
    
    {{-- Title --}}
    <h3 class="{{ $titleSize }} font-semibold text-base-content mb-2 px-4">
        {{ $title }}
    </h3>
    
    {{-- Message --}}
    <p class="{{ $messageSize }} text-base-content/70 px-4 max-w-md mx-auto">
        {{ $message }}
    </p>
    
    {{-- Optional Action Button --}}
    @if($showAction && ($actionLabel || (isset($slot) && $slot->isNotEmpty())))
        <div class="mt-6">
            @if(isset($slot) && $slot->isNotEmpty())
                {{ $slot }}
            @elseif($actionWire)
                <button 
                    wire:click="{{ $actionWire }}"
                    wire:loading.attr="disabled"
                    wire:target="{{ $actionWire }}"
                    class="btn {{ $actionClass }} gap-2">
                    <span wire:loading.remove wire:target="{{ $actionWire }}">
                        {{ $actionLabel }}
                    </span>
                    <span wire:loading wire:target="{{ $actionWire }}" class="loading loading-spinner loading-sm"></span>
                </button>
            @elseif($actionHref)
                <a href="{{ $actionHref }}" class="btn {{ $actionClass }} gap-2">
                    {{ $actionLabel }}
                </a>
            @endif
        </div>
    @endif
</div>
