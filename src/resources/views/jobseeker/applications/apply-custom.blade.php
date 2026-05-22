<x-layouts.portal :title="'Custom Apply'" heading="Custom Apply" subheading="Upload a custom resume and cover letter for this job." portalRole="jobseeker">
    <div class="max-w-3xl space-y-6">
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <h3 class="text-xl font-semibold text-gray-900">{{ $job->title }}</h3>

            <p class="mt-2 text-sm text-gray-500">
                Use this option when both your resume and cover letter should be tailored specifically to this role.
            </p>

            <form method="POST" action="{{ route('jobseeker.jobs.apply.custom.store', $job) }}" enctype="multipart/form-data" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="resume" class="block text-sm font-medium text-gray-700">Custom Resume</label>
                    <input
                        id="resume"
                        name="resume"
                        type="file"
                        accept=".pdf,.doc,.docx"
                        class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm"
                    >
                    <p class="mt-2 text-xs text-gray-500">
                        Upload a resume tailored for this role.
                    </p>
                    @error('resume')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="cover_letter" class="block text-sm font-medium text-gray-700">Custom Cover Letter</label>
                    <input
                        id="cover_letter"
                        name="cover_letter"
                        type="file"
                        accept=".pdf,.doc,.docx"
                        class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm"
                    >
                    <p class="mt-2 text-xs text-gray-500">
                        Upload a cover letter written specifically for this employer and role.
                    </p>
                    @error('cover_letter')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-likeslocale.button type="submit" variant="accent">
                        Submit Custom Application
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('jobseeker.jobs.apply', $job)" variant="outline">
                        Use Profile Resume Instead
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('jobseeker.jobs.show', $job)" variant="secondary">
                        Back to Job
                    </x-likeslocale.button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.portal>