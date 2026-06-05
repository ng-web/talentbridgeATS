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
        @if(($filters['q'] ?? '') === '' && ($filters['status'] ?? '') === '')
            <div class="mt-4">
                <x-likeslocale.button :href="route('jobseeker.jobs.index')">Browse Opportunities</x-likeslocale.button>
            </div>
        @endif
    </div>
@else
    <div class="space-y-3">
        @foreach($applications as $application)
            @php
                $job         = $application->job;
                $companyName = $job?->employer?->company_name ?: ($job?->employer?->user?->name ?: 'Company');
                $tone        = \App\Models\Application::toneFor($application->status);
                $statusLabel = \App\Models\Application::labelFor($application->status);
            @endphp

            <x-likeslocale.operation-row>
                <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                    @if($job)
                        <a href="{{ route('jobseeker.jobs.show', $job) }}" class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                <h3 class="text-base font-semibold tracking-[0.02em] text-[#6f4cb2]">{{ $job->title }}</h3>
                                <x-likeslocale.status-pill :tone="$tone">{{ $statusLabel }}</x-likeslocale.status-pill>
                            </div>

                            <div class="border-t border-gray-100 mt-2 pt-2 text-left space-y-1.5">
                                <div class="text-sm flex flex-wrap gap-x-4 gap-y-1 text-gray-600">
                                    <span class="font-semibold text-gray-800">
                                        <x-heroicon-o-building-office class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $companyName }}
                                    </span>
                                    @if($job?->category)
                                        <span><x-heroicon-o-tag class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $job->category }}</span>
                                    @endif
                                    @if($job?->listing_type)
                                        <span><x-heroicon-o-briefcase class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ \App\Models\Job::listingTypeLabelFor($job->listing_type) }}</span>
                                    @endif
                                </div>
                                <div class="text-sm flex flex-wrap gap-x-4 gap-y-1 text-gray-500">
                                    <span><x-heroicon-o-calendar-days class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $application->applied_at?->format('M d, Y') }}</span>
                                    <span class="{{ $application->submitted_resume_path ? 'text-green-600' : '' }}">
                                        <x-heroicon-o-document-text class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />Resume {{ $application->submitted_resume_path ? '✓' : '—' }}
                                    </span>
                                    <span class="{{ $application->submitted_cover_letter_path ? 'text-green-600' : '' }}">
                                        <x-heroicon-o-document-text class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />Cover Letter {{ $application->submitted_cover_letter_path ? '✓' : '—' }}
                                    </span>
                                </div>
                            </div>
                        </a>
                    @else
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-gray-500">Job no longer available</h3>
                                <x-likeslocale.status-pill :tone="$tone">{{ $statusLabel }}</x-likeslocale.status-pill>
                            </div>
                            <p class="mt-1 text-sm text-gray-400">This application remains in your history.</p>
                        </div>
                    @endif

                    <div class="flex flex-col items-center gap-2.5 sm:flex-row xl:shrink-0">
                        @if($job)
                            <div class="text-sm text-gray-500 text-center sm:text-left">
                                {{ $job->location ?: 'Location TBD' }}
                                @if($job->country)
                                    <span class="mx-2 text-gray-300">|</span>{{ $job->country }}
                                @endif
                            </div>
                        @endif

                        <div class="flex gap-2 justify-center sm:justify-start">
                            @if($job)
                                <x-likeslocale.button :href="route('jobseeker.jobs.show', $job)" variant="info">
                                    View Role
                                </x-likeslocale.button>
                            @endif

                            @if(in_array($application->status, [\App\Models\Application::STATUS_APPLIED, \App\Models\Application::STATUS_REVIEWING], true))
                                <form method="POST"
                                      action="{{ route('jobseeker.applications.withdraw', $application) }}"
                                      onsubmit="return confirm('Withdraw your application for {{ addslashes($job?->title ?? 'this role') }}?');">
                                    @csrf
                                    @method('PATCH')
                                    <x-likeslocale.button type="submit" variant="secondary">Withdraw</x-likeslocale.button>
                                </form>
                            @elseif($application->status === \App\Models\Application::STATUS_SHORTLISTED)
                                <x-likeslocale.button :href="route('contact')" variant="secondary">
                                    Withdraw
                                </x-likeslocale.button>
                            @endif
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
