<x-layouts.portal :title="'Applicants'" heading="Applicants" subheading="Review applicants and update their statuses." portalRole="employer">
    <div class="space-y-6">
        <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">Applicant Pipeline</h3>
            <p class="mt-1 text-sm text-gray-500">Track each candidate from application through review and placement.</p>

            <div class="mt-4 flex flex-wrap gap-2">
                @foreach(\App\Models\Application::EMPLOYER_STATUSES as $status)
                    <x-likeslocale.status-pill :tone="\App\Models\Application::toneFor($status)">
                        {{ \App\Models\Application::labelFor($status) }}
                    </x-likeslocale.status-pill>
                @endforeach
            </div>
        </div>

        @if(session('status'))
            <div class="rounded-3xl border border-green-200 bg-green-50 p-5 text-green-900 shadow-sm">
                {{ session('status') }}
            </div>
        @endif

        @if($applications->isEmpty())
            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
                <h3 class="text-xl font-semibold text-gray-900">No applicants yet</h3>
                <p class="mt-2 text-gray-500">Applicants will appear here once seekers apply to your approved jobs.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($applications as $application)
                    @php
                        $tone = \App\Models\Application::toneFor($application->status);
                        $statusLabel = \App\Models\Application::labelFor($application->status);
                        $initial = strtoupper(mb_substr($application->jobSeeker->user->name ?? 'A', 0, 1));
                        $profilePhoto = $application->jobSeeker->documents
                            ->firstWhere('document_type', \App\Models\JobSeekerDocument::TYPE_PROFILE_PHOTO);
                        $certificate = $application->jobSeeker->documents
                            ->firstWhere('document_type', \App\Models\JobSeekerDocument::TYPE_CERTIFICATE);
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

                                        <x-likeslocale.status-pill :tone="$tone">
                                            {{ $statusLabel }}
                                        </x-likeslocale.status-pill>
                                    </div>

                                    <div class="border-t border-gray-100 mt-3 pt-2.5 space-y-1.5">
                                        <div class="text-sm">
                                            <span class="font-semibold text-gray-800">{{ $application->job->title }}</span>
                                            @if($application->job?->category)
                                                <span class="mx-2 text-gray-300">|</span>
                                                <span class="text-gray-600">{{ $application->job->category }}</span>
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
                                                   class="mt-2 inline-block text-sm font-medium text-[#6f4cb2] hover:underline">
                                                    View
                                                </a>
                                            @else
                                                <p class="mt-2 text-sm text-gray-500">Not submitted</p>
                                            @endif
                                        </div>

                                        <div class="rounded-2xl border border-gray-200 bg-white/70 p-4">
                                            <p class="text-sm font-medium text-gray-900">Cover Letter</p>
                                            @if($application->submitted_cover_letter_path)
                                                <a href="{{ asset('storage/'.$application->submitted_cover_letter_path) }}"
                                                   target="_blank"
                                                   class="mt-2 inline-block text-sm font-medium text-[#6f4cb2] hover:underline">
                                                    View
                                                </a>
                                            @else
                                                <p class="mt-2 text-sm text-gray-500">Not submitted</p>
                                            @endif
                                        </div>

                                        <div class="rounded-2xl border border-gray-200 bg-white/70 p-4">
                                            <p class="text-sm font-medium text-gray-900">Qualifications</p>
                                            @if($certificate)
                                                <a href="{{ asset('storage/'.$certificate->file_path) }}"
                                                   target="_blank"
                                                   class="mt-2 inline-block text-sm font-medium text-[#6f4cb2] hover:underline">
                                                    View
                                                </a>
                                            @else
                                                <p class="mt-2 text-sm text-gray-500">Not uploaded</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Status + notes --}}
                            <form method="POST"
                                action="{{ route('employer.applications.update-status', $application) }}"
                                class="flex flex-col gap-3 w-full xl:w-auto xl:min-w-[220px]">
                                @csrf
                                @method('PATCH')

                                <select name="status" class="rounded-2xl border-gray-300 shadow-sm w-full">
                                    @foreach(\App\Models\Application::EMPLOYER_STATUSES as $status)
                                        <option value="{{ $status }}" @selected($application->status === $status)>
                                            {{ \App\Models\Application::labelFor($status) }}
                                        </option>
                                    @endforeach
                                </select>

                                <textarea
                                    name="notes"
                                    rows="3"
                                    placeholder="Internal notes (visible to your team only)…"
                                    class="rounded-2xl border-gray-300 shadow-sm w-full text-sm resize-none"
                                >{{ $application->notes }}</textarea>

                                <x-likeslocale.button type="submit" variant="accent">
                                    Save
                                </x-likeslocale.button>
                            </form>
                        </div>
                    </x-likeslocale.operation-row>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.portal>
