<x-layouts.portal :title="'Applicants'" heading="Applicants" subheading="Review candidates and update their application statuses." portalRole="employer">
    <div class="space-y-6">

        {{-- Filters --}}
        <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Search & Filter</h3>

                {{-- Quick-filter pills --}}
                @php $baseParams = array_filter(['q' => $q ?: null, 'job_id' => $jobId ?: null]); @endphp
                <div class="flex flex-wrap gap-2">
                    @foreach(\App\Models\Application::EMPLOYER_STATUSES as $s)
                        @php $isActive = $status === $s; @endphp
                        <a href="{{ route('employer.applicants.index', array_merge($baseParams, $isActive ? [] : ['status' => $s])) }}"
                           class="inline-flex items-center px-3 py-1.5 rounded-xl border text-xs font-semibold transition-colors
                               {{ $isActive
                                   ? 'border-[#6f4cb2] bg-[#6f4cb2] text-white shadow-sm'
                                   : 'border-gray-300 bg-white text-gray-600 hover:border-[#6f4cb2] hover:text-[#6f4cb2]' }}">
                            {{ \App\Models\Application::labelFor($s) }}
                            @if($isActive)
                                <span class="ml-1.5 opacity-70">×</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            <form method="GET" action="{{ route('employer.applicants.index') }}"
                  class="flex flex-col sm:flex-row sm:flex-wrap xl:flex-nowrap items-center gap-3">

                <input name="q" type="text" value="{{ $q }}"
                    placeholder="Search applicant name"
                    class="flex-1 min-w-0 w-full sm:w-auto rounded-2xl border-gray-300 shadow-sm">

                <select name="job_id" class="w-full sm:w-56 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All job listings</option>
                    @foreach($jobs as $job)
                        <option value="{{ $job->id }}" @selected($jobId === $job->id)>{{ $job->title }}</option>
                    @endforeach
                </select>

                <select name="status" class="w-full sm:w-40 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All statuses</option>
                    @foreach(\App\Models\Application::EMPLOYER_STATUSES as $s)
                        <option value="{{ $s }}" @selected($status === $s)>
                            {{ \App\Models\Application::labelFor($s) }}
                        </option>
                    @endforeach
                </select>

                <div class="flex gap-2 shrink-0 w-full sm:w-auto">
                    <x-likeslocale.button type="submit" variant="accent">Apply</x-likeslocale.button>
                    <a href="{{ route('employer.applicants.index') }}">
                        <x-likeslocale.button type="button" variant="secondary">Reset</x-likeslocale.button>
                    </a>
                </div>
            </form>
        </div>

        @if(session('status'))
            <div class="rounded-3xl border border-green-200 bg-green-50 p-5 text-green-900 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        @if($applications->isEmpty())
            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
                <h3 class="text-xl font-semibold text-gray-900">
                    {{ ($q || $jobId || $status) ? 'No applicants match your filters' : 'No applicants yet' }}
                </h3>
                <p class="mt-2 text-gray-500">
                    {{ ($q || $jobId || $status) ? 'Try adjusting your search or clearing a filter.' : 'Applicants will appear here once seekers apply to your approved jobs.' }}
                </p>
                @if($q || $jobId || $status)
                    <div class="mt-4">
                        <a href="{{ route('employer.applicants.index') }}">
                            <x-likeslocale.button variant="secondary">Clear Filters</x-likeslocale.button>
                        </a>
                    </div>
                @endif
            </div>
        @else
            <div class="space-y-3">
                @foreach($applications as $application)
                    @php
                        $tone         = \App\Models\Application::toneFor($application->status);
                        $statusLabel  = \App\Models\Application::labelFor($application->status);
                        $initial      = strtoupper(mb_substr($application->jobSeeker->user->name ?? 'A', 0, 1));
                        $profilePhoto = $application->jobSeeker->documents->firstWhere('document_type', \App\Models\JobSeekerDocument::TYPE_PROFILE_PHOTO);
                        $certificates = $application->jobSeeker->documents->where('document_type', \App\Models\JobSeekerDocument::TYPE_CERTIFICATE)->values();
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

                            {{-- View + quick status --}}
                            <div class="flex flex-col gap-2 w-full xl:w-auto xl:min-w-[200px]">
                                <x-likeslocale.button :href="route('employer.applicants.show', $application)" variant="info">
                                    View Applicant
                                </x-likeslocale.button>

                                <form method="POST"
                                      action="{{ route('employer.applications.update-status', $application) }}"
                                      class="flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="notes" value="{{ $application->notes }}">
                                    <select name="status" class="flex-1 rounded-2xl border-gray-300 shadow-sm text-sm">
                                        @foreach(\App\Models\Application::EMPLOYER_STATUSES as $s)
                                            <option value="{{ $s }}" @selected($application->status === $s)>
                                                {{ \App\Models\Application::labelFor($s) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-likeslocale.button type="submit" variant="accent">Save</x-likeslocale.button>
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
    </div>
</x-layouts.portal>
