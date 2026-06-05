<x-layouts.portal
    :title="$application->jobSeeker->user->name . ' — Applicant'"
    :heading="$application->jobSeeker->user->name"
    subheading="Applicant detail — review documents and update pipeline status."
    portalRole="employer">

    @php
        $seeker      = $application->jobSeeker;
        $user        = $seeker->user;
        $job         = $application->job;
        $tone        = \App\Models\Application::toneFor($application->status);
        $statusLabel = \App\Models\Application::labelFor($application->status);
        $profilePhoto = $seeker->documents->firstWhere('document_type', \App\Models\JobSeekerDocument::TYPE_PROFILE_PHOTO);
        $initial     = strtoupper(mb_substr($user->name, 0, 1));
    @endphp

    <div class="mb-5">
        <a href="{{ route('employer.applicants.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-[#6f4cb2] transition-colors">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Back to Applicants
        </a>
    </div>

    @if(session('status'))
        <div class="mb-5 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">

        {{-- Left: Applicant Summary --}}
        <div class="xl:col-span-1 space-y-4">
            <div class="rounded-3xl bg-white p-6 shadow border border-gray-100 text-center">
                @if($profilePhoto)
                    <img src="{{ asset('storage/' . $profilePhoto->file_path) }}"
                         alt="{{ $user->name }}"
                         class="w-20 h-20 rounded-2xl object-cover border border-gray-200 mx-auto">
                @else
                    <div class="w-20 h-20 rounded-2xl flex items-center justify-center text-white text-3xl font-bold shadow-sm mx-auto bg-[#6f4cb2]">
                        {{ $initial }}
                    </div>
                @endif

                <h2 class="mt-4 text-lg font-bold text-gray-900">{{ $user->name }}</h2>
                <x-likeslocale.status-pill :tone="$tone" class="mt-2">{{ $statusLabel }}</x-likeslocale.status-pill>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow border border-gray-100 space-y-3 text-sm">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Contact</p>
                    <p class="text-gray-700 break-all">{{ $user->email }}</p>
                    @if($seeker->phone)
                        <p class="text-gray-700 mt-1">{{ $seeker->phone }}</p>
                    @endif
                    @if($seeker->location)
                        <p class="text-gray-500 mt-1">{{ $seeker->location }}</p>
                    @endif
                </div>

                @if($seeker->date_of_birth)
                    <div class="pt-3 border-t border-gray-100">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Date of Birth</p>
                        <p class="text-gray-700">{{ $seeker->date_of_birth->format('M d, Y') }}</p>
                    </div>
                @endif

                <div class="pt-3 border-t border-gray-100">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Application</p>
                    <p class="text-gray-700 font-medium">{{ $job->title ?? 'Job removed' }}</p>
                    @if($job?->listing_type)
                        <p class="text-gray-500 text-xs mt-0.5">{{ \App\Models\Job::listingTypeLabelFor($job->listing_type) }}</p>
                    @endif
                    <p class="text-gray-400 text-xs mt-1">Applied {{ $application->applied_at?->format('M d, Y') }}</p>
                </div>
            </div>
        </div>

        {{-- Center: Documents --}}
        <div class="xl:col-span-2 space-y-5">

            {{-- Application Documents --}}
            <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Application Documents</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-2xl border p-4 {{ $application->submitted_resume_path ? 'border-green-200 bg-green-50/40' : 'border-gray-200 bg-gray-50' }}">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-semibold text-gray-900">Resume</p>
                            @if($application->submitted_resume_path)
                                <x-likeslocale.status-pill tone="success">Submitted</x-likeslocale.status-pill>
                            @else
                                <x-likeslocale.status-pill tone="neutral">Not submitted</x-likeslocale.status-pill>
                            @endif
                        </div>
                        @if($application->submitted_resume_path)
                            <a href="{{ asset('storage/'.$application->submitted_resume_path) }}"
                               target="_blank"
                               class="text-sm font-medium text-[#6f4cb2] hover:underline">View Resume →</a>
                        @endif
                    </div>

                    <div class="rounded-2xl border p-4 {{ $application->submitted_cover_letter_path ? 'border-green-200 bg-green-50/40' : 'border-gray-200 bg-gray-50' }}">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-semibold text-gray-900">Cover Letter</p>
                            @if($application->submitted_cover_letter_path)
                                <x-likeslocale.status-pill tone="success">Submitted</x-likeslocale.status-pill>
                            @else
                                <x-likeslocale.status-pill tone="neutral">Not submitted</x-likeslocale.status-pill>
                            @endif
                        </div>
                        @if($application->submitted_cover_letter_path)
                            <a href="{{ asset('storage/'.$application->submitted_cover_letter_path) }}"
                               target="_blank"
                               class="text-sm font-medium text-[#6f4cb2] hover:underline">View Letter →</a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Profile / Compliance Documents --}}
            @php
                $sensitiveTypes = [
                    \App\Models\JobSeekerDocument::TYPE_PASSPORT,
                    \App\Models\JobSeekerDocument::TYPE_POLICE_RECORD,
                    \App\Models\JobSeekerDocument::TYPE_MEDICAL_RECORD,
                ];
            @endphp
            @foreach($categories as $categoryLabel => $types)
                @php
                    $hasDocs = collect($types)->some(fn($t) => ($docsByType[$t] ?? collect())->isNotEmpty());
                @endphp

                <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">{{ $categoryLabel }}</h3>
                    <div class="space-y-3">
                        @foreach($types as $type)
                            @php
                                $docs      = $docsByType[$type] ?? collect();
                                $label     = \App\Models\JobSeekerDocument::labelFor($type);
                                $sensitive = in_array($type, $sensitiveTypes);
                            @endphp

                            <div class="rounded-2xl border p-4 {{ $docs->isNotEmpty() && !$sensitive ? 'border-green-200 bg-green-50/40' : ($sensitive ? 'border-gray-200 bg-gray-50' : 'border-gray-200 bg-gray-50') }}">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-sm font-semibold text-gray-900">{{ $label }}</p>
                                    @if($sensitive)
                                        <x-likeslocale.status-pill tone="neutral">Admin only</x-likeslocale.status-pill>
                                    @elseif($docs->isNotEmpty())
                                        <x-likeslocale.status-pill tone="success">
                                            {{ $docs->count() > 1 ? $docs->count() . ' files' : 'Uploaded' }}
                                        </x-likeslocale.status-pill>
                                    @else
                                        <x-likeslocale.status-pill tone="neutral">Not uploaded</x-likeslocale.status-pill>
                                    @endif
                                </div>

                                @if($sensitive)
                                    <p class="text-xs text-gray-400">This document is restricted to admin review only.</p>
                                @elseif($docs->isNotEmpty())
                                    <div class="space-y-1">
                                        @foreach($docs as $doc)
                                            <a href="{{ asset('storage/'.$doc->file_path) }}"
                                               target="_blank"
                                               class="block text-sm font-medium text-[#6f4cb2] hover:underline truncate"
                                               title="{{ $doc->original_name }}">
                                                {{ $doc->original_name ?: 'View document' }} →
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Right: Workflow --}}
        <div class="xl:col-span-1 space-y-5">

            {{-- Pipeline Progress --}}
            <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Pipeline Stage</h3>
                <div class="space-y-2">
                    @foreach(\App\Models\Application::EMPLOYER_STATUSES as $s)
                        @php $isActive = $application->status === $s; @endphp
                        <div class="flex items-center gap-3">
                            <div class="w-2.5 h-2.5 rounded-full shrink-0 {{ $isActive ? 'bg-[#6f4cb2]' : 'bg-gray-200' }}"></div>
                            <span class="text-sm {{ $isActive ? 'font-semibold text-[#6f4cb2]' : 'text-gray-400' }}">
                                {{ \App\Models\Application::labelFor($s) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Status Update --}}
            <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Update Status</h3>
                <form method="POST" action="{{ route('employer.applications.update-status', $application) }}"
                      class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Pipeline Stage</label>
                        <select name="status" class="block w-full rounded-2xl border-gray-300 shadow-sm text-sm">
                            @foreach(\App\Models\Application::EMPLOYER_STATUSES as $s)
                                <option value="{{ $s }}" @selected($application->status === $s)>
                                    {{ \App\Models\Application::labelFor($s) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">Internal Notes</label>
                        <textarea name="notes" rows="5"
                                  placeholder="Notes visible to your team only…"
                                  class="block w-full rounded-2xl border-gray-300 shadow-sm text-sm resize-none">{{ $application->notes }}</textarea>
                    </div>

                    <x-likeslocale.button type="submit" variant="accent" class="w-full justify-center">
                        Save Decision
                    </x-likeslocale.button>
                </form>
            </div>

            {{-- Applicant Profile --}}
            @if($seeker->education || $seeker->experience_summary || $seeker->skills)
                <div class="rounded-3xl bg-white p-6 shadow border border-gray-100 space-y-4 text-sm">
                    <h3 class="text-base font-semibold text-gray-900">Applicant Profile</h3>

                    @if($seeker->education)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Education</p>
                            <p class="text-gray-700 whitespace-pre-line">{{ $seeker->education }}</p>
                        </div>
                    @endif

                    @if($seeker->experience_summary)
                        <div class="pt-3 border-t border-gray-100">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Experience</p>
                            <p class="text-gray-700 whitespace-pre-line">{{ $seeker->experience_summary }}</p>
                        </div>
                    @endif

                    @if($seeker->skills)
                        <div class="pt-3 border-t border-gray-100">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Skills</p>
                            <p class="text-gray-700 whitespace-pre-line">{{ $seeker->skills }}</p>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-layouts.portal>
