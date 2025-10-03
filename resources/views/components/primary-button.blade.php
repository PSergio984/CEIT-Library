@props(['icon' => null, 'iconPosition' => 'left'])

<button
    wire:loading.attr="disabled"
    wire:loading.class="opacity-70 cursor-not-allowed"
    {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-6 py-3 bg-[#273F4F] hover:bg-[#1e2f3a] text-white rounded-lg font-semibold text-sm sm:text-base border border-transparent shadow-md focus:outline-none focus:ring-2 focus:ring-[#273F4F] focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-70 disabled:cursor-not-allowed disabled:bg-[#1a2d38]']) }}>

    <span wire:loading class="loading loading-spinner loading-sm mr-2"></span>

    @if($icon && $iconPosition === 'left')
        <span wire:loading.remove>
            <x-mary-icon :name="$icon" class="w-5 h-5 mr-2"/>
        </span>
    @endif

    <span wire:loading.remove>{{ $slot }}</span>
    <span wire:loading class="text-sm">Processing...</span>

    @if($icon && $iconPosition === 'right')
        <span wire:loading.remove>
            <x-mary-icon :name="$icon" class="w-5 h-5 ml-2"/>
        </span>
    @endif
</button>
