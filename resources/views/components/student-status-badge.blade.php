@props(['status'])

@php
    $color = $status === 'Available' ? 'success' : 'error';
@endphp
<span {{ $attributes->merge(['class' => "badge badge-{$color}"]) }}>
    {{ $status }}
</span>
