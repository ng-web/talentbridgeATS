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
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium text-white"
                                  style="background:#6f4cb2;">
                                {{ ucfirst(str_replace('_', ' ', $job->listing_type)) }}
                            </span>

                            @if($job->employment_type)
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                    {{ $job->employment_type }}
                                </span>
                            @endif

                            @if($job->category)
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                                    {{ $job->category }}
                                </span>
                            @endif
                        </div>

                        <p class="text-base font-semibold text-gray-900">{{ $companyName }}</p>

                        <p class="mt-1 text-sm text-gray-500">
                            {{ $job->location ?: 'Location TBD' }}
                            @if($job->country)
                                · {{ $job->country }}
                            @endif
                            @if($job->remote_flag)
                                · Remote
                            @endif
                        </p>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="prose max-w-none prose-p:text-gray-700">
                        <p>{{ $job->description }}</p>
                    </div>
                </div>
            </div>

            @if($job->eligibility)
                <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                    <h3 class="text-2xl font-semibold mb-4">Eligibility</h3>
                    <p class="text-gray-700 leading-7">{{ $job->eligibility }}</p>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                <h3 class="text-xl font-semibold">Opportunity Summary</h3>

                <div class="mt-5 space-y-3 text-sm text-gray-600">
                    <p><span class="font-medium text-gray-900">Location:</span> {{ $job->location ?: 'TBD' }}</p>
                    <p><span class="font-medium text-gray-900">Country:</span> {{ $job->country ?: 'TBD' }}</p>
                    <p><span class="font-medium text-gray-900">Type:</span> {{ ucfirst(str_replace('_', ' ', $job->listing_type)) }}</p>
                    @if($job->duration)
                        <p><span class="font-medium text-gray-900">Duration:</span> {{ $job->duration }}</p>
                    @endif
                    @if($job->application_deadline)
                        <p><span class="font-medium text-gray-900">Deadline:</span> {{ $job->application_deadline->format('M d, Y') }}</p>
                    @endif
                </div>

                <form method="POST" action="{{ route('jobseeker.jobs.apply', $job) }}" class="mt-6">
                    @csrf
                    <button type="submit"
                            class="inline-flex w-full items-center justify-center rounded-2xl px-5 py-3 text-sm font-medium text-white transition hover:brightness-110"
                            style="background:#6f4cb2;">
                        Apply Now
                    </button>
                </form>
            </div>

            <div class="rounded-3xl p-6 md:p-8 shadow border"
                 style="background:rgba(111,76,178,0.08); border-color:rgba(111,76,178,0.15);">
                <h3 class="text-xl font-semibold text-gray-900">Tip</h3>
                <p class="mt-3 text-sm text-gray-700">
                    Make sure your profile is complete before applying so your documents and details are ready.
                </p>
                <a href="{{ route('jobseeker.profile.edit') }}"
                   class="inline-flex mt-5 items-center justify-center rounded-2xl px-5 py-3 text-sm font-medium text-white transition hover:brightness-110"
                   style="background:#6f4cb2;">
                    Review Profile
                </a>
            </div>
        </div>
    </div>
</x-layouts.portal>