<x-layouts.portal :title="$job->title" :heading="$job->title" subheading="Review details and apply to this opportunity." portalRole="jobseeker">
    @php
        $companyName = $job->employer?->company_name ?: ($job->employer?->user?->name ?: 'Company');
        $logoPath = $job->employer?->logo_path;
        $initial = strtoupper(mb_substr($companyName, 0, 1));
    @endphp

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                <div class="flex items-start gap-4 mb-6">
                    <div class="shrink-0">
                        @if($logoPath)
                            <img src="{{ asset('storage/'.$logoPath) }}"
                                 alt="{{ $companyName }}"
                                 class="w-14 h-14 rounded-xl object-cover border border-gray-200 bg-white">
                        @else
                            <div class="w-14 h-14 rounded-xl flex items-center justify-center text-white font-semibold shadow-sm"
                                 style="background:#6f4cb2;">
                                {{ $initial }}
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <div class="flex flex-wrap gap-2 mb-2">
                            <x-likeslocale.status-pill tone="brand">
                                {{ \App\Models\Job::listingTypeLabelFor($job->listing_type) }}
                            </x-likeslocale.status-pill>

                            @if($job->employment_type)
                                <x-likeslocale.status-pill tone="neutral">
                                    {{ $job->employment_type }}
                                </x-likeslocale.status-pill>
                            @endif

                            @if($job->category)
                                <x-likeslocale.status-pill tone="neutral">
                                    {{ $job->category }}
                                </x-likeslocale.status-pill>
                            @endif
                        </div>

                        <p class="text-base font-semibold text-gray-900">{{ $companyName }}</p>

                        <p class="mt-1 text-sm text-gray-500">
                            {{ $job->location ?: 'Location TBD' }}
                            @if($job->country)
                                <span class="mx-2 text-gray-300">|</span>{{ $job->country }}
                            @endif
                            @if($job->remote_flag)
                                <span class="mx-2 text-gray-300">|</span>Remote
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Overview --}}
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Overview</h3>
                    <p class="text-gray-700 leading-7 whitespace-pre-line">{{ $job->description }}</p>
                </div>
            </div>

            @if($job->responsibilities)
                <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                    <h3 class="text-xl font-semibold mb-4">Key Responsibilities</h3>
                    <ul class="space-y-2">
                        @foreach(array_filter(array_map('trim', explode("\n", $job->responsibilities))) as $item)
                            <li class="flex items-start gap-2.5 text-gray-700">
                                <x-heroicon-o-check-circle class="w-4 h-4 mt-0.5 shrink-0 text-[#6f4cb2]" />
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($job->eligibility)
                <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                    <h3 class="text-xl font-semibold mb-4">Eligibility & Requirements</h3>
                    <ul class="space-y-2">
                        @foreach(array_filter(array_map('trim', explode("\n", $job->eligibility))) as $item)
                            <li class="flex items-start gap-2.5 text-gray-700">
                                <x-heroicon-o-check-circle class="w-4 h-4 mt-0.5 shrink-0 text-[#50b7a4]" />
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($job->benefits)
                <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                    <h3 class="text-xl font-semibold mb-4">Benefits & Perks</h3>
                    <ul class="space-y-2">
                        @foreach(array_filter(array_map('trim', explode("\n", $job->benefits))) as $item)
                            <li class="flex items-start gap-2.5 text-gray-700">
                                <x-heroicon-o-star class="w-4 h-4 mt-0.5 shrink-0 text-amber-500" />
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                <h3 class="text-xl font-semibold">Opportunity Summary</h3>

                <div class="mt-5 divide-y divide-gray-100 text-sm text-gray-600">
                    <div class="py-2.5 first:pt-0 last:pb-0"><span class="font-medium text-gray-900">Location:</span> {{ $job->location ?: 'TBD' }}</div>
                    <div class="py-2.5 first:pt-0 last:pb-0"><span class="font-medium text-gray-900">Country:</span> {{ $job->country ?: 'TBD' }}</div>
                    <div class="py-2.5 first:pt-0 last:pb-0"><span class="font-medium text-gray-900">Type:</span> {{ \App\Models\Job::listingTypeLabelFor($job->listing_type) }}</div>
                    @if($job->duration)
                        <div class="py-2.5 first:pt-0 last:pb-0"><span class="font-medium text-gray-900">Duration:</span> {{ $job->duration }}</div>
                    @endif
                    @if($job->application_deadline)
                        <div class="py-2.5 first:pt-0 last:pb-0">
                            <span class="font-medium text-gray-900">Deadline:</span>
                            <span class="{{ $deadlinePassed ? 'text-red-600 font-medium' : '' }}">
                                {{ $job->application_deadline->format('M d, Y') }}
                                @if($deadlinePassed)<span class="mx-2 text-gray-300">|</span>Closed @endif
                            </span>
                        </div>
                    @endif
                </div>

                <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                    @if($deadlinePassed)
                        <div class="flex items-center gap-2">
                            <x-likeslocale.status-pill tone="danger">Applications Closed</x-likeslocale.status-pill>
                        </div>
                        <p class="mt-3 text-xs text-gray-500 leading-5">
                            The deadline for this role has passed and it is no longer accepting applications.
                        </p>

                    @elseif($existingApplication && $existingApplication->status !== \App\Models\Application::STATUS_WITHDRAWN)
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-gray-900">Application submitted</p>
                            <x-likeslocale.status-pill :tone="\App\Models\Application::toneFor($existingApplication->status)">
                                {{ \App\Models\Application::labelFor($existingApplication->status) }}
                            </x-likeslocale.status-pill>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            Applied {{ $existingApplication->applied_at?->format('M d, Y') }}
                        </p>
                        <div class="mt-4 flex justify-center sm:justify-start">
                            <x-likeslocale.button :href="route('jobseeker.applications.index')" variant="info">
                                Track Application
                            </x-likeslocale.button>
                        </div>

                    @else
                        <h4 class="text-base font-semibold text-gray-900">Apply to this role</h4>
                        <div class="mt-4 flex justify-center sm:justify-start">
                            <x-likeslocale.button :href="route('jobseeker.jobs.apply', $job)" variant="accent">
                                Apply Now
                            </x-likeslocale.button>
                        </div>
                        <p class="mt-4 text-xs text-gray-500 leading-5">
                            Your profile resume will be used by default. You can upload a different resume on the next page. A tailored cover letter is required.
                        </p>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl p-6 md:p-8 shadow border"
                 style="background:rgba(111,76,178,0.08); border-color:rgba(111,76,178,0.15);">
                <h3 class="text-xl font-semibold text-gray-900">Tip</h3>
                <p class="mt-3 text-sm text-gray-700">
                    Make sure your profile resume is up to date before applying. You’ll usually want to tailor your cover letter for each opportunity.
                </p>
                <div class="mt-5 flex justify-center sm:justify-start">
                    <x-likeslocale.button :href="route('jobseeker.profile.edit')" variant="accent">
                        Review Profile
                    </x-likeslocale.button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.portal>