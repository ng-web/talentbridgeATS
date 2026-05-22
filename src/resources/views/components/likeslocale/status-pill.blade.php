@props([
    'tone' => 'neutral',
])

@php
    $classes = match($tone) {
        'brand'   => 'bg-violet-50 text-violet-600',
        'success' => 'bg-emerald-50 text-emerald-700',
        'warning' => 'bg-amber-50 text-amber-600',
        'danger'  => 'bg-red-50 text-red-600',
        'neutral' => 'bg-gray-100 text-gray-500',
        'info'    => 'bg-sky-50 text-sky-600',
        default   => 'bg-gray-100 text-gray-500',
    };
@endphp

<span {{ $attributes->merge([
    'class' => "inline-flex items-center justify-center rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap {$classes}"
]) }}>
    {{ $slot }}
</span>