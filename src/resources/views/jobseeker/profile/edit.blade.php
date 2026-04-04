<x-layouts.portal :title="'My Profile'" heading="My Profile" subheading="Complete your profile and upload your documents." portalRole="jobseeker">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
            <p class="text-sm text-gray-500">Profile Completion</p>
            <p class="text-4xl font-bold mt-3">{{ $jobSeeker->profile_completeness }}%</p>

            <div class="mt-4 w-full bg-gray-200 rounded-full h-3">
                <div class="bg-violet-600 h-3 rounded-full" style="width: {{ $jobSeeker->profile_completeness }}%"></div>
            </div>

            <div class="mt-6 space-y-3 text-sm">
                <p>
                    Resume:
                    @if($jobSeeker->resume_path)
                        <a href="{{ asset('storage/'.$jobSeeker->resume_path) }}" class="text-violet-600" target="_blank">View</a>
                    @else
                        <span class="text-gray-500">Not uploaded</span>
                    @endif
                </p>

                <p>
                    Cover Letter:
                    @if($jobSeeker->cover_letter_path)
                        <a href="{{ asset('storage/'.$jobSeeker->cover_letter_path) }}" class="text-violet-600" target="_blank">View</a>
                    @else
                        <span class="text-gray-500">Not uploaded</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
                <form method="POST" action="{{ route('jobseeker.profile.update') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="date_of_birth" class="block text-sm font-medium text-gray-700">Date of Birth</label>
                            <input id="date_of_birth" name="date_of_birth" type="date"
                                value="{{ old('date_of_birth', optional($jobSeeker->date_of_birth)->format('Y-m-d')) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            @error('date_of_birth')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                            <input id="location" name="location" type="text"
                                value="{{ old('location', $jobSeeker->location) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            @error('location')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="education" class="block text-sm font-medium text-gray-700">Education</label>
                        <textarea id="education" name="education" rows="4"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('education', $jobSeeker->education) }}</textarea>
                        @error('education')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="experience_summary" class="block text-sm font-medium text-gray-700">Experience Summary</label>
                        <textarea id="experience_summary" name="experience_summary" rows="4"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('experience_summary', $jobSeeker->experience_summary) }}</textarea>
                        @error('experience_summary')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="skills" class="block text-sm font-medium text-gray-700">Skills</label>
                        <textarea id="skills" name="skills" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('skills', $jobSeeker->skills) }}</textarea>
                        @error('skills')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="work_study_interest_flag" value="1"
                                @checked(old('work_study_interest_flag', $jobSeeker->work_study_interest_flag))>
                            <span class="text-sm text-gray-700">Interested in work-study opportunities</span>
                        </label>
                    </div>

                    <div>
                        <x-likeslocale.button type="submit">
                            Save Profile
                        </x-likeslocale.button>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
                    <h3 class="font-semibold text-xl mb-4">Upload Resume</h3>

                    <form method="POST" action="{{ route('jobseeker.profile.resume.upload') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <input type="file" name="resume" accept=".pdf,.doc,.docx" class="block w-full text-sm text-gray-700">

                        @error('resume')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <x-likeslocale.button type="submit" variant="secondary">
                            Upload Resume
                        </x-likeslocale.button>
                    </form>
                </div>

                <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
                    <h3 class="font-semibold text-xl mb-4">Upload Cover Letter</h3>

                    <form method="POST" action="{{ route('jobseeker.profile.cover-letter.upload') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <input type="file" name="cover_letter" accept=".pdf,.doc,.docx" class="block w-full text-sm text-gray-700">

                        @error('cover_letter')
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <x-likeslocale.button type="submit" variant="secondary">
                            Upload Cover Letter
                        </x-likeslocale.button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.portal>