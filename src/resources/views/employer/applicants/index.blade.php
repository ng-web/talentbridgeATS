<x-layouts.portal :title="'Applicants'" heading="Applicants" subheading="Review candidates and update their application statuses." portalRole="employer">
    <div class="space-y-6">

        {{-- Filters --}}
        <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Search & Filter</h3>
                    <p class="mt-0.5 text-sm text-gray-500">Filter by applicant name, job listing, or pipeline stage.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach(\App\Models\Application::EMPLOYER_STATUSES as $s)
                        <x-likeslocale.status-pill :tone="\App\Models\Application::toneFor($s)">
                            {{ \App\Models\Application::labelFor($s) }}
                        </x-likeslocale.status-pill>
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
                                        <div class="text-sm">
                                            <span class="font-semibold text-gray-800">{{ $application->job->title }}</span>
                                            @if($application->job?->category)
                                                <span class="mx-2 text-gray-300">|</span>
                                                <span class="text-gray-600">{{ $application->job->category }}</span>
                                            @endif
                                            @if($application->job?->listing_type)
                                                <span class="mx-2 text-gray-300">|</span>
                                                <x-likeslocale.status-pill tone="neutral">
                                                    {{ \App\Models\Job::listingTypeLabelFor($application->job->listing_type) }}
                                                </x-likeslocale.status-pill>
                                            @endif
                                        </div>

                                        <div class="text-sm text-gray-600">
                                            <span class="font-medium text-gray-700">Applied:</span>
                                            {{ $application->applied_at?->format('M d, Y') }}
                                            <span class="mx-2 text-gray-300">|</span>
                                            <span class="font-medium text-gray-700">Resume:</span>
                                            {{ $application->submitted_resume_path ? 'Submitted' : 'Not submitted' }}
                                            <span class="mx-2 text-gray-300">|</span>
                                            <span class="font-medium text-gray-700">Cover Letter:</span>
                                            {{ $application->submitted_cover_letter_path ? 'Submitted' : 'Not submitted' }}
                                        </div>
                                    </div>

                                    {{-- Documents --}}
                                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 max-w-2xl">
                                        <div class="rounded-2xl border border-gray-200 bg-white/70 p-4">
                                            <p class="text-sm font-medium text-gray-900">Resume</p>
                                            @if($application->submitted_resume_path)
                                                <a href="{{ asset('storage/'.$application->submitted_resume_path) }}"
                                                   target="_blank"
                                                   class="mt-2 inline-block text-sm font-medium text-[#6f4cb2] hover:underline">View</a>
                                            @else
                                                <p class="mt-2 text-sm text-gray-500">Not submitted</p>
                                            @endif
                                        </div>

                                        <div class="rounded-2xl border border-gray-200 bg-white/70 p-4">
                                            <p class="text-sm font-medium text-gray-900">Cover Letter</p>
                                            @if($application->submitted_cover_letter_path)
                                                <a href="{{ asset('storage/'.$application->submitted_cover_letter_path) }}"
                                                   target="_blank"
                                                   class="mt-2 inline-block text-sm font-medium text-[#6f4cb2] hover:underline">View</a>
                                            @else
                                                <p class="mt-2 text-sm text-gray-500">Not submitted</p>
                                            @endif
                                        </div>

                                        <div class="rounded-2xl border border-gray-200 bg-white/70 p-4">
                                            <p class="text-sm font-medium text-gray-900">
                                                Qualifications
                                                @if($certificates->count() > 1)
                                                    <span class="text-xs text-gray-400 font-normal">({{ $certificates->count() }})</span>
                                                @endif
                                            </p>
                                            @if($certificates->isNotEmpty())
                                                <div class="mt-2 space-y-1">
                                                    @foreach($certificates as $cert)
                                                        <a href="{{ asset('storage/'.$cert->file_path) }}"
                                                           target="_blank"
                                                           class="block text-sm font-medium text-[#6f4cb2] hover:underline truncate"
                                                           title="{{ $cert->original_name }}">
                                                            {{ $cert->original_name ?: 'Certificate ' . $loop->iteration }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @else
                                                <p class="mt-2 text-sm text-gray-500">Not uploaded</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Status update form --}}
                            <form method="POST"
                                  action="{{ route('employer.applications.update-status', $application) }}"
                                  class="flex flex-col gap-3 w-full xl:w-auto xl:min-w-[220px]">
                                @csrf
                                @method('PATCH')

                                <select name="status" class="rounded-2xl border-gray-300 shadow-sm w-full">
                                    @foreach(\App\Models\Application::EMPLOYER_STATUSES as $s)
                                        <option value="{{ $s }}" @selected($application->status === $s)>
                                            {{ \App\Models\Application::labelFor($s) }}
                                        </option>
                                    @endforeach
                                </select>

                                <textarea name="notes" rows="3"
                                    placeholder="Internal notes (visible to your team only)…"
                                    class="rounded-2xl border-gray-300 shadow-sm w-full text-sm resize-none"
                                >{{ $application->notes }}</textarea>

                                <x-likeslocale.button type="submit" variant="accent">Save</x-likeslocale.button>
                            </form>
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
