<x-layouts.portal :title="'My Profile'" heading="My Profile" subheading="Complete your profile, upload your default resume, and submit your compliance documents." portalRole="jobseeker">
    @php
        $profilePhoto = $jobSeeker->documents->firstWhere('document_type', \App\Models\JobSeekerDocument::TYPE_PROFILE_PHOTO);
    @endphp

    @if(session('success'))
        <div class="mb-6 rounded-3xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 rounded-3xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="space-y-6">
            {{-- Profile photo --}}
            <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
                <div class="flex flex-col items-center text-center">
                    @if($profilePhoto)
                        <img src="{{ asset('storage/' . $profilePhoto->file_path) }}"
                             alt="Profile photo"
                             class="w-24 h-24 rounded-2xl object-cover border border-gray-200 shadow-sm">
                    @else
                        <div class="w-24 h-24 rounded-2xl flex items-center justify-center text-white text-3xl font-bold shadow-sm"
                             style="background:#6f4cb2;">
                            {{ strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                        </div>
                    @endif

                    <p class="mt-3 text-base font-semibold text-gray-900">{{ Auth::user()->name }}</p>
                    <p class="text-sm text-gray-500">{{ Auth::user()->email }}</p>
                </div>

                <div class="mt-5 space-y-2">
                    {{-- Single-action photo upload --}}
                    <form x-data="{ loading: false }"
                          method="POST"
                          action="{{ route('jobseeker.documents.store') }}"
                          enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="document_type" value="{{ \App\Models\JobSeekerDocument::TYPE_PROFILE_PHOTO }}">
                        <label class="block cursor-pointer" :class="loading ? 'opacity-50 pointer-events-none' : ''">
                            <input type="file"
                                   name="file"
                                   accept=".jpg,.jpeg,.png"
                                   class="sr-only"
                                   @change="loading = true; $el.closest('form').submit()">
                            <div class="flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 hover:border-[#6f4cb2]/40 hover:bg-violet-50/30 transition-all p-3 text-sm text-gray-500">
                                <x-heroicon-o-arrow-up-tray class="w-4 h-4 shrink-0" />
                                <span x-show="!loading">{{ $profilePhoto ? 'Replace photo' : 'Upload photo' }}</span>
                                <span x-show="loading" x-cloak>Uploading…</span>
                            </div>
                        </label>
                        @error('file')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </form>

                    @if($profilePhoto)
                        <form method="POST"
                              action="{{ route('jobseeker.documents.destroy', $profilePhoto) }}"
                              onsubmit="return confirm('Remove your profile photo?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="w-full text-xs font-medium text-red-500 hover:text-red-700 hover:underline text-center py-1">
                                Remove photo
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Profile completeness + resume --}}
            <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
                <p class="text-sm text-gray-500">Profile Completion</p>
                <p class="text-4xl font-bold mt-3">{{ $jobSeeker->profile_completeness }}%</p>

                <div class="mt-4 w-full bg-gray-200 rounded-full h-3">
                    <div class="bg-violet-600 h-3 rounded-full" style="width: {{ $jobSeeker->profile_completeness }}%"></div>
                </div>

                <div class="mt-6 space-y-2 text-sm">
                    <p class="font-medium text-gray-700">Default Resume</p>
                    @if($jobSeeker->resume_path)
                        <div class="flex items-center gap-3">
                            <a href="{{ asset('storage/'.$jobSeeker->resume_path) }}"
                               class="text-violet-600 hover:underline"
                               target="_blank">View resume</a>
                            <span class="text-gray-300">·</span>
                            <form method="POST"
                                  action="{{ route('jobseeker.profile.resume.clear') }}"
                                  onsubmit="return confirm('Remove your default resume?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-xs text-red-500 hover:text-red-700 hover:underline font-medium">
                                    Remove
                                </button>
                            </form>
                        </div>
                    @else
                        <p class="text-gray-500">Not uploaded</p>
                    @endif
                </div>

                <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                    Your profile resume is used as the default for applications. A cover letter is required per role.
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            {{-- Profile details form --}}
            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
                <form method="POST" action="{{ route('jobseeker.profile.update') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    {{-- Contact info --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email Address</label>
                            <input type="email" value="{{ Auth::user()->email }}" disabled
                                   class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-gray-500 shadow-sm cursor-not-allowed">
                            <p class="mt-1 text-xs text-gray-400">To change your login email, visit account settings.</p>
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                            <input id="phone" name="phone" type="tel"
                                   value="{{ old('phone', $jobSeeker->phone) }}"
                                   placeholder="+1 (868) 000-0000"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

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

            {{-- Upload default resume --}}
            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
                <h3 class="font-semibold text-xl mb-1">Default Resume</h3>
                <p class="text-sm text-gray-500 mb-5">
                    This resume is used as the default for quick applications. You can still upload a custom resume for specific jobs.
                </p>

                <form x-data="{ loading: false }"
                      method="POST"
                      action="{{ route('jobseeker.profile.resume.upload') }}"
                      enctype="multipart/form-data">
                    @csrf
                    <label class="block cursor-pointer" :class="loading ? 'opacity-50 pointer-events-none' : ''">
                        <input type="file"
                               name="resume"
                               accept=".pdf,.doc,.docx"
                               class="sr-only"
                               @change="loading = true; $el.closest('form').submit()">
                        <div class="flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 hover:border-[#6f4cb2]/40 hover:bg-violet-50/30 transition-all p-4 text-sm text-gray-500">
                            <x-heroicon-o-document-arrow-up class="w-4 h-4 shrink-0" />
                            <span x-show="!loading">{{ $jobSeeker->resume_path ? 'Replace resume (PDF, DOC, DOCX)' : 'Upload resume (PDF, DOC, DOCX)' }}</span>
                            <span x-show="loading" x-cloak>Uploading…</span>
                        </div>
                    </label>
                    @error('resume')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </form>
            </div>
        </div>
    </div>

    {{-- Compliance Documents --}}
    <div class="mt-6 rounded-3xl bg-white p-8 shadow border border-gray-100">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
                <h3 class="text-xl font-semibold text-gray-900">Compliance Documents</h3>
                <p class="mt-1 text-sm text-gray-500">Upload the required identity, compliance, and qualification documents for your profile.</p>
            </div>

            @php
                $uploadedCount = $jobSeeker->documents
                    ->whereNotIn('document_type', [\App\Models\JobSeekerDocument::TYPE_PROFILE_PHOTO])
                    ->pluck('document_type')->unique()->count();
                $totalCount = count(\App\Models\JobSeekerDocument::TYPES) - 1;
            @endphp

            <x-likeslocale.status-pill :tone="$uploadedCount === $totalCount ? 'success' : 'warning'">
                {{ $uploadedCount }} / {{ $totalCount }} documents uploaded
            </x-likeslocale.status-pill>
        </div>

        @php $docsByType = $jobSeeker->documents->groupBy('document_type'); @endphp

        <div class="mt-8 space-y-8">
            @foreach(\App\Models\JobSeekerDocument::CATEGORIES as $categoryLabel => $types)
                <div>
                    <h4 class="text-sm font-semibold uppercase tracking-[0.16em] text-gray-400 mb-4">{{ $categoryLabel }}</h4>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($types as $type)
                            @php
                                $docs      = $docsByType[$type] ?? collect();
                                $isMulti   = in_array($type, \App\Models\JobSeekerDocument::MULTI_UPLOAD_TYPES);
                                $hasAny    = $docs->isNotEmpty();
                                $label     = \App\Models\JobSeekerDocument::labelFor($type);
                                $accept    = \App\Models\JobSeekerDocument::acceptAttrFor($type);
                            @endphp

                            <div class="rounded-2xl border p-5 {{ $hasAny ? 'border-green-200 bg-green-50/40' : 'border-gray-200 bg-gray-50/60' }}">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <p class="text-sm font-semibold text-gray-900 leading-snug">{{ $label }}</p>

                                    @if($hasAny)
                                        <x-likeslocale.status-pill tone="success">
                                            {{ $isMulti ? $docs->count() . ' file' . ($docs->count() > 1 ? 's' : '') : 'Uploaded' }}
                                        </x-likeslocale.status-pill>
                                    @else
                                        <x-likeslocale.status-pill tone="neutral">Not uploaded</x-likeslocale.status-pill>
                                    @endif
                                </div>

                                {{-- Existing files --}}
                                @if($hasAny)
                                    <div class="space-y-2 mb-3">
                                        @foreach($docs as $doc)
                                            <div class="flex items-center justify-between gap-2 rounded-xl bg-white border border-green-100 px-3 py-2">
                                                <div class="min-w-0">
                                                    <a href="{{ asset('storage/' . $doc->file_path) }}"
                                                       target="_blank"
                                                       class="text-xs font-medium text-[#6f4cb2] hover:underline truncate block max-w-[140px]"
                                                       title="{{ $doc->original_name }}">
                                                        {{ $doc->original_name ?: 'View document' }}
                                                    </a>
                                                    <p class="text-xs text-gray-400">{{ $doc->uploaded_at?->format('M d, Y') }}</p>
                                                </div>
                                                <form method="POST"
                                                      action="{{ route('jobseeker.documents.destroy', $doc) }}"
                                                      onsubmit="return confirm('Remove this file?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-400 hover:text-red-600 shrink-0">
                                                        <x-heroicon-o-x-mark class="w-4 h-4" />
                                                    </button>
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Upload button: always shown for multi, or when empty for single --}}
                                @if($isMulti || !$hasAny)
                                    <form x-data="{ loading: false }"
                                          method="POST"
                                          action="{{ route('jobseeker.documents.store') }}"
                                          enctype="multipart/form-data">
                                        @csrf
                                        <input type="hidden" name="document_type" value="{{ $type }}">
                                        <label class="block cursor-pointer" :class="loading ? 'opacity-50 pointer-events-none' : ''">
                                            <input type="file" name="file" accept="{{ $accept }}" class="sr-only"
                                                   @change="loading = true; $el.closest('form').submit()">
                                            <div class="flex items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-gray-200 hover:border-[#6f4cb2]/40 hover:bg-violet-50/30 bg-white/60 transition-all p-2.5 text-xs text-gray-500">
                                                <x-heroicon-o-arrow-up-tray class="w-3.5 h-3.5 shrink-0" />
                                                <span x-show="!loading">{{ $isMulti && $hasAny ? 'Add another' : 'Upload' }}</span>
                                                <span x-show="loading" x-cloak>Uploading…</span>
                                            </div>
                                        </label>
                                    </form>
                                @else
                                    {{-- Single-upload replace --}}
                                    <form x-data="{ loading: false }"
                                          method="POST"
                                          action="{{ route('jobseeker.documents.store') }}"
                                          enctype="multipart/form-data">
                                        @csrf
                                        <input type="hidden" name="document_type" value="{{ $type }}">
                                        <label class="block cursor-pointer" :class="loading ? 'opacity-50 pointer-events-none' : ''">
                                            <input type="file" name="file" accept="{{ $accept }}" class="sr-only"
                                                   @change="loading = true; $el.closest('form').submit()">
                                            <div class="flex items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-green-200 hover:border-green-400 bg-white/60 transition-all p-2.5 text-xs text-gray-500">
                                                <x-heroicon-o-arrow-up-tray class="w-3.5 h-3.5 shrink-0" />
                                                <span x-show="!loading">Replace</span>
                                                <span x-show="loading" x-cloak>Uploading…</span>
                                            </div>
                                        </label>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.portal>
