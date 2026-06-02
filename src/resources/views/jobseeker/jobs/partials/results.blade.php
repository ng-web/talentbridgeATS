@if($jobs->isEmpty())
    <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
        <h3 class="text-xl font-semibold text-gray-900">No matching opportunities found</h3>
        <p class="mt-2 text-gray-500">Try a broader keyword, remove one filter, or search a different location.</p>
    </div>
@else
    <div class="mb-3 text-sm text-gray-500 px-1">
        {{ $jobs->total() }} {{ Str::plural('opportunity', $jobs->total()) }} found
    </div>

    <div class="space-y-3">
        @foreach($jobs as $job)
            @php
                $companyName = $job->employer?->company_name ?: ($job->employer?->user?->name ?: 'Company');
                $logoPath    = $job->employer?->logo_path;
                $initial     = strtoupper(mb_substr($companyName, 0, 1));
                $deadlinePassed = $job->application_deadline && $job->application_deadline->isPast();
            @endphp

            <x-likeslocale.operation-row>
                <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">

                    <div class="flex items-start gap-3 flex-1 min-w-0">
                        <div class="shrink-0">
                            @if($logoPath)
                                <img src="{{ asset('storage/'.$logoPath) }}" alt="{{ $companyName }}"
                                     class="w-11 h-11 rounded-xl object-cover border border-gray-200 bg-white">
                            @else
                                <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white font-semibold shadow-sm bg-[#6f4cb2]">
                                    {{ $initial }}
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('jobseeker.jobs.show', $job) }}"
                                   class="text-base font-semibold tracking-[0.02em] text-[#6f4cb2] hover:underline">
                                    {{ $job->title }}
                                </a>
                                <x-likeslocale.status-pill tone="brand">
                                    {{ \App\Models\Job::listingTypeLabelFor($job->listing_type) }}
                                </x-likeslocale.status-pill>
                                @if($job->remote_flag)
                                    <x-likeslocale.status-pill tone="info">Remote</x-likeslocale.status-pill>
                                @endif
                            </div>

                            <div class="border-t border-gray-100 mt-2 pt-2">
                                <p class="text-sm font-semibold text-gray-800">{{ $companyName }}</p>
                                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500">
                                    @if($job->location || $job->country)
                                        <span>
                                            <x-heroicon-o-map-pin class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />
                                            {{ $job->location ?: '' }}{{ $job->location && $job->country ? ', ' : '' }}{{ $job->country ?: '' }}
                                        </span>
                                    @endif
                                    @if($job->duration)
                                        <span>
                                            <x-heroicon-o-clock class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />
                                            {{ $job->duration }}
                                        </span>
                                    @endif
                                    @if($job->application_deadline)
                                        <span class="{{ $deadlinePassed ? 'text-red-500 font-medium' : '' }}">
                                            <x-heroicon-o-calendar class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />
                                            {{ $deadlinePassed ? 'Closed' : 'Deadline: ' . $job->application_deadline->format('M d, Y') }}
                                        </span>
                                    @endif
                                    @if($job->salary_min || $job->salary_max)
                                        <span>
                                            <x-heroicon-o-banknotes class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />
                                            @if($job->salary_min && $job->salary_max)
                                                {{ number_format($job->salary_min) }}–{{ number_format($job->salary_max) }}
                                            @elseif($job->salary_min)
                                                From {{ number_format($job->salary_min) }}
                                            @else
                                                Up to {{ number_format($job->salary_max) }}
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2 justify-center sm:justify-start xl:shrink-0">
                        <x-likeslocale.button :href="route('jobseeker.jobs.show', $job)" variant="info">
                            View
                        </x-likeslocale.button>
                        @unless($deadlinePassed)
                            <x-likeslocale.button :href="route('jobseeker.jobs.apply', $job)" variant="accent">
                                Apply Now
                            </x-likeslocale.button>
                        @endunless
                    </div>
                </div>
            </x-likeslocale.operation-row>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $jobs->links() }}
    </div>
@endif
