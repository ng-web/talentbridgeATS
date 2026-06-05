<x-layouts.portal :title="'My Jobs'" heading="My Jobs" subheading="Manage your current job listings and their statuses." portalRole="employer">
    <div class="space-y-4">

        {{-- Compact header + filters --}}
        <div class="rounded-3xl bg-white p-5 shadow border border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">

                <form id="jobs-filter-form" method="GET" action="{{ route('employer.jobs.index') }}"
                      class="flex flex-1 flex-wrap gap-3 items-center">

                    @if($availableStatuses->count() > 1)
                        <select id="jobs-status" name="status" class="rounded-2xl border-gray-300 shadow-sm text-sm w-full sm:w-40">
                            <option value="">All statuses</option>
                            @foreach($availableStatuses as $s)
                                <option value="{{ $s }}" @selected($status === $s)>
                                    {{ \App\Models\Job::labelFor($s) }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    @if($availableTypes->count() > 1)
                        <select id="jobs-type" name="listing_type" class="rounded-2xl border-gray-300 shadow-sm text-sm w-full sm:w-48">
                            <option value="">All programme types</option>
                            @foreach($availableTypes as $t)
                                <option value="{{ $t }}" @selected($listingType === $t)>
                                    {{ \App\Models\Job::listingTypeLabelFor($t) }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    @if($status || $listingType)
                        <a href="{{ route('employer.jobs.index') }}" id="jobs-filter-reset"
                           class="text-sm text-gray-400 hover:text-[#6f4cb2] hover:underline whitespace-nowrap">
                            Clear filters
                        </a>
                    @endif
                </form>

                <x-likeslocale.button :href="route('employer.jobs.create')" variant="accent" class="shrink-0">
                    Create Job
                </x-likeslocale.button>
            </div>
        </div>

        {{-- Job list --}}
        <div id="jobs-list-region">
            @include('employer.jobs.partials.list')
        </div>
    </div>

    @push('scripts')
    <script>
        (() => {
            const form      = document.getElementById('jobs-filter-form');
            const region    = document.getElementById('jobs-list-region');
            const statusSel = document.getElementById('jobs-status');
            const typeSel   = document.getElementById('jobs-type');
            const reset     = document.getElementById('jobs-filter-reset');

            if (!form || !region) return;

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
                } catch (e) {
                    console.error(e);
                } finally {
                    region.style.opacity = '1';
                }
            };

            statusSel?.addEventListener('change', () => fetchList());
            typeSel?.addEventListener('change', () => fetchList());
            reset?.addEventListener('click', e => { e.preventDefault(); form.reset(); fetchList(form.action); });
        })();
    </script>
    @endpush
</x-layouts.portal>
