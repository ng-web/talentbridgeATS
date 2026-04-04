<x-layouts.portal :title="'Applicants'" heading="Applicants" subheading="Review applicants and update their statuses." portalRole="employer">
    <div class="space-y-6">
        <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
            <h3 class="text-lg font-semibold text-gray-900">Applicant Pipeline</h3>
            <p class="mt-1 text-sm text-gray-500">Track each candidate from application through review and placement.</p>

            <div class="mt-4 flex flex-wrap gap-2">
                @foreach(\App\Models\Application::STATUSES as $status)
                    <x-likeslocale.status-pill :tone="\App\Models\Application::toneFor($status)">
                        {{ \App\Models\Application::labelFor($status) }}
                    </x-likeslocale.status-pill>
                @endforeach
            </div>
        </div>

        @if($applications->isEmpty())
            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
                <h3 class="text-xl font-semibold text-gray-900">No applicants yet</h3>
                <p class="mt-2 text-gray-500">Applicants will appear here once seekers apply to your approved jobs.</p>
            </div>
        @else
            <div class="rounded-3xl border border-gray-300 p-4 md:p-6" style="background:#e7e7ea;">
                <div class="space-y-4">
                    @foreach($applications as $application)
                        @php
                            $tone = \App\Models\Application::toneFor($application->status);
                            $statusLabel = \App\Models\Application::labelFor($application->status);
                            $initial = strtoupper(mb_substr($application->jobSeeker->user->name ?? 'A', 0, 1));
                        @endphp

                        <div class="rounded-2xl border border-gray-300 px-4 py-4 md:px-5 md:py-4 shadow-sm" style="background:#efeff2;">
                            <div class="rounded-2xl border border-transparent px-4 py-4 md:px-5 md:py-4 transition-all duration-200 ease-out hover:bg-[#f6f6f9] hover:border-gray-300 hover:shadow-md">
                                <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5">
                                    <div class="flex items-start gap-4 min-w-0 flex-1">
                                        <div class="shrink-0">
                                            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-semibold shadow-sm bg-[#6f4cb2]">
                                                {{ $initial }}
                                            </div>
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

                                            <div class="mt-1 text-sm">
                                                <span class="font-semibold text-gray-800">{{ $application->job->title }}</span>
                                                @if($application->job?->category)
                                                    <span class="text-gray-400">·</span>
                                                    <span class="text-gray-600">{{ $application->job->category }}</span>
                                                @endif
                                            </div>

                                            <div class="mt-2 text-sm text-gray-500 flex flex-wrap gap-x-3 gap-y-1">
                                                <span>
                                                    <span class="font-medium text-gray-700">Applied:</span>
                                                    {{ $application->applied_at?->format('M d, Y') }}
                                                </span>

                                                <span>
                                                    <span class="font-medium text-gray-700">Resume:</span>
                                                    {{ $application->submitted_resume_path ? 'Submitted' : 'Not submitted' }}
                                                </span>

                                                <span>
                                                    <span class="font-medium text-gray-700">Cover Letter:</span>
                                                    {{ $application->submitted_cover_letter_path ? 'Submitted' : 'Not submitted' }}
                                                </span>
                                            </div>

                                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-2xl">
                                                <div class="rounded-2xl border border-gray-200 bg-white/70 p-4">
                                                    <p class="text-sm font-medium text-gray-900">Submitted Resume</p>
                                                    @if($application->submitted_resume_path)
                                                        <a href="{{ asset('storage/'.$application->submitted_resume_path) }}"
                                                           target="_blank"
                                                           class="mt-2 inline-block text-sm font-medium text-[#6f4cb2] hover:underline">
                                                            View Resume
                                                        </a>
                                                    @else
                                                        <p class="mt-2 text-sm text-gray-500">Not submitted</p>
                                                    @endif
                                                </div>

                                                <div class="rounded-2xl border border-gray-200 bg-white/70 p-4">
                                                    <p class="text-sm font-medium text-gray-900">Submitted Cover Letter</p>
                                                    @if($application->submitted_cover_letter_path)
                                                        <a href="{{ asset('storage/'.$application->submitted_cover_letter_path) }}"
                                                           target="_blank"
                                                           class="mt-2 inline-block text-sm font-medium text-[#6f4cb2] hover:underline">
                                                            View Cover Letter
                                                        </a>
                                                    @else
                                                        <p class="mt-2 text-sm text-gray-500">Not submitted</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST"
                                        action="{{ route('employer.applications.update-status', $application) }}"
                                        class="flex flex-col gap-3 w-full xl:w-auto xl:min-w-[220px]"
                                        onsubmit="return confirm('Update applicant status for {{ addslashes($application->jobSeeker->user->name ?? 'this applicant') }} on {{ addslashes($application->job->title ?? 'this job') }}?');">
                                        @csrf
                                        @method('PATCH')

                                        <select name="status" class="rounded-2xl border-gray-300 shadow-sm w-full">
                                            @foreach(\App\Models\Application::STATUSES as $status)
                                                <option value="{{ $status }}" @selected($application->status === $status)>
                                                    {{ \App\Models\Application::labelFor($status) }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <x-likeslocale.button type="submit">
                                            Update Status
                                        </x-likeslocale.button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-layouts.portal>