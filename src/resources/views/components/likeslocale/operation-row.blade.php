<div
    x-data="{ hovered: false }"
    @mouseenter="hovered = true"
    @mouseleave="hovered = false"
    class="rounded-2xl border border-gray-200 bg-white px-4 py-4 xl:px-5 xl:py-5 transition-all duration-200 ease-out"
    :style="hovered
        ? 'box-shadow: 0 4px 12px rgba(0,0,0,0.08), 0 10px 24px rgba(0,0,0,0.08); transform: translateY(-1px); border-color: #d1d5db;'
        : 'box-shadow: 0 1px 2px rgba(0,0,0,0.05), 0 4px 12px rgba(0,0,0,0.06);'"
>
    {{ $slot }}
</div>
