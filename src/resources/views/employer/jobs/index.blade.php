<x-layouts.portal :title="'My Jobs'" heading="My Jobs" subheading="Manage your current job listings and their statuses." portalRole="employer">
    <div class="space-y-6">
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h3 class="text-2xl font-semibold">Job Listings</h3>
                    <p class="mt-1 text-sm text-gray-500">Create and manage all jobs under your employer account.</p>
                </div>

                <x-likeslocale.button :href="route('employer.jobs.create')" variant="accent">
                    Create Job
                </x-likeslocale.button>
            </div>
        </div>

        @if($jobs->isEmpty())
            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
                <h3 class="text-xl font-semibold text-gray-900">No jobs created yet</h3>
                <p class="mt-2 text-gray-500">Create your first job listing to begin receiving applicants.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($jobs as $job)
                    @php
                        $companyName = $job->employer?->company_name ?: ($job->employer?->user?->name ?: 'Company');
                        $logoPath = $job->employer?->logo_path;
                        $initial = strtoupper(mb_substr($companyName, 0, 1));
                    @endphp

                    <x-likeslocale.operation-row>
                        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-5">
                            <a href="{{ route('employer.jobs.edit', $job) }}" class="flex items-start gap-4 min-w-0 flex-1">
                                <div class="shrink-0">
                                    @if($logoPath)
                                        <img src="{{ asset('storage/'.$logoPath) }}"
                                             alt="{{ $companyName }}"
                                             class="w-12 h-12 rounded-xl object-cover border border-gray-200 bg-white">
                                    @else
                                        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-semibold shadow-sm bg-[#6f4cb2]">
                                            {{ $initial }}
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-semibold tracking-[0.02em] text-[#6f4cb2]">
                                            {{ $job->title }}
                                        </h3>

                                        <x-likeslocale.status-pill tone="brand">
                                            {{ ucfirst(str_replace('_', ' ', $job->listing_type)) }}
                                        </x-likeslocale.status-pill>

                                        <x-likeslocale.status-pill tone="neutral">
                                            {{ ucfirst(str_replace('_', ' ', $job->status)) }}
                                        </x-likeslocale.status-pill>

                                        <x-likeslocale.status-pill :tone="$job->is_approved ? 'success' : 'warning'">
                                            {{ $job->is_approved ? 'Approved' : 'Pending Review' }}
                                        </x-likeslocale.status-pill>
                                    </div>

                                    <div class="border-t border-gray-100 mt-3 pt-2.5">
                                        <div class="text-sm">
                                            <span class="font-semibold text-gray-800">{{ $companyName }}</span>
                                            @if($job->category)
                                                <span class="text-gray-400">·</span>
                                                <span class="text-gray-600">{{ $job->category }}</span>
                                            @endif
                                        </div>

                                        <p class="mt-1.5 text-sm text-gray-500">
                                            {{ \Illuminate\Support\Str::limit($job->description, 110) }}
                                        </p>
                                    </div>
                                </div>
                            </a>

                            <div class="flex flex-col lg:flex-row lg:items-center gap-4 xl:gap-8 xl:shrink-0">
                                <div class="text-sm text-gray-500">
                                    {{ $job->location ?: 'Location TBD' }}
                                    @if($job->country)
                                        <span class="text-gray-400">·</span> {{ $job->country }}
                                    @endif
                                </div>

                                <div class="flex flex-row gap-3">
                                    <x-likeslocale.button :href="route('employer.jobs.edit', $job)" variant="info">
                                        Edit
                                    </x-likeslocale.button>
                                </div>
                            </div>
                        </div>
                    </x-likeslocale.operation-row>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.portal>