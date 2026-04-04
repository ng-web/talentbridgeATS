@props([
    'title',
    'percent' => 0,
    'description' => null,
    'bg' => '#efe8fb',
    'border' => '#d8caee',
    'valueColor' => '#6f4cb2',
    'titleColor' => '#6f4cb2',
    'trackColor' => 'rgba(255,255,255,0.6)',
    'fillColor' => '#6f4cb2',
])

@php
    $percentValue = max(0, min(100, (int) $percent));
    $isComplete = $percentValue >= 100;
@endphp

<div class="rounded-3xl p-8 shadow border" style="background:{{ $bg }}; border-color:{{ $border }};">
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-base font-medium" style="color:{{ $titleColor }};">
                {{ $title }}
            </p>

            <p class="mt-4 text-5xl font-bold leading-none" style="color:{{ $valueColor }};">
                {{ $percentValue }}%
            </p>

            <p class="mt-3 text-sm leading-6 {{ $isComplete ? 'text-green-700' : '' }}" style="{{ $isComplete ? '' : 'color:'.$titleColor.';' }}">
                {{ $description }}
            </p>
        </div>

        <div class="relative shrink-0">
            <svg class="h-16 w-16 -rotate-90" viewBox="0 0 42 42" aria-hidden="true">
                <circle
                    cx="21"
                    cy="21"
                    r="15.9155"
                    fill="none"
                    stroke="rgba(255,255,255,0.55)"
                    stroke-width="4"
                />
                <circle
                    cx="21"
                    cy="21"
                    r="15.9155"
                    fill="none"
                    stroke="{{ $fillColor }}"
                    stroke-width="4"
                    stroke-linecap="round"
                    stroke-dasharray="{{ $percentValue }}, 100"
                />
            </svg>

            <div class="absolute inset-0 flex items-center justify-center">
                @if($isComplete)
                    <x-heroicon-o-check class="h-5 w-5 text-green-600" />
                @else
                    <x-heroicon-o-user-circle class="h-5 w-5" style="color:{{ $valueColor }};" />
                @endif
            </div>
        </div>
    </div>

    <div class="mt-6 h-3 w-full rounded-full" style="background:{{ $trackColor }};">
        <div
            class="h-3 rounded-full transition-all duration-300"
            style="width: {{ $percentValue }}%; background:{{ $fillColor }};"
        ></div>
    </div>

    @if($isComplete)
        <div class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1 text-xs font-semibold text-green-700">
            <x-heroicon-o-check-badge class="h-4 w-4" />
            Profile Complete
        </div>
    @endif
</div>