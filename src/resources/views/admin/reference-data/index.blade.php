<x-layouts.portal :title="'Reference Data'" heading="Reference Data" subheading="Manage the dropdown lists used across job listings." portalRole="admin">
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        {{-- Countries --}}
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <h3 class="text-xl font-semibold text-gray-900">Countries</h3>
            <p class="mt-1 text-sm text-gray-500">Countries available when creating job listings.</p>

            <form method="POST" action="{{ route('admin.countries.store') }}" class="mt-5 flex gap-3">
                @csrf
                <input name="name" type="text" placeholder="e.g. Australia"
                       class="flex-1 rounded-2xl border-gray-300 shadow-sm text-sm"
                       value="{{ old('name') }}">
                <x-likeslocale.button type="submit" variant="accent">Add</x-likeslocale.button>
            </form>
            @error('name')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <ul class="mt-5 space-y-2">
                @forelse($countries as $country)
                    <li class="flex items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <span class="text-sm font-medium text-gray-800">{{ $country->name }}</span>
                        <form method="POST" action="{{ route('admin.countries.destroy', $country) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium"
                                    onclick="return confirm('Remove {{ $country->name }}?')">
                                Remove
                            </button>
                        </form>
                    </li>
                @empty
                    <li class="rounded-2xl bg-gray-50 border border-gray-100 p-4 text-sm text-gray-400 text-center">No countries yet.</li>
                @endforelse
            </ul>
        </div>

        {{-- Locations --}}
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <h3 class="text-xl font-semibold text-gray-900">Locations</h3>
            <p class="mt-1 text-sm text-gray-500">Cities and regions within each country.</p>

            <form method="POST" action="{{ route('admin.locations.store') }}" class="mt-5 space-y-3">
                @csrf
                <div class="flex gap-3">
                    <select name="country_id" class="w-44 shrink-0 rounded-2xl border-gray-300 shadow-sm text-sm">
                        <option value="">Country</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" @selected(old('country_id') == $country->id)>
                                {{ $country->name }}
                            </option>
                        @endforeach
                    </select>
                    <input name="name" type="text" placeholder="e.g. Negril"
                           class="flex-1 rounded-2xl border-gray-300 shadow-sm text-sm"
                           value="{{ old('name') }}">
                    <x-likeslocale.button type="submit" variant="accent">Add</x-likeslocale.button>
                </div>
                @error('country_id')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                @error('name')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
            </form>

            <ul class="mt-5 space-y-2 max-h-96 overflow-y-auto pr-1">
                @forelse($locations->groupBy(fn($l) => $l->country->name) as $countryName => $group)
                    <li>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 px-1 mb-1">{{ $countryName }}</p>
                        <ul class="space-y-1">
                            @foreach($group as $location)
                                <li class="flex items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-gray-50 px-4 py-2">
                                    <span class="text-sm text-gray-800">{{ $location->name }}</span>
                                    <form method="POST" action="{{ route('admin.locations.destroy', $location) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium"
                                                onclick="return confirm('Remove {{ $location->name }}?')">
                                            Remove
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @empty
                    <li class="rounded-2xl bg-gray-50 border border-gray-100 p-4 text-sm text-gray-400 text-center">No locations yet.</li>
                @endforelse
            </ul>
        </div>

        {{-- Job Categories --}}
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <h3 class="text-xl font-semibold text-gray-900">Job Categories</h3>
            <p class="mt-1 text-sm text-gray-500">Industry categories for classifying listings.</p>

            <form method="POST" action="{{ route('admin.categories.store') }}" class="mt-5 flex gap-3">
                @csrf
                <input name="name" type="text" placeholder="e.g. Healthcare Support"
                       class="flex-1 rounded-2xl border-gray-300 shadow-sm text-sm"
                       value="{{ old('name') }}">
                <x-likeslocale.button type="submit" variant="accent">Add</x-likeslocale.button>
            </form>
            @error('name')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <ul class="mt-5 space-y-2 max-h-80 overflow-y-auto pr-1">
                @forelse($categories as $category)
                    <li class="flex items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <span class="text-sm font-medium text-gray-800">{{ $category->name }}</span>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium"
                                    onclick="return confirm('Remove {{ $category->name }}?')">
                                Remove
                            </button>
                        </form>
                    </li>
                @empty
                    <li class="rounded-2xl bg-gray-50 border border-gray-100 p-4 text-sm text-gray-400 text-center">No categories yet.</li>
                @endforelse
            </ul>
        </div>

        {{-- Employment Types --}}
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <h3 class="text-xl font-semibold text-gray-900">Employment Types</h3>
            <p class="mt-1 text-sm text-gray-500">Contract types available on job listings.</p>

            <form method="POST" action="{{ route('admin.employment-types.store') }}" class="mt-5 flex gap-3">
                @csrf
                <input name="name" type="text" placeholder="e.g. Internship"
                       class="flex-1 rounded-2xl border-gray-300 shadow-sm text-sm"
                       value="{{ old('name') }}">
                <x-likeslocale.button type="submit" variant="accent">Add</x-likeslocale.button>
            </form>
            @error('name')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            <ul class="mt-5 space-y-2">
                @forelse($employmentTypes as $type)
                    <li class="flex items-center justify-between gap-3 rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3">
                        <span class="text-sm font-medium text-gray-800">{{ $type->name }}</span>
                        <form method="POST" action="{{ route('admin.employment-types.destroy', $type) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium"
                                    onclick="return confirm('Remove {{ $type->name }}?')">
                                Remove
                            </button>
                        </form>
                    </li>
                @empty
                    <li class="rounded-2xl bg-gray-50 border border-gray-100 p-4 text-sm text-gray-400 text-center">No employment types yet.</li>
                @endforelse
            </ul>
        </div>

    </div>
</x-layouts.portal>
