@if($applications->isEmpty())
    <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
        <h3 class="text-xl font-semibold text-gray-900">
            {{ ($q || $jobId || $status) ? 'No applicants match your filters' : 'No applicants yet' }}
        </h3>
        <p class="mt-2 text-gray-500">
            {{ ($q || $jobId || $status) ? 'Try adjusting your search or clearing a filter.' : 'Applicants will appear here once seekers apply to your approved jobs.' }}
        </p>
    </div>
@else
    <div class="space-y-3">
        @foreach($applications as $application)
            @php
                $tone         = \App\Models\Application::toneFor($application->status);
                $statusLabel  = \App\Models\Application::labelFor($application->status);
                $initial      = strtoupper(mb_substr($application->jobSeeker->user->name ?? 'A', 0, 1));
                $profilePhoto = $application->jobSeeker->documents->firstWhere('document_type', \App\Models\JobSeekerDocument::TYPE_PROFILE_PHOTO);
            @endphp

            <x-likeslocale.operation-row>
                <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5">
                    <div class="flex items-start gap-4 min-w-0 flex-1">
                        <div class="shrink-0">
                            @if($profilePhoto)
                                <img src="{{ asset('storage/' . $profilePhoto->file_path) }}"
                                     alt="{{ $application->jobSeeker->user->name }}"
                                     class="w-12 h-12 rounded-xl object-cover border border-gray-200">
                            @else
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-semibold shadow-sm bg-[#6f4cb2]">
                                    {{ $initial }}
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-semibold tracking-[0.02em] text-[#6f4cb2]">
                                    {{ $application->jobSeeker->user->name }}
                                </h3>
                                <x-likeslocale.status-pill :tone="$tone">{{ $statusLabel }}</x-likeslocale.status-pill>
                            </div>

                            <div class="border-t border-gray-100 mt-3 pt-2.5 space-y-1.5">
                                <div class="text-sm flex flex-wrap gap-x-4 gap-y-1 text-gray-600">
                                    <span class="font-semibold text-gray-800">
                                        <x-heroicon-o-briefcase class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $application->job->title }}
                                    </span>
                                    @if($application->job?->category)
                                        <span><x-heroicon-o-tag class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $application->job->category }}</span>
                                    @endif
                                    @if($application->job?->listing_type)
                                        <x-likeslocale.status-pill tone="neutral">
                                            {{ \App\Models\Job::listingTypeLabelFor($application->job->listing_type) }}
                                        </x-likeslocale.status-pill>
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
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 w-full xl:w-48">
                        <x-likeslocale.button :href="route('employer.applicants.show', $application)" variant="info">
                            View Applicant
                        </x-likeslocale.button>

                        <form method="POST" action="{{ route('employer.applications.update-status', $application) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="notes" value="{{ $application->notes }}">
                            <select name="status" onchange="this.form.submit()"
                                    class="w-full rounded-2xl border-gray-300 shadow-sm text-sm">
                                @foreach(\App\Models\Application::EMPLOYER_STATUSES as $s)
                                    <option value="{{ $s }}" @selected($application->status === $s)>
                                        {{ \App\Models\Application::labelFor($s) }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                </div>
            </x-likeslocale.operation-row>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $applications->links() }}
    </div>
@endif
