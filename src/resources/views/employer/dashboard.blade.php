<x-layouts.portal :title="'Employer Dashboard'" :heading="'Welcome, '.auth()->user()->name" subheading="Manage your company profile, jobs, and applicants." portalRole="employer">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-likeslocale.stat-card
                    title="My Jobs"
                    :value="$jobCount"
                    description="All current jobs created under your employer account."
                    bg="#efe8fb"
                    border="#d8caee"
                    valueColor="#6f4cb2"
                    titleColor="#6f4cb2"
                    chartColor="rgba(111,76,178,0.28)"
                    activityLabel="Job Activity"
                >
                    <x-slot:icon>
                        <x-heroicon-o-briefcase class="w-5 h-5" />
                    </x-slot:icon>
                </x-likeslocale.stat-card>

                <x-likeslocale.stat-card
                    title="Total Applicants"
                    :value="$applicantCount"
                    description="Track all applicants submitted to your jobs."
                    bg="#e7f7f3"
                    border="#bfe9df"
                    valueColor="#50b7a4"
                    titleColor="#0f766e"
                    chartColor="rgba(80,183,164,0.30)"
                    activityLabel="Applicant Flow"
                >
                    <x-slot:icon>
                        <x-heroicon-o-users class="w-5 h-5" />
                    </x-slot:icon>
                </x-likeslocale.stat-card>
            </div>

            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
                <h3 class="text-2xl font-semibold">Quick Actions</h3>
                <p class="mt-1 text-gray-500">Manage your recruitment workflow efficiently.</p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-likeslocale.button :href="route('employer.company.edit')" variant="slate">
                        Company Profile
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('employer.jobs.create')" variant="accent">
                        Create Job
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('employer.jobs.index')" variant="secondary">
                        Manage Jobs
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('employer.applicants.index')" variant="lavender">
                        View Applicants
                    </x-likeslocale.button>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
                <h3 class="text-2xl font-semibold">Recruitment Workflow</h3>
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="rounded-2xl bg-gray-50 p-5 border border-gray-100">
                        <div class="w-10 h-10 rounded-2xl flex items-center justify-center mb-4" style="background:#efe8fb; color:#6f4cb2;">
                            <x-heroicon-o-pencil-square class="w-5 h-5" />
                        </div>
                        <p class="text-sm font-semibold text-gray-900">Step 1</p>
                        <p class="mt-2 text-sm text-gray-600">Create and submit your job listing.</p>
                    </div>

                    <div class="rounded-2xl bg-gray-50 p-5 border border-gray-100">
                        <div class="w-10 h-10 rounded-2xl flex items-center justify-center mb-4" style="background:#e7f7f3; color:#50b7a4;">
                            <x-heroicon-o-check-badge class="w-5 h-5" />
                        </div>
                        <p class="text-sm font-semibold text-gray-900">Step 2</p>
                        <p class="mt-2 text-sm text-gray-600">Wait for admin approval and publishing.</p>
                    </div>

                    <div class="rounded-2xl bg-gray-50 p-5 border border-gray-100">
                        <div class="w-10 h-10 rounded-2xl flex items-center justify-center mb-4" style="background:#edf2f6; color:#6d8290;">
                            <x-heroicon-o-inbox-stack class="w-5 h-5" />
                        </div>
                        <p class="text-sm font-semibold text-gray-900">Step 3</p>
                        <p class="mt-2 text-sm text-gray-600">Review applicants and update statuses.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <x-likeslocale.progress-card
                title="Company Profile Completion"
                :percent="$companyCompletion"
                :description="$companyCompletion >= 100
                    ? 'Your company profile is complete and ready for applicants.'
                    : 'Complete your company details to build trust and improve presentation.'"
                bg="#edf2f6"
                border="#cfd8df"
                valueColor="#6d8290"
                titleColor="#5d7380"
                trackColor="rgba(255,255,255,0.7)"
                fillColor="#6d8290"
            />

            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900">Company Snapshot</h3>
                        <p class="mt-4 text-gray-900 font-medium">
                            {{ $employer->company_name ?: 'Company profile not completed yet.' }}
                        </p>

                        @if($employer->industry)
                            <p class="mt-2 text-sm text-gray-500">{{ $employer->industry }}</p>
                        @endif
                    </div>

                    <div class="w-14 h-14 rounded-3xl flex items-center justify-center shadow-sm" style="background:#efe8fb; color:#6f4cb2;">
                        <x-heroicon-o-building-office-2 class="w-7 h-7" />
                    </div>
                </div>

                <div class="mt-6">
                    <x-likeslocale.button :href="route('employer.company.edit')">
                        Update Profile
                    </x-likeslocale.button>
                </div>
            </div>

            <x-likeslocale.info-card
                title="Recommendation"
                bg="rgba(111,76,178,0.08)"
                border="rgba(111,76,178,0.15)"
                iconBg="rgba(111,76,178,0.14)"
                iconColor="#6f4cb2"
            >
                <x-slot:icon>
                    <x-heroicon-o-bell-alert class="w-6 h-6" />
                </x-slot:icon>

                @if(($companyCompletion ?? 0) >= 100)
                    Your company profile is complete. Keep it current so applicants and administrators always see up-to-date information.
                @else
                    Complete your company profile before posting multiple jobs. It improves trust and platform presentation.
                @endif
            </x-likeslocale.info-card>
        </div>
    </div>
</x-layouts.portal>