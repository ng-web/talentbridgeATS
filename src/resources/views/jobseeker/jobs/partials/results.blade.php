@if($jobs->isEmpty())
    <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
        <h3 class="text-xl font-semibold text-gray-900">No matching opportunities found</h3>
        <p class="mt-2 text-gray-500">Try a broader keyword, remove one filter, or search a different location.</p>
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
                <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">

                    {{-- Logo + content --}}
                    <div class="flex flex-col xl:flex-row xl:items-start gap-3 flex-1">
                        <div class="flex justify-center sm:justify-start shrink-0">
                            @if($logoPath)
                                <img src="{{ asset('storage/'.$logoPath) }}"
                                     alt="{{ $companyName }}"
                                     class="w-11 h-11 rounded-xl object-cover border border-gray-200 bg-white">
                            @else
                                <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white font-semibold shadow-sm bg-[#6f4cb2]">
                                    {{ $initial }}
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <a href="{{ route('jobseeker.jobs.show', $job) }}">
                                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                    <h3 class="text-base font-semibold tracking-[0.02em] text-[#6f4cb2]">
                                        {{ $job->title }}
                                    </h3>
                                    <x-likeslocale.status-pill tone="brand">
                                        {{ \App\Models\Job::listingTypeLabelFor($job->listing_type) }}
                                    </x-likeslocale.status-pill>
                                </div>
                            </a>

                            <div class="border-t border-gray-100 mt-2 pt-2 text-left">
                                <div class="text-sm">
                                    <span class="font-semibold text-gray-800">{{ $companyName }}</span>
                                    @if($job->category)
                                        <span class="mx-2 text-gray-300">|</span>
                                        <span class="text-gray-600">{{ $job->category }}</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ \Illuminate\Support\Str::limit($job->description, 95) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Location + buttons --}}
                    <div class="flex flex-col items-center gap-2.5 sm:flex-row sm:items-center xl:shrink-0">
                        <div class="text-sm text-gray-500 text-center sm:text-left">
                            {{ $job->location ?: 'Location TBD' }}
                            @if($job->country)
                                <span class="mx-2 text-gray-300">|</span>{{ $job->country }}
                            @endif
                            @if($job->remote_flag)
                                <span class="text-gray-400"> (Remote)</span>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-2 justify-center sm:justify-start">
                            <x-likeslocale.button :href="route('jobseeker.jobs.show', $job)" variant="info">
                                View
                            </x-likeslocale.button>
                            <x-likeslocale.button :href="route('jobseeker.jobs.apply', $job)" variant="accent">
                                Apply Now
                            </x-likeslocale.button>
                        </div>
                    </div>
                </div>
            </x-likeslocale.operation-row>
        @endforeach
    </div>
@endif
