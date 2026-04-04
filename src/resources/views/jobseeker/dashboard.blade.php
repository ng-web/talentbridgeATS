<x-layouts.portal :title="'Job Seeker Dashboard'" :heading="'Welcome, '.auth()->user()->name" subheading="Manage your profile, applications, and opportunities." portalRole="jobseeker">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-likeslocale.progress-card
                    title="Profile Completion"
                    :percent="$jobSeeker->profile_completeness"
                    :description="$jobSeeker->profile_completeness >= 100
                        ? 'Your profile is complete and ready for applications.'
                        : 'Complete your details to strengthen your applications.'"
                    bg="#efe8fb"
                    border="#d8caee"
                    valueColor="#6f4cb2"
                    titleColor="#6f4cb2"
                    trackColor="rgba(255,255,255,0.6)"
                    fillColor="#6f4cb2"
                />

                <x-likeslocale.stat-card
                    title="Applications Submitted"
                    :value="$applicationCount"
                    description="Track all your submitted applications in one place."
                    bg="#e7f7f3"
                    border="#bfe9df"
                    valueColor="#50b7a4"
                    titleColor="#0f766e"
                    chartColor="rgba(80,183,164,0.30)"
                    activityLabel="Recent Activity"
                >
                    <x-slot:icon>
                        <x-heroicon-o-document-text class="w-5 h-5" />
                    </x-slot:icon>
                </x-likeslocale.stat-card>
            </div>

            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
                <div>
                    <h3 class="text-2xl font-semibold">Quick Actions</h3>
                    <p class="mt-1 text-gray-500">Move quickly through your most important tasks.</p>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-likeslocale.button :href="route('jobseeker.profile.edit')" variant="slate">
                        My Profile
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('jobseeker.jobs.index')">
                        Browse Jobs
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('jobseeker.applications.index')" variant="accent">
                        My Applications
                    </x-likeslocale.button>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-2xl bg-gray-50 p-5 border border-gray-100">
                    <div class="w-10 h-10 rounded-2xl flex items-center justify-center mb-4" style="background:#efe8fb; color:#6f4cb2;">
                        <x-heroicon-o-user-circle class="w-5 h-5" />
                    </div>
                    <p class="text-sm font-semibold text-gray-900">Step 1</p>
                    <p class="mt-2 text-sm text-gray-600">Complete your profile information.</p>
                </div>

                <div class="rounded-2xl bg-gray-50 p-5 border border-gray-100">
                    <div class="w-10 h-10 rounded-2xl flex items-center justify-center mb-4" style="background:#e7f7f3; color:#50b7a4;">
                        <x-heroicon-o-document-arrow-up class="w-5 h-5" />
                    </div>
                    <p class="text-sm font-semibold text-gray-900">Step 2</p>
                    <p class="mt-2 text-sm text-gray-600">Upload your resume and cover letter.</p>
                </div>

                <div class="rounded-2xl bg-gray-50 p-5 border border-gray-100">
                    <div class="w-10 h-10 rounded-2xl flex items-center justify-center mb-4" style="background:#edf2f6; color:#6d8290;">
                        <x-heroicon-o-briefcase class="w-5 h-5" />
                    </div>
                    <p class="text-sm font-semibold text-gray-900">Step 3</p>
                    <p class="mt-2 text-sm text-gray-600">Browse approved opportunities and apply.</p>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <x-likeslocale.stat-card
                title="Published Opportunities"
                :value="$availableJobsCount"
                description="Current approved opportunities available to explore."
                bg="#edf2f6"
                border="#cfd8df"
                valueColor="#6d8290"
                titleColor="#5d7380"
                chartColor="rgba(109,130,144,0.30)"
                activityLabel="Opportunity Flow"
            >
                <x-slot:icon>
                    <x-heroicon-o-briefcase class="w-5 h-5" />
                </x-slot:icon>
            </x-likeslocale.stat-card>

            <x-likeslocale.info-card
                title="Profile Tips"
                bg="#efe8fb"
                border="#d8caee"
                iconBg="rgba(111,76,178,0.14)"
                iconColor="#6f4cb2"
            >
                <x-slot:icon>
                    <x-heroicon-o-light-bulb class="w-6 h-6" />
                </x-slot:icon>

                @if(($jobSeeker->profile_completeness ?? 0) >= 100)
                    Your profile is complete. Keep your documents and details current so you can apply quickly.
                @else
                    Adding education, experience, and documents improves your profile completion and helps you apply faster.
                @endif

                <div class="mt-5">
                    <x-likeslocale.button :href="route('jobseeker.profile.edit')" variant="accent">
                        Update Profile
                    </x-likeslocale.button>
                </div>
            </x-likeslocale.info-card>
        </div>
    </div>
</x-layouts.portal>