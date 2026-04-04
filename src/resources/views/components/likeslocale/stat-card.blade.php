@props([
    'title',
    'value',
    'description' => null,
    'bg' => '#ffffff',
    'border' => '#e5e7eb',
    'valueColor' => '#111827',
    'titleColor' => '#374151',
    'chartColor' => 'rgba(107,114,128,0.28)',
    'activityLabel' => 'Activity',
])

<div
    x-data="{ loaded: false }"
    x-init="setTimeout(() => loaded = true, 60)"
    class="relative rounded-3xl p-8 shadow border overflow-hidden"
    style="background:{{ $bg }}; border-color:{{ $border }};"
>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium" style="color:{{ $titleColor }};">
                {{ $title }}
            </p>

            <p class="mt-4 text-4xl font-bold leading-none" style="color:{{ $valueColor }};">
                {{ $value }}
            </p>

            @if($description)
                <p class="mt-3 text-sm leading-6 text-gray-600">
                    {{ $description }}
                </p>
            @endif
        </div>

        <div class="shrink-0 flex flex-col items-end gap-3">
            @if (trim($icon ?? '') !== '')
                <div
                    class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-sm"
                    style="background:rgba(255,255,255,0.72); color:{{ $valueColor }};"
                >
                    {{ $icon }}
                </div>
            @endif

            <div class="hidden sm:flex items-end gap-1.5 pt-1">
                <span
                    class="block w-1.5 rounded-full transition-all duration-700 ease-out"
                    :style="loaded ? 'height:12px; background:{{ $chartColor }};' : 'height:0px; background:{{ $chartColor }};'"
                ></span>
                <span
                    class="block w-1.5 rounded-full transition-all duration-700 ease-out delay-75"
                    :style="loaded ? 'height:17px; background:{{ $chartColor }};' : 'height:0px; background:{{ $chartColor }};'"
                ></span>
                <span
                    class="block w-1.5 rounded-full transition-all duration-700 ease-out delay-100"
                    :style="loaded ? 'height:22px; background:{{ $chartColor }};' : 'height:0px; background:{{ $chartColor }};'"
                ></span>
                <span
                    class="block w-1.5 rounded-full transition-all duration-700 ease-out delay-150"
                    :style="loaded ? 'height:28px; background:{{ $chartColor }};' : 'height:0px; background:{{ $chartColor }};'"
                ></span>
                <span
                    class="block w-1.5 rounded-full transition-all duration-700 ease-out delay-200"
                    :style="loaded ? 'height:34px; background:{{ $chartColor }};' : 'height:0px; background:{{ $chartColor }};'"
                ></span>
            </div>
        </div>
    </div>

    <div class="mt-6 flex items-center justify-between gap-4">
        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-400">
            {{ $activityLabel }}
        </div>

        <div class="flex items-end gap-1.5 sm:hidden">
            <span
                class="block w-1.5 rounded-full transition-all duration-700 ease-out"
                :style="loaded ? 'height:10px; background:{{ $chartColor }};' : 'height:0px; background:{{ $chartColor }};'"
            ></span>
            <span
                class="block w-1.5 rounded-full transition-all duration-700 ease-out delay-75"
                :style="loaded ? 'height:14px; background:{{ $chartColor }};' : 'height:0px; background:{{ $chartColor }};'"
            ></span>
            <span
                class="block w-1.5 rounded-full transition-all duration-700 ease-out delay-100"
                :style="loaded ? 'height:18px; background:{{ $chartColor }};' : 'height:0px; background:{{ $chartColor }};'"
            ></span>
            <span
                class="block w-1.5 rounded-full transition-all duration-700 ease-out delay-150"
                :style="loaded ? 'height:24px; background:{{ $chartColor }};' : 'height:0px; background:{{ $chartColor }};'"
            ></span>
            <span
                class="block w-1.5 rounded-full transition-all duration-700 ease-out delay-200"
                :style="loaded ? 'height:30px; background:{{ $chartColor }};' : 'height:0px; background:{{ $chartColor }};'"
            ></span>
        </div>
    </div>
</div>