@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
])

@php
    $variantClasses = [
        'primary'  => 'll-btn ll-btn-primary',
        'accent'   => 'll-btn ll-btn-accent',
        'secondary'=> 'll-btn ll-btn-secondary',
        'slate'    => 'll-btn ll-btn-slate',
        'lavender' => 'll-btn ll-btn-lavender',
        'outline'  => 'll-btn ll-btn-outline',
        'soft'     => 'll-btn ll-btn-soft',
        'warning'  => 'll-btn ll-btn-warning',
        'success'  => 'll-btn ll-btn-success',
        'info'     => 'll-btn ll-btn-info',
    ];

    $classes = $variantClasses[$variant] ?? $variantClasses['primary'];
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif