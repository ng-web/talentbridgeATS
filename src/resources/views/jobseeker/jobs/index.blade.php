<x-layouts.portal :title="'Browse Opportunities'" heading="Browse Opportunities" subheading="Explore approved jobs and work-study opportunities." portalRole="jobseeker">
    <div class="space-y-6">
        <div class="rounded-3xl bg-white p-5 md:p-6 shadow border border-gray-100">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h3 class="text-2xl font-semibold">Search Opportunities</h3>
                    <p class="mt-1 text-sm text-gray-500">Find relevant listings by keyword, location, category, or type.</p>
                </div>

                <x-likeslocale.button :href="route('jobseeker.applications.index')" variant="accent">
                    My Applications
                </x-likeslocale.button>
            </div>

            <form id="job-filter-form" method="GET" action="{{ route('jobseeker.jobs.index') }}" class="mt-6 space-y-4">
                <datalist id="job-location-suggestions">
                    @foreach($availableLocations as $location)
                        <option value="{{ $location }}"></option>
                    @endforeach
                </datalist>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    <div>
                        <label for="keyword" class="block text-sm font-medium text-gray-700">Keywords</label>
                        <input id="keyword"
                               name="keyword"
                               type="text"
                               value="{{ $filters['keyword'] ?? '' }}"
                               class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm"
                               placeholder="Job title, company, category">
                    </div>

                    <div>
                        <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                        <input id="location"
                               name="location"
                               type="text"
                               list="job-location-suggestions"
                               value="{{ $filters['location'] ?? '' }}"
                               class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm"
                               placeholder="Start typing a location">
                    </div>

                    <div>
                        <label for="listing_type" class="block text-sm font-medium text-gray-700">Type</label>
                        <select id="listing_type" name="listing_type" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                            <option value="">All Types</option>
                            @foreach($availableTypes as $type)
                                <option value="{{ $type }}" @selected(($filters['listing_type'] ?? '') === $type)>
                                    {{ \App\Models\Job::listingTypeLabelFor($type) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                        <select id="category" name="category" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                            <option value="">All Categories</option>
                            @foreach($availableCategories as $category)
                                <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>
                                    {{ $category }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <label class="inline-flex items-center gap-3 text-sm text-gray-700">
                        <input type="checkbox"
                               name="remote_only"
                               value="1"
                               class="rounded border-gray-300 text-violet-600 focus:ring-violet-500"
                               @checked($filters['remote_only'] ?? false)>
                        <span>Remote positions only</span>
                    </label>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <x-likeslocale.button type="submit">
                            Search Jobs
                        </x-likeslocale.button>

                        <a href="{{ route('jobseeker.jobs.index') }}"
                           class="ll-btn ll-btn-outline"
                           id="job-filter-reset">
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div id="job-results">
            @include('jobseeker.jobs.partials.results', ['jobs' => $jobs])
        </div>
    </div>

    @push('scripts')
        <script>
            (() => {
                const form = document.getElementById('job-filter-form');
                const results = document.getElementById('job-results');
                const resetLink = document.getElementById('job-filter-reset');

                if (!form || !results) return;

                let timeoutId = null;

                const fetchResults = async () => {
                    const formData = new FormData(form);
                    const params = new URLSearchParams(formData);

                    try {
                        const response = await fetch(`${form.action}?${params.toString()}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                        });

                        if (!response.ok) return;

                        const data = await response.json();

                        if (data.html) {
                            results.innerHTML = data.html;
                            const newUrl = params.toString()
                                ? `${form.action}?${params.toString()}`
                                : form.action;

                            window.history.replaceState({}, '', newUrl);
                        }
                    } catch (error) {
                        console.error('Job filter request failed.', error);
                    }
                };

                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    fetchResults();
                });

                form.querySelectorAll('select, input[type="checkbox"]').forEach((element) => {
                    element.addEventListener('change', fetchResults);
                });

                form.querySelectorAll('input[type="text"]').forEach((element) => {
                    element.addEventListener('input', () => {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(fetchResults, 300);
                    });
                });

                if (resetLink) {
                    resetLink.addEventListener('click', (event) => {
                        event.preventDefault();
                        form.reset();
                        fetchResults();
                    });
                }
            })();
        </script>
    @endpush
</x-layouts.portal>