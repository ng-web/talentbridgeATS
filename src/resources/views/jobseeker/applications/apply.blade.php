<x-layouts.portal :title="'Apply — ' . $job->title" heading="Apply for this Role" subheading="Submit your resume and a cover letter tailored for this position." portalRole="jobseeker">
    <div class="max-w-3xl space-y-6">

        {{-- Job summary --}}
        <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
            @php
                $companyName = $job->employer?->company_name ?: ($job->employer?->user?->name ?: 'Company');
                $logoPath = $job->employer?->logo_path;
                $initial = strtoupper(mb_substr($companyName, 0, 1));
            @endphp

            <div class="flex items-center gap-4">
                <div class="shrink-0">
                    @if($logoPath)
                        <img src="{{ asset('storage/'.$logoPath) }}"
                             alt="{{ $companyName }}"
                             class="w-12 h-12 rounded-xl object-cover border border-gray-200 bg-white">
                    @else
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-semibold shadow-sm"
                             style="background:#6f4cb2;">
                            {{ $initial }}
                        </div>
                    @endif
                </div>

                <div class="min-w-0">
                    <p class="text-lg font-semibold text-gray-900">{{ $job->title }}</p>
                    <p class="text-sm text-gray-500">
                        {{ $companyName }}
                        @if($job->location)
                            · {{ $job->location }}
                        @endif
                    </p>
                </div>
            </div>

            @if($job->application_deadline)
                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800">
                    <span class="font-medium">Deadline:</span> {{ $job->application_deadline->format('M d, Y') }}
                </div>
            @endif
        </div>

        {{-- Application form --}}
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <h3 class="text-xl font-semibold text-gray-900">Your Application</h3>

            <form method="POST" action="{{ route('jobseeker.jobs.apply.store', $job) }}" enctype="multipart/form-data" class="mt-6 space-y-6">
                @csrf

                {{-- Resume --}}
                <div>
                    <p class="text-sm font-semibold text-gray-900">Resume</p>

                    @if($jobSeeker->resume_path)
                        <div class="mt-2 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <span class="font-medium text-gray-900">Profile resume on file</span>
                                    <span class="text-gray-400 mx-1.5">·</span>
                                    <a href="{{ asset('storage/' . $jobSeeker->resume_path) }}"
                                       target="_blank"
                                       class="font-medium text-[#6f4cb2] hover:underline">View</a>
                                </div>
                                <x-likeslocale.status-pill tone="success">Ready</x-likeslocale.status-pill>
                            </div>

                            <p class="mt-2 text-gray-500 text-xs">
                                This resume will be submitted unless you upload a different one below.
                            </p>
                        </div>
                    @else
                        <div class="mt-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            No profile resume on file. You must upload one below to continue.
                        </div>
                    @endif

                    <div class="mt-3">
                        <label for="resume" class="block text-sm font-medium text-gray-700">
                            {{ $jobSeeker->resume_path ? 'Upload a different resume for this role (optional)' : 'Upload your resume' }}
                        </label>
                        <input
                            id="resume"
                            name="resume"
                            type="file"
                            accept=".pdf,.doc,.docx"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm text-sm"
                        >
                        <p class="mt-1.5 text-xs text-gray-500">PDF, DOC, or DOCX — max 5 MB</p>

                        @error('resume')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="border-t border-gray-100"></div>

                {{-- Cover letter --}}
                <div>
                    <label for="cover_letter" class="block text-sm font-semibold text-gray-900">
                        Cover Letter
                        <span class="ml-1 text-sm font-normal text-gray-500">(required)</span>
                    </label>
                    <p class="mt-1 text-sm text-gray-500">
                        Write a cover letter specific to this role and employer. This is your opportunity to stand out.
                    </p>
                    <input
                        id="cover_letter"
                        name="cover_letter"
                        type="file"
                        accept=".pdf,.doc,.docx"
                        class="mt-3 block w-full rounded-2xl border-gray-300 shadow-sm text-sm"
                    >
                    <p class="mt-1.5 text-xs text-gray-500">PDF, DOC, or DOCX — max 5 MB</p>

                    @error('cover_letter')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <x-likeslocale.button type="submit" variant="accent">
                        Submit Application
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('jobseeker.jobs.show', $job)" variant="secondary">
                        Back to Role
                    </x-likeslocale.button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.portal>
