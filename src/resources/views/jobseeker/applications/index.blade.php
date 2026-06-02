<x-layouts.portal :title="'My Applications'" heading="My Applications" subheading="Track the status of all your submitted applications." portalRole="jobseeker">
    <div class="space-y-6">
        <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Filter Applications</h3>
                    <p class="mt-0.5 text-sm text-gray-500">Search by job title or narrow by pipeline stage.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach(\App\Models\Application::STATUSES as $s)
                        <x-likeslocale.status-pill :tone="\App\Models\Application::toneFor($s)">
                            {{ \App\Models\Application::labelFor($s) }}
                        </x-likeslocale.status-pill>
                    @endforeach
                </div>
            </div>

            <form id="app-filter-form" method="GET" action="{{ route('jobseeker.applications.index') }}"
                  class="flex flex-col sm:flex-row sm:items-center gap-3">

                <input id="app-q" name="q" type="text" value="{{ $filters['q'] ?? '' }}"
                    placeholder="Search by job title"
                    class="flex-1 min-w-0 w-full sm:w-auto rounded-2xl border-gray-300 shadow-sm">

                <select id="app-status" name="status" class="w-full sm:w-40 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All statuses</option>
                    @foreach([
                        \App\Models\Application::STATUS_APPLIED,
                        \App\Models\Application::STATUS_REVIEWING,
                        \App\Models\Application::STATUS_SHORTLISTED,
                        \App\Models\Application::STATUS_PLACED,
                    ] as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>
                            {{ \App\Models\Application::labelFor($s) }}
                        </option>
                    @endforeach
                </select>

                <div class="flex gap-2 shrink-0 w-full sm:w-auto">
                    <x-likeslocale.button type="submit" variant="accent">Apply</x-likeslocale.button>
                    <a href="{{ route('jobseeker.applications.index') }}" id="app-filter-reset">
                        <x-likeslocale.button type="button" variant="secondary">Reset</x-likeslocale.button>
                    </a>
                </div>
            </form>
        </div>

        <div id="app-list-region">
            @include('jobseeker.applications.partials.list', ['applications' => $applications, 'filters' => $filters])
        </div>
    </div>

    @push('scripts')
    <script>
        (() => {
            const form      = document.getElementById('app-filter-form');
            const region    = document.getElementById('app-list-region');
            const qInput    = document.getElementById('app-q');
            const statusSel = document.getElementById('app-status');
            const resetLink = document.getElementById('app-filter-reset');

            if (!form || !region) return;

            let timer = null;

            const fetchList = async (url = null) => {
                const params = new URLSearchParams(new FormData(form));
                const target = url ?? `${form.action}?${params.toString()}`;

                region.style.opacity = '0.6';

                try {
                    const res = await fetch(target, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
                    });
                    if (!res.ok) return;
                    region.innerHTML = await res.text();
                    window.history.replaceState({}, '', target);
                    bindPagination();
                } catch (e) {
                    console.error('Application filter failed', e);
                } finally {
                    region.style.opacity = '1';
                }
            };

            const bindPagination = () => {
                region.querySelectorAll('.pagination a, nav[role="navigation"] a').forEach(link => {
                    link.addEventListener('click', e => { e.preventDefault(); fetchList(link.href); });
                });
            };

            form.addEventListener('submit', e => { e.preventDefault(); fetchList(); });
            statusSel?.addEventListener('change', () => fetchList());
            qInput?.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(fetchList, 300); });
            resetLink?.addEventListener('click', e => { e.preventDefault(); form.reset(); fetchList(); });

            bindPagination();
        })();
    </script>
    @endpush
</x-layouts.portal>
