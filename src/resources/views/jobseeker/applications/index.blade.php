<x-layouts.portal :title="'My Applications'" heading="My Applications" subheading="Track the status of all your submitted applications." portalRole="jobseeker">
    <div class="space-y-6">
        <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">Application Pipeline</h3>
            <p class="mt-1 text-sm text-gray-500">Follow your progress from application through review and placement.</p>

            <div class="mt-4 flex flex-wrap gap-2">
                @foreach(\App\Models\Application::STATUSES as $status)
                    <x-likeslocale.status-pill :tone="\App\Models\Application::toneFor($status)">
                        {{ \App\Models\Application::labelFor($status) }}
                    </x-likeslocale.status-pill>
                @endforeach
            </div>
        </div>

        <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Filter Applications</h3>
                    <p class="mt-0.5 text-sm text-gray-500">Search by job title or narrow by status.</p>
                </div>
            </div>

            <form method="GET" action="{{ route('jobseeker.applications.index') }}" class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3">
                <input
                    id="q"
                    name="q"
                    type="text"
                    value="{{ $filters['q'] ?? '' }}"
                    placeholder="Search by job title"
                    class="flex-1 min-w-0 w-full sm:w-auto rounded-2xl border-gray-300 shadow-sm"
                >

                <select id="status" name="status" class="w-full sm:w-40 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All statuses</option>
                    @foreach([
                        \App\Models\Application::STATUS_APPLIED,
                        \App\Models\Application::STATUS_REVIEWING,
                        \App\Models\Application::STATUS_SHORTLISTED,
                        \App\Models\Application::STATUS_PLACED,
                    ] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                            {{ \App\Models\Application::labelFor($status) }}
                        </option>
                    @endforeach
                </select>

                <div class="flex gap-2 shrink-0 w-full sm:w-auto justify-center sm:justify-start">
                    <x-likeslocale.button type="submit" variant="accent">
                        Apply
                    </x-likeslocale.button>
                    <a href="{{ route('jobseeker.applications.index') }}">
                        <x-likeslocale.button type="button" variant="secondary">
                            Reset
                        </x-likeslocale.button>
                    </a>
                </div>
            </form>
        </div>

        @if($applications->isEmpty())
            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
                <h3 class="text-xl font-semibold text-gray-900">No applications found</h3>
                <p class="mt-2 text-gray-500">
                    @if(($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '')
                        No applications matched your current filters.
                    @else
                        Once you apply to opportunities, they will appear here.
                    @endif
                </p>

                <x-likeslocale.button :href="route('jobseeker.jobs.index')" class="mt-6">
                    Browse Opportunities
                </x-likeslocale.button>
            </div>
        @else
            <div class="space-y-3">
                @foreach($applications as $application)
                        @php
                            $job = $application->job;
                            $companyName = $job?->employer?->company_name ?: ($job?->employer?->user?->name ?: 'Company');
                            $tone = \App\Models\Application::toneFor($application->status);
                            $statusLabel = \App\Models\Application::labelFor($application->status);
                        @endphp

                        <x-likeslocale.operation-row>
                            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                                    @if($job)
                                        <a href="{{ route('jobseeker.jobs.show', $job) }}" class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                                <h3 class="text-base font-semibold tracking-[0.02em] text-[#6f4cb2]">
                                                    {{ $job->title }}
                                                </h3>

                                                <x-likeslocale.status-pill :tone="$tone">
                                                    {{ $statusLabel }}
                                                </x-likeslocale.status-pill>
                                            </div>

                                            <div class="border-t border-gray-100 mt-2 pt-2 text-left space-y-1.5">
                                                <div class="text-sm">
                                                    <span class="font-semibold text-gray-800">{{ $companyName }}</span>
                                                    @if($job?->category)
                                                        <span class="mx-2 text-gray-300">|</span>
                                                        <span class="text-gray-600">{{ $job->category }}</span>
                                                    @endif
                                                </div>

                                                <div class="text-sm text-gray-600">
                                                    <span class="font-medium text-gray-700">Applied:</span>
                                                    {{ $application->applied_at?->format('M d, Y') }}
                                                    <span class="mx-2 text-gray-300">|</span>
                                                    <span class="font-medium text-gray-700">Resume:</span>
                                                    {{ $application->submitted_resume_path ? 'Submitted' : 'Not submitted' }}
                                                    <span class="mx-2 text-gray-300">|</span>
                                                    <span class="font-medium text-gray-700">Cover Letter:</span>
                                                    {{ $application->submitted_cover_letter_path ? 'Submitted' : 'Not submitted' }}
                                                </div>
                                            </div>
                                        </a>
                                    @else
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                                <h3 class="text-base font-semibold tracking-[0.02em] text-[#6f4cb2]">
                                                    Job no longer available
                                                </h3>

                                                <x-likeslocale.status-pill :tone="$tone">
                                                    {{ $statusLabel }}
                                                </x-likeslocale.status-pill>
                                            </div>

                                            <div class="mt-2 text-sm text-gray-500 text-left">
                                                This application remains in your history.
                                            </div>
                                        </div>
                                    @endif

                                    <div class="flex flex-col items-center gap-2.5 sm:flex-row sm:items-center xl:shrink-0">
                                        <div class="text-sm text-gray-500 text-center sm:text-left">
                                            {{ $job?->location ?: 'Location TBD' }}
                                            @if($job?->country)
                                                <span class="mx-2 text-gray-300">|</span>{{ $job->country }}
                                            @endif
                                        </div>

                                        <div class="flex flex-row gap-2 justify-center sm:justify-start">
                                            @if($job)
                                                <x-likeslocale.button :href="route('jobseeker.jobs.show', $job)" variant="info">
                                                    View Role
                                                </x-likeslocale.button>
                                            @endif

                                            @if(in_array($application->status, [\App\Models\Application::STATUS_APPLIED, \App\Models\Application::STATUS_REVIEWING], true))
                                                <form method="POST"
                                                      action="{{ route('jobseeker.applications.withdraw', $application) }}"
                                                      onsubmit="return confirm('Withdraw your application for {{ addslashes($job?->title ?? 'this role') }}? This cannot be undone.');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <x-likeslocale.button type="submit" variant="secondary">
                                                        Withdraw
                                                    </x-likeslocale.button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </x-likeslocale.operation-row>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $applications->links() }}
            </div>
        @endif
    </div>
</x-layouts.portal>