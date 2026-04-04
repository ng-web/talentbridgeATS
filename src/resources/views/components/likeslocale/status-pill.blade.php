@props([
    'tone' => 'neutral',
])

@php
    $classes = match($tone) {
        'brand' => 'bg-violet-100 text-violet-700 border border-violet-200',
        'success' => 'bg-green-100 text-green-700 border border-green-200',
        'warning' => 'bg-amber-100 text-amber-700 border border-amber-200',
        'danger' => 'bg-red-100 text-red-700 border border-red-200',
        'neutral' => 'bg-gray-100 text-gray-600 border border-gray-200',
        'info' => 'bg-sky-100 text-sky-700 border border-sky-200',
        default => 'bg-gray-100 text-gray-600 border border-gray-200',
    };
@endphp

<span {{ $attributes->merge([
    'class' => "inline-flex items-center justify-center rounded-full px-3 py-1.5 text-xs font-medium leading-none whitespace-nowrap {$classes}"
]) }}>
    {{ $slot }}
</span>