<x-layouts.portal :title="'Manage Jobs'" heading="Manage Jobs" subheading="Approve, return, or archive submitted jobs." portalRole="admin">
    <div class="space-y-6">
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <h3 class="text-xl font-semibold text-gray-900">Search & Filter Jobs</h3>
            <p class="mt-1 text-sm text-gray-500">Find jobs quickly by title, company, location, or status.</p>

            <form id="job-filters-form" method="GET" action="{{ route('admin.jobs.index') }}" class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3">
                <input
                    id="q"
                    name="q"
                    type="text"
                    value="{{ $filters['q'] ?? '' }}"
                    placeholder="Search by title, company, or location"
                    class="flex-1 min-w-0 w-full sm:w-auto rounded-2xl border-gray-300 shadow-sm"
                >

                <select id="status" name="status" class="w-full sm:w-44 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All statuses</option>
                    <option value="{{ \App\Models\Job::STATUS_DRAFT }}" @selected(($filters['status'] ?? '') === \App\Models\Job::STATUS_DRAFT)>Draft</option>
                    <option value="{{ \App\Models\Job::STATUS_PENDING_REVIEW }}" @selected(($filters['status'] ?? '') === \App\Models\Job::STATUS_PENDING_REVIEW)>Pending Review</option>
                    <option value="{{ \App\Models\Job::STATUS_PUBLISHED }}" @selected(($filters['status'] ?? '') === \App\Models\Job::STATUS_PUBLISHED)>Published</option>
                    <option value="{{ \App\Models\Job::STATUS_ARCHIVED }}" @selected(($filters['status'] ?? '') === \App\Models\Job::STATUS_ARCHIVED)>Archived</option>
                </select>

                <a href="{{ route('admin.jobs.index') }}" class="shrink-0">
                    <x-likeslocale.button type="button" variant="secondary">
                        Reset
                    </x-likeslocale.button>
                </a>
            </form>
        </div>

        <div id="jobs-list-region">
            @include('admin.jobs.partials.list', ['jobs' => $jobs, 'filters' => $filters])
        </div>
    </div>

    @push('scripts')
    <script>
        (() => {
            const form = document.getElementById('job-filters-form');
            const listRegion = document.getElementById('jobs-list-region');
            const qInput = document.getElementById('q');
            const statusSelect = document.getElementById('status');

            if (!form || !listRegion || !qInput || !statusSelect) {
                return;
            }

            let debounceTimer = null;

            const buildUrl = (url = null) => {
                if (url) return url;

                const formData = new FormData(form);
                const params = new URLSearchParams();

                for (const [key, value] of formData.entries()) {
                    if (String(value).trim() !== '') {
                        params.set(key, value);
                    }
                }

                const action = form.getAttribute('action') || window.location.pathname;
                const query = params.toString();

                return query ? `${action}?${query}` : action;
            };

            const fetchList = async (url = null) => {
                const targetUrl = buildUrl(url);

                listRegion.style.opacity = '0.6';

                try {
                    const response = await fetch(targetUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                        },
                    });

                    if (!response.ok) {
                        throw new Error(`Request failed with status ${response.status}`);
                    }

                    const html = await response.text();
                    listRegion.innerHTML = html;
                    window.history.replaceState({}, '', targetUrl);
                    bindPagination();
                } catch (error) {
                    console.error('Job filtering failed:', error);
                } finally {
                    listRegion.style.opacity = '1';
                }
            };

            const debouncedFetch = () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => fetchList(), 300);
            };

            const bindPagination = () => {
                listRegion.querySelectorAll('.pagination a, nav[role="navigation"] a').forEach((link) => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        fetchList(link.href);
                    });
                });
            };

            qInput.addEventListener('input', debouncedFetch);
            statusSelect.addEventListener('change', () => fetchList());

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                fetchList();
            });

            bindPagination();
        })();
    </script>
    @endpush
</x-layouts.portal>