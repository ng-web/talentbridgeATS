@if($jobs->isEmpty())
    <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
        <h3 class="text-xl font-semibold text-gray-900">No jobs found</h3>
        <p class="mt-2 text-gray-500">Employer-submitted jobs will appear here for moderation.</p>
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
                            <div class="flex items-start gap-4 min-w-0 flex-1">
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
                                            {{ \App\Models\Job::listingTypeLabelFor($job->listing_type) }}
                                        </x-likeslocale.status-pill>

                                        <x-likeslocale.status-pill :tone="\App\Models\Job::toneFor($job->status)">
                                            {{ \App\Models\Job::labelFor($job->status) }}
                                        </x-likeslocale.status-pill>

                                        <x-likeslocale.status-pill :tone="$job->is_approved ? 'success' : 'warning'">
                                            {{ $job->is_approved ? 'Approved' : 'Pending Approval' }}
                                        </x-likeslocale.status-pill>
                                    </div>

                                    <div class="border-t border-gray-100 mt-3 pt-2.5">
                                        <div class="text-sm">
                                            <span class="font-semibold text-gray-800">{{ $companyName }}</span>
                                            @if($job->category)
                                                <span class="mx-2 text-gray-300">|</span>
                                                <span class="text-gray-600">{{ $job->category }}</span>
                                            @endif
                                        </div>

                                        <p class="mt-1.5 text-sm text-gray-500">
                                            {{ \Illuminate\Support\Str::limit($job->description, 110) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col lg:flex-row lg:items-center gap-4 xl:gap-8 xl:shrink-0">
                                <div class="text-sm text-gray-500">
                                    {{ $job->location ?: 'Location TBD' }}
                                    @if($job->country)
                                        <span class="mx-2 text-gray-300">|</span>{{ $job->country }}
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-3">
                                    @if(!$job->is_approved || $job->status !== \App\Models\Job::STATUS_PUBLISHED)
                                        <form method="POST" action="{{ route('admin.jobs.approve', $job) }}"
                                            onsubmit="return confirm('Approve and publish this job?');">
                                            @csrf
                                            @method('PATCH')
                                            <x-likeslocale.button type="submit" variant="success">
                                                Approve
                                            </x-likeslocale.button>
                                        </form>
                                    @endif

                                    @if($job->status !== \App\Models\Job::STATUS_PENDING_REVIEW)
                                        <form method="POST" action="{{ route('admin.jobs.pending', $job) }}"
                                            onsubmit="return confirm('Move this job back to pending review?');">
                                            @csrf
                                            @method('PATCH')
                                            <x-likeslocale.button type="submit" variant="warning">
                                                Set Pending
                                            </x-likeslocale.button>
                                        </form>
                                    @endif

                                    @if($job->status !== \App\Models\Job::STATUS_ARCHIVED)
                                        <form method="POST" action="{{ route('admin.jobs.archive', $job) }}"
                                            onsubmit="return confirm('Archive this job? It will no longer be active.');">
                                            @csrf
                                            @method('PATCH')
                                            <x-likeslocale.button type="submit">
                                                Archive
                                            </x-likeslocale.button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                    </div>
                </x-likeslocale.operation-row>
            @endforeach
    </div>

    <div class="mt-6">
        {{ $jobs->links() }}
    </div>
@endif