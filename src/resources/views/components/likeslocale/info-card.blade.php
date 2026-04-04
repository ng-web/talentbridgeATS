@props([
    'title',
    'bg' => '#efe8fb',
    'border' => '#d8caee',
    'iconBg' => 'rgba(111,76,178,0.14)',
    'iconColor' => '#6f4cb2',
])

<div class="rounded-3xl p-8 shadow border" style="background:{{ $bg }}; border-color:{{ $border }};">
    <div class="flex items-start gap-4">
        @if (trim($icon ?? '') !== '')
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0" style="background:{{ $iconBg }}; color:{{ $iconColor }};">
                {{ $icon }}
            </div>
        @endif

        <div class="flex-1">
            <h3 class="text-xl font-semibold text-gray-900">{{ $title }}</h3>
            <div class="mt-3 text-sm text-gray-700">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>