@if($jobs->isEmpty())
    <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
        <h3 class="text-xl font-semibold text-gray-900">No matching opportunities found</h3>
        <p class="mt-2 text-gray-500">Try a broader keyword, remove one filter, or search a different location.</p>
    </div>
@else
    <div class="rounded-3xl border border-gray-300 p-4 md:p-6" style="background:#e7e7ea;">
        <div class="space-y-4">
            @foreach($jobs as $job)
                @php
                    $companyName = $job->employer?->company_name ?: ($job->employer?->user?->name ?: 'Company');
                    $logoPath = $job->employer?->logo_path;
                    $initial = strtoupper(mb_substr($companyName, 0, 1));
                @endphp

                <div class="rounded-2xl border border-gray-300 px-4 py-4 md:px-5 md:py-4 shadow-sm" style="background:#efeff2;">
                    <div class="rounded-2xl border border-transparent px-4 py-4 md:px-5 md:py-4 transition-all duration-200 ease-out hover:bg-[#f6f6f9] hover:border-gray-300 hover:shadow-md">
                        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-5">
                            <a href="{{ route('jobseeker.jobs.show', $job) }}" class="flex items-start gap-4 min-w-0 flex-1">
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
                                    </div>

                                    <div class="mt-1 text-sm">
                                        <span class="font-semibold text-gray-800">{{ $companyName }}</span>
                                        @if($job->category)
                                            <span class="text-gray-400">·</span>
                                            <span class="text-gray-600">{{ $job->category }}</span>
                                        @endif
                                    </div>

                                    <p class="mt-2 text-sm text-gray-500">
                                        {{ \Illuminate\Support\Str::limit($job->description, 95) }}
                                    </p>
                                </div>
                            </a>

                            <div class="flex flex-col lg:flex-row lg:items-center gap-4 xl:gap-8 xl:shrink-0">
                                <div class="text-sm text-gray-500">
                                    {{ $job->location ?: 'Location TBD' }}
                                    @if($job->country)
                                        <span class="text-gray-400">·</span> {{ $job->country }}
                                    @endif
                                    @if($job->remote_flag)
                                        <span class="text-gray-400"> (Remote)</span>
                                    @endif
                                </div>

                                <div class="flex flex-row gap-3">
                                    <x-likeslocale.button :href="route('jobseeker.jobs.show', $job)" class="min-w-[96px]">
                                        View
                                    </x-likeslocale.button>

                                    <form method="POST" action="{{ route('jobseeker.jobs.apply', $job) }}">
                                        @csrf
                                        <x-likeslocale.button type="submit" variant="outline" class="min-w-[120px]">
                                            Quick Apply
                                        </x-likeslocale.button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif