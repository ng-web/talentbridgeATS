<x-layouts.portal :title="'Edit Job'" heading="Edit Job" subheading="Update your existing job listing." portalRole="employer">
    <div class="max-w-5xl">
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <form method="POST" action="{{ route('employer.jobs.update', $job) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div>
                    <label for="program_id" class="block text-sm font-medium text-gray-700">Program</label>
                    <select id="program_id" name="program_id" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        <option value="">No program</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}" @selected(old('program_id', $job->program_id) == $program->id)>
                                {{ $program->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $job->title) }}"
                        class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm" required>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="description" name="description" rows="5"
                        class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm" required>{{ old('description', $job->description) }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="listing_type" class="block text-sm font-medium text-gray-700">Listing Type</label>
                        <select id="listing_type" name="listing_type" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm" required>
                            <option value="job" @selected(old('listing_type', $job->listing_type) === 'job')>Job</option>
                            <option value="work_study" @selected(old('listing_type', $job->listing_type) === 'work_study')>Work Study</option>
                        </select>
                    </div>

                    <div>
                        <label for="employment_type" class="block text-sm font-medium text-gray-700">Employment Type</label>
                        <input id="employment_type" name="employment_type" type="text" value="{{ old('employment_type', $job->employment_type) }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                        <input id="category" name="category" type="text" value="{{ old('category', $job->category) }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                        <input id="location" name="location" type="text" value="{{ old('location', $job->location) }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                        <input id="country" name="country" type="text" value="{{ old('country', $job->country) }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label for="duration" class="block text-sm font-medium text-gray-700">Duration</label>
                        <input id="duration" name="duration" type="text" value="{{ old('duration', $job->duration) }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label for="salary_min" class="block text-sm font-medium text-gray-700">Salary Min</label>
                        <input id="salary_min" name="salary_min" type="number" value="{{ old('salary_min', $job->salary_min) }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label for="salary_max" class="block text-sm font-medium text-gray-700">Salary Max</label>
                        <input id="salary_max" name="salary_max" type="number" value="{{ old('salary_max', $job->salary_max) }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label for="fees" class="block text-sm font-medium text-gray-700">Fees</label>
                        <input id="fees" name="fees" type="number" value="{{ old('fees', $job->fees) }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label for="application_deadline" class="block text-sm font-medium text-gray-700">Application Deadline</label>
                        <input id="application_deadline" name="application_deadline" type="date"
                            value="{{ old('application_deadline', optional($job->application_deadline)->format('Y-m-d')) }}"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="status" name="status" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm" required>
                            @foreach(['draft','pending_review','published','closed','archived'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $job->status) === $status)>
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="remote_flag" value="1" @checked(old('remote_flag', $job->remote_flag))>
                        <span class="text-sm text-gray-700">Remote</span>
                    </label>
                </div>

                <div>
                    <label for="eligibility" class="block text-sm font-medium text-gray-700">Eligibility</label>
                    <textarea id="eligibility" name="eligibility" rows="4"
                        class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">{{ old('eligibility', $job->eligibility) }}</textarea>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <x-likeslocale.button type="submit">
                        Update Job
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('employer.jobs.index')" variant="outline">
                        Back to Jobs
                    </x-likeslocale.button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.portal>