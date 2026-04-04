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

        @if($applications->isEmpty())
            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
                <h3 class="text-xl font-semibold text-gray-900">No applications yet</h3>
                <p class="mt-2 text-gray-500">Once you apply to opportunities, they will appear here.</p>

                <x-likeslocale.button :href="route('jobseeker.jobs.index')" class="mt-6">
                    Browse Opportunities
                </x-likeslocale.button>
            </div>
        @else
            <div class="rounded-3xl border border-gray-300 p-4 md:p-6" style="background:#e7e7ea;">
                <div class="space-y-4">
                    @foreach($applications as $application)
                        @php
                            $job = $application->job;
                            $companyName = $job?->employer?->company_name ?: ($job?->employer?->user?->name ?: 'Company');
                            $tone = \App\Models\Application::toneFor($application->status);
                            $statusLabel = \App\Models\Application::labelFor($application->status);
                        @endphp

                        <div class="rounded-2xl border border-gray-300 px-4 py-4 md:px-5 md:py-4 shadow-sm" style="background:#efeff2;">
                            <div class="rounded-2xl border border-transparent px-4 py-4 md:px-5 md:py-4 transition-all duration-200 ease-out hover:bg-[#f6f6f9] hover:border-gray-300 hover:shadow-md">
                                <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-5">
                                    @if($job)
                                        <a href="{{ route('jobseeker.jobs.show', $job) }}" class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-lg font-semibold tracking-[0.02em] text-[#6f4cb2]">
                                                    {{ $job->title }}
                                                </h3>

                                                <x-likeslocale.status-pill :tone="$tone">
                                                    {{ $statusLabel }}
                                                </x-likeslocale.status-pill>
                                            </div>

                                            <div class="mt-1 text-sm">
                                                <span class="font-semibold text-gray-800">{{ $companyName }}</span>
                                                @if($job?->category)
                                                    <span class="text-gray-400">·</span>
                                                    <span class="text-gray-600">{{ $job->category }}</span>
                                                @endif
                                            </div>

                                            <div class="mt-2 text-sm text-gray-500 flex flex-wrap gap-x-3 gap-y-1">
                                                <span>
                                                    <span class="font-medium text-gray-700">Applied:</span>
                                                    {{ $application->applied_at?->format('M d, Y') }}
                                                </span>

                                                <span>
                                                    <span class="font-medium text-gray-700">Resume:</span>
                                                    {{ $application->submitted_resume_path ? 'Submitted' : 'Not submitted' }}
                                                </span>

                                                <span>
                                                    <span class="font-medium text-gray-700">Cover Letter:</span>
                                                    {{ $application->submitted_cover_letter_path ? 'Submitted' : 'Not submitted' }}
                                                </span>
                                            </div>
                                        </a>
                                    @else
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-lg font-semibold tracking-[0.02em] text-[#6f4cb2]">
                                                    Job no longer available
                                                </h3>

                                                <x-likeslocale.status-pill :tone="$tone">
                                                    {{ $statusLabel }}
                                                </x-likeslocale.status-pill>
                                            </div>

                                            <div class="mt-2 text-sm text-gray-500">
                                                This application remains in your history.
                                            </div>
                                        </div>
                                    @endif

                                    <div class="flex flex-col lg:flex-row lg:items-center gap-4 xl:gap-8 xl:shrink-0">
                                        <div class="text-sm text-gray-500">
                                            {{ $job?->location ?: 'Location TBD' }}
                                            @if($job?->country)
                                                <span class="text-gray-400">·</span> {{ $job->country }}
                                            @endif
                                        </div>

                                        @if($job)
                                            <div class="flex flex-row gap-3">
                                                <x-likeslocale.button :href="route('jobseeker.jobs.show', $job)" class="min-w-[96px]">
                                                    View
                                                </x-likeslocale.button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-layouts.portal>