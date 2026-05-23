@props([
    'class' => '',
    'rows' => 1,
    'width' => 'w-full',
    'height' => 'h-4',
    'rounded' => 'rounded',
])

<div {{ $attributes->merge(['class' => 'animate-pulse space-y-3 ' . $class]) }}>
    @for ($i = 0; $i < $rows; $i++)
        <div class="bg-base-300 {{ $width }} {{ $height }} {{ $rounded }}"></div>
    @endfor
</div>
