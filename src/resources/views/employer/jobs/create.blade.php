<x-layouts.portal :title="'Create Job'" heading="Create Job" subheading="Add a new opportunity for admin review and publishing." portalRole="employer">
    <div class="max-w-5xl">
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <form method="POST" action="{{ route('employer.jobs.store') }}" class="space-y-6">
                @csrf

                <div>
                    <label for="program_id" class="block text-sm font-medium text-gray-700">Program</label>
                    <select id="program_id" name="program_id" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        <option value="">No program</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}" @selected(old('program_id') == $program->id)>
                                {{ $program->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('program_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}"
                        class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm" required>
                    @error('title')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="description" name="description" rows="5"
                        class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm" required>{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="listing_type" class="block text-sm font-medium text-gray-700">Listing Type</label>
                        <select id="listing_type" name="listing_type" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm" required>
                            <option value="">Select program type</option>
                            @foreach(\App\Models\Job::LISTING_TYPE_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('listing_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('listing_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="employment_type" class="block text-sm font-medium text-gray-700">Employment Type</label>
                        <input id="employment_type" name="employment_type" type="text" value="{{ old('employment_type') }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('employment_type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                        <input id="category" name="category" type="text" value="{{ old('category') }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('category')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                        <input id="location" name="location" type="text" value="{{ old('location') }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('location')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                        <input id="country" name="country" type="text" value="{{ old('country') }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('country')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="duration" class="block text-sm font-medium text-gray-700">Duration</label>
                        <input id="duration" name="duration" type="text" value="{{ old('duration') }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('duration')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="salary_min" class="block text-sm font-medium text-gray-700">Salary Min</label>
                        <input id="salary_min" name="salary_min" type="number" value="{{ old('salary_min') }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('salary_min')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="salary_max" class="block text-sm font-medium text-gray-700">Salary Max</label>
                        <input id="salary_max" name="salary_max" type="number" value="{{ old('salary_max') }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('salary_max')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="fees" class="block text-sm font-medium text-gray-700">Fees</label>
                        <input id="fees" name="fees" type="number" value="{{ old('fees') }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('fees')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="application_deadline" class="block text-sm font-medium text-gray-700">Application Deadline</label>
                        <input id="application_deadline" name="application_deadline" type="date"
                            value="{{ old('application_deadline') }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('application_deadline')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="remote_flag" value="1" @checked(old('remote_flag'))>
                        <span class="text-sm text-gray-700">Remote</span>
                    </label>
                </div>

                <div>
                    <label for="eligibility" class="block text-sm font-medium text-gray-700">Eligibility</label>
                    <textarea id="eligibility" name="eligibility" rows="4"
                        class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">{{ old('eligibility') }}</textarea>
                    @error('eligibility')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <x-likeslocale.button type="submit">
                        Create Job
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('employer.jobs.index')" variant="outline">
                        Cancel
                    </x-likeslocale.button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.portal>