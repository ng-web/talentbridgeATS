<x-layouts.portal :title="'Edit Job'" heading="Edit Job Listing" subheading="Update your existing listing. It will be returned for review on save." portalRole="employer">
    <div class="max-w-5xl">
        <form method="POST" action="{{ route('employer.jobs.update', $job) }}" class="space-y-6">
            @csrf
            @method('PATCH')

            {{-- Basic Details --}}
            <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 mb-5">Basic Details</h3>
                <div class="space-y-5">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">Job Title <span class="text-red-500">*</span></label>
                        <input id="title" name="title" type="text" value="{{ old('title', $job->title) }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm" required>
                        @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="program_id" class="block text-sm font-medium text-gray-700">Program</label>
                            <select id="program_id" name="program_id" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                                <option value="">No program</option>
                                @foreach($programs as $program)
                                    <option value="{{ $program->id }}" @selected(old('program_id', $job->program_id) == $program->id)>{{ $program->name }}</option>
                                @endforeach
                            </select>
                            @error('program_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="listing_type" class="block text-sm font-medium text-gray-700">Listing Type <span class="text-red-500">*</span></label>
                            <select id="listing_type" name="listing_type" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm" required>
                                <option value="">Select programme type</option>
                                @foreach(\App\Models\Job::LISTING_TYPE_LABELS as $value => $label)
                                    <option value="{{ $value }}" @selected(old('listing_type', $job->listing_type) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('listing_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="employment_type" class="block text-sm font-medium text-gray-700">Employment Type</label>
                            <select id="employment_type" name="employment_type" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                                <option value="">Select type</option>
                                @foreach($employmentTypes as $type)
                                    <option value="{{ $type }}" @selected(old('employment_type', $job->employment_type) === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('employment_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                            <select id="category" name="category" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                                <option value="">Select category</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}" @selected(old('category', $job->category) === $cat)>{{ $cat }}</option>
                                @endforeach
                            </select>
                            @error('category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Location & Schedule --}}
            <script>const __jobLocations = @json($locations);</script>
            <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100"
                 x-data="{
                     country: '{{ old('country', $job->country) }}',
                     allLocations: __jobLocations,
                     get filteredLocations() { return this.allLocations[this.country] ?? []; }
                 }">
                <h3 class="text-lg font-semibold text-gray-900 mb-5">Location & Schedule</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="country" class="block text-sm font-medium text-gray-700">Country <span class="text-red-500">*</span></label>
                        <select id="country" name="country" x-model="country" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm" required>
                            <option value="">Select country</option>
                            @foreach($countries as $c)
                                <option value="{{ $c->name }}" @selected(old('country', $job->country) === $c->name)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        @error('country')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                        <select id="location" name="location" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                            <option value="">Select location</option>
                            <template x-for="loc in filteredLocations" :key="loc">
                                <option :value="loc" :selected="loc === '{{ old('location', $job->location) }}'" x-text="loc"></option>
                            </template>
                        </select>
                        @error('location')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="duration" class="block text-sm font-medium text-gray-700">Duration</label>
                        <input id="duration" name="duration" type="text" value="{{ old('duration', $job->duration) }}"
                            placeholder="e.g. 12 months, Summer 2025"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('duration')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="application_deadline" class="block text-sm font-medium text-gray-700">Application Deadline</label>
                        <input id="application_deadline" name="application_deadline" type="date"
                            value="{{ old('application_deadline', optional($job->application_deadline)->format('Y-m-d')) }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('application_deadline')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="inline-flex items-center gap-2 mt-2">
                            <input type="checkbox" name="remote_flag" value="1" @checked(old('remote_flag', $job->remote_flag))>
                            <span class="text-sm text-gray-700">Remote / Work from home</span>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Compensation --}}
            <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Compensation</h3>
                <p class="text-sm text-gray-500 mb-5">Leave blank if not applicable or to be discussed.</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label for="salary_min" class="block text-sm font-medium text-gray-700">Salary Min</label>
                        <input id="salary_min" name="salary_min" type="number" value="{{ old('salary_min', $job->salary_min) }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('salary_min')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="salary_max" class="block text-sm font-medium text-gray-700">Salary Max</label>
                        <input id="salary_max" name="salary_max" type="number" value="{{ old('salary_max', $job->salary_max) }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('salary_max')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="fees" class="block text-sm font-medium text-gray-700">Programme Fees</label>
                        <input id="fees" name="fees" type="number" value="{{ old('fees', $job->fees) }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('fees')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Description Sections --}}
            <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100 space-y-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Listing Content</h3>
                    <p class="text-sm text-gray-500">Each section appears as its own block on the listing. Use a new line for each point.</p>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Overview <span class="text-red-500">*</span></label>
                    <p class="mt-0.5 text-xs text-gray-400">Briefly describe the role and what the candidate can expect.</p>
                    <textarea id="description" name="description" rows="5"
                        class="mt-2 block w-full rounded-2xl border-gray-300 shadow-sm" required>{{ old('description', $job->description) }}</textarea>
                    @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="responsibilities" class="block text-sm font-medium text-gray-700">Key Responsibilities</label>
                    <p class="mt-0.5 text-xs text-gray-400">List the main duties. Put each responsibility on its own line.</p>
                    <textarea id="responsibilities" name="responsibilities" rows="6"
                        class="mt-2 block w-full rounded-2xl border-gray-300 shadow-sm">{{ old('responsibilities', $job->responsibilities) }}</textarea>
                    @error('responsibilities')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="eligibility" class="block text-sm font-medium text-gray-700">Eligibility & Requirements</label>
                    <p class="mt-0.5 text-xs text-gray-400">Who can apply? Include age range, visa requirements, language skills, etc.</p>
                    <textarea id="eligibility" name="eligibility" rows="5"
                        class="mt-2 block w-full rounded-2xl border-gray-300 shadow-sm">{{ old('eligibility', $job->eligibility) }}</textarea>
                    @error('eligibility')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="benefits" class="block text-sm font-medium text-gray-700">Benefits & Perks</label>
                    <p class="mt-0.5 text-xs text-gray-400">What's included — housing, meals, stipend, travel, training, etc.</p>
                    <textarea id="benefits" name="benefits" rows="5"
                        class="mt-2 block w-full rounded-2xl border-gray-300 shadow-sm">{{ old('benefits', $job->benefits) }}</textarea>
                    @error('benefits')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <x-likeslocale.button type="submit">Save & Resubmit for Review</x-likeslocale.button>
                <x-likeslocale.button :href="route('employer.jobs.index')" variant="outline">Back to Jobs</x-likeslocale.button>
            </div>

        </form>
    </div>
</x-layouts.portal>
