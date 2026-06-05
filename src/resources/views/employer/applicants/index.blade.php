<x-layouts.portal :title="'Applicants'" heading="Applicants" subheading="Review candidates and update their application statuses." portalRole="employer">
    <div class="space-y-6">

        {{-- Filters --}}
        <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Search & Filter</h3>

                {{-- Quick-filter pills --}}
                <div class="flex flex-wrap gap-2" id="applicant-pills">
                    @foreach(\App\Models\Application::EMPLOYER_STATUSES as $s)
                        @php $isActive = $status === $s; @endphp
                        <button type="button"
                                data-status="{{ $s }}"
                                onclick="applyPillFilter('{{ $s }}')"
                                class="inline-flex items-center px-3 py-1.5 rounded-xl border text-xs font-semibold transition-colors
                                    {{ $isActive
                                        ? 'border-[#6f4cb2] bg-[#6f4cb2] text-white shadow-sm'
                                        : 'border-gray-300 bg-white text-gray-600 hover:border-[#6f4cb2] hover:text-[#6f4cb2]' }}">
                            {{ \App\Models\Application::labelFor($s) }}
                            @if($isActive)<span class="ml-1.5 opacity-70">×</span>@endif
                        </button>
                    @endforeach
                </div>
            </div>

            <form id="applicant-filter-form" method="GET" action="{{ route('employer.applicants.index') }}"
                  class="flex flex-col sm:flex-row sm:flex-wrap xl:flex-nowrap items-center gap-3">

                <input id="applicant-q" name="q" type="text" value="{{ $q }}"
                    placeholder="Search applicant name"
                    class="flex-1 min-w-0 w-full sm:w-auto rounded-2xl border-gray-300 shadow-sm">

                <select id="applicant-job" name="job_id" class="w-full sm:w-56 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All job listings</option>
                    @foreach($jobs as $job)
                        <option value="{{ $job->id }}" @selected($jobId === $job->id)>{{ $job->title }}</option>
                    @endforeach
                </select>

                <input type="hidden" id="applicant-status-input" name="status" value="{{ $status }}">

                <a href="{{ route('employer.applicants.index') }}" id="applicant-reset"
                   class="text-sm text-gray-400 hover:text-[#6f4cb2] hover:underline whitespace-nowrap {{ ($q || $jobId || $status) ? '' : 'hidden' }}">
                    Clear filters
                </a>
            </form>
        </div>

        @if(session('status'))
            <div class="rounded-3xl border border-green-200 bg-green-50 p-5 text-green-900 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        <div id="applicant-list-region">
            @include('employer.applicants.partials.list')
        </div>
    </div>

    @push('scripts')
    <script>
        (() => {
            const form      = document.getElementById('applicant-filter-form');
            const region    = document.getElementById('applicant-list-region');
            const qInput    = document.getElementById('applicant-q');
            const jobSel    = document.getElementById('applicant-job');
            const statusIn  = document.getElementById('applicant-status-input');
            const resetLink = document.getElementById('applicant-reset');

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
                    console.error(e);
                } finally {
                    region.style.opacity = '1';
                }
            };

            const bindPagination = () => {
                region.querySelectorAll('.pagination a, nav[role="navigation"] a').forEach(link => {
                    link.addEventListener('click', e => { e.preventDefault(); fetchList(link.href); });
                });
            };

            qInput?.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(fetchList, 300); });
            jobSel?.addEventListener('change', () => fetchList());
            resetLink?.addEventListener('click', e => {
                e.preventDefault();
                form.reset();
                statusIn.value = '';
                updatePills('');
                fetchList(form.action);
            });

            bindPagination();

            window.applyPillFilter = function(s) {
                const current = statusIn.value;
                statusIn.value = (current === s) ? '' : s;
                updatePills(statusIn.value);
                fetchList();
            };

            window.updatePills = function(active) {
                document.querySelectorAll('#applicant-pills button').forEach(btn => {
                    const isActive = btn.dataset.status === active;
                    btn.className = btn.className
                        .replace(/border-\[#6f4cb2\] bg-\[#6f4cb2\] text-white shadow-sm/g, '')
                        .replace(/border-gray-300 bg-white text-gray-600 hover:border-\[#6f4cb2\] hover:text-\[#6f4cb2\]/g, '')
                        .trim();
                    btn.classList.add(...(isActive
                        ? ['border-[#6f4cb2]', 'bg-[#6f4cb2]', 'text-white', 'shadow-sm']
                        : ['border-gray-300', 'bg-white', 'text-gray-600', 'hover:border-[#6f4cb2]', 'hover:text-[#6f4cb2]']
                    ));
                    btn.innerHTML = `${btn.dataset.status.charAt(0).toUpperCase() + btn.dataset.status.slice(1).replace('_', ' ')}${isActive ? ' <span class="ml-1.5 opacity-70">×</span>' : ''}`;
                });
            };
        })();
    </script>
    @endpush
</x-layouts.portal>
