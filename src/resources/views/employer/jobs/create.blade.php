<x-layouts.portal :title="'Create Job'" heading="Create Job Listing" subheading="Complete each section — this is exactly how it will appear to applicants." portalRole="employer">
    <div class="max-w-5xl">
        <form method="POST" action="{{ route('employer.jobs.store') }}" class="space-y-6">
            @csrf

            {{-- Basic Details --}}
            <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 mb-5">Basic Details</h3>
                <div class="space-y-5">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">Job Title <span class="text-red-500">*</span></label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}"
                            placeholder="e.g. Au Pair — Host Family in New York"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm" required>
                        @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="program_id" class="block text-sm font-medium text-gray-700">Program</label>
                            <select id="program_id" name="program_id" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                                <option value="">No program</option>
                                @foreach($programs as $program)
                                    <option value="{{ $program->id }}" @selected(old('program_id') == $program->id)>{{ $program->name }}</option>
                                @endforeach
                            </select>
                            @error('program_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="listing_type" class="block text-sm font-medium text-gray-700">Listing Type <span class="text-red-500">*</span></label>
                            <select id="listing_type" name="listing_type" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm" required>
                                <option value="">Select programme type</option>
                                @foreach(\App\Models\Job::LISTING_TYPE_LABELS as $value => $label)
                                    <option value="{{ $value }}" @selected(old('listing_type') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('listing_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="employment_type" class="block text-sm font-medium text-gray-700">Employment Type</label>
                            <select id="employment_type" name="employment_type" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                                <option value="">Select type</option>
                                @foreach($employmentTypes as $type)
                                    <option value="{{ $type }}" @selected(old('employment_type') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('employment_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                            <select id="category" name="category" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                                <option value="">Select category</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
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
                     country: '{{ old('country') }}',
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
                                <option value="{{ $c->name }}" @selected(old('country') === $c->name)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                        @error('country')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                        <select id="location" name="location" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                            <option value="">Select location</option>
                            <template x-for="loc in filteredLocations" :key="loc">
                                <option :value="loc" :selected="loc === '{{ old('location') }}'" x-text="loc"></option>
                            </template>
                        </select>
                        @error('location')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="duration" class="block text-sm font-medium text-gray-700">Duration</label>
                        <input id="duration" name="duration" type="text" value="{{ old('duration') }}"
                            placeholder="e.g. 12 months, Summer 2025"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('duration')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="application_deadline" class="block text-sm font-medium text-gray-700">Application Deadline</label>
                        <input id="application_deadline" name="application_deadline" type="date"
                            value="{{ old('application_deadline') }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('application_deadline')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="inline-flex items-center gap-2 mt-2">
                            <input type="checkbox" name="remote_flag" value="1" @checked(old('remote_flag'))>
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
                        <input id="salary_min" name="salary_min" type="number" value="{{ old('salary_min') }}"
                            placeholder="0" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('salary_min')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="salary_max" class="block text-sm font-medium text-gray-700">Salary Max</label>
                        <input id="salary_max" name="salary_max" type="number" value="{{ old('salary_max') }}"
                            placeholder="0" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('salary_max')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="fees" class="block text-sm font-medium text-gray-700">Programme Fees</label>
                        <input id="fees" name="fees" type="number" value="{{ old('fees') }}"
                            placeholder="0" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
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
                    <label for="description" class="block text-sm font-medium text-gray-700">
                        Overview <span class="text-red-500">*</span>
                    </label>
                    <p class="mt-0.5 text-xs text-gray-400">Briefly describe the role and what the candidate can expect day-to-day.</p>
                    <textarea id="description" name="description" rows="5"
                        placeholder="e.g. We are looking for a caring and energetic Au Pair to join our family in New York City. You will provide childcare for two children aged 4 and 7..."
                        class="mt-2 block w-full rounded-2xl border-gray-300 shadow-sm" required>{{ old('description') }}</textarea>
                    @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="responsibilities" class="block text-sm font-medium text-gray-700">Key Responsibilities</label>
                    <p class="mt-0.5 text-xs text-gray-400">List the main duties. Put each responsibility on its own line.</p>
                    <textarea id="responsibilities" name="responsibilities" rows="6"
                        placeholder="Supervise and engage children during after-school hours&#10;Prepare light meals and snacks&#10;Assist with homework and educational activities&#10;Maintain a clean and safe environment&#10;Accompany children to activities and appointments"
                        class="mt-2 block w-full rounded-2xl border-gray-300 shadow-sm">{{ old('responsibilities') }}</textarea>
                    @error('responsibilities')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="eligibility" class="block text-sm font-medium text-gray-700">Eligibility & Requirements</label>
                    <p class="mt-0.5 text-xs text-gray-400">Who can apply? Include age range, visa requirements, language skills, certifications, etc.</p>
                    <textarea id="eligibility" name="eligibility" rows="5"
                        placeholder="Between 18–26 years of age&#10;Valid J-1 visa eligibility&#10;Conversational English required&#10;Minimum 200 hours of childcare experience&#10;No criminal record"
                        class="mt-2 block w-full rounded-2xl border-gray-300 shadow-sm">{{ old('eligibility') }}</textarea>
                    @error('eligibility')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="benefits" class="block text-sm font-medium text-gray-700">Benefits & Perks</label>
                    <p class="mt-0.5 text-xs text-gray-400">What's included — housing, meals, stipend, travel, training, etc.</p>
                    <textarea id="benefits" name="benefits" rows="5"
                        placeholder="Private room and board provided by host family&#10;Weekly stipend of USD $195.75&#10;Educational allowance of USD $500 per year&#10;Health insurance coverage&#10;Round-trip airfare reimbursement"
                        class="mt-2 block w-full rounded-2xl border-gray-300 shadow-sm">{{ old('benefits') }}</textarea>
                    @error('benefits')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <x-likeslocale.button type="submit">Submit for Review</x-likeslocale.button>
                <x-likeslocale.button :href="route('employer.jobs.index')" variant="outline">Cancel</x-likeslocale.button>
            </div>

        </form>
    </div>
</x-layouts.portal>
