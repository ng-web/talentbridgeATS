<x-layouts.portal :title="'Employer Dashboard'" :heading="'Welcome, '.auth()->user()->name" subheading="Manage your company profile, jobs, and applicants." portalRole="employer">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-likeslocale.stat-card
                    title="My Jobs"
                    :value="$jobCount"
                    :description="$jobStatusCounts->isEmpty() ? 'All current jobs created under your employer account.' : null"
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

                    @if($jobStatusCounts->isNotEmpty())
                        @php
                            $jobBreakdown = [
                                \App\Models\Job::STATUS_PUBLISHED     => 'Published',
                                \App\Models\Job::STATUS_PENDING_REVIEW => 'Pending',
                                \App\Models\Job::STATUS_ARCHIVED      => 'Archived',
                            ];
                        @endphp
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach($jobBreakdown as $st => $lbl)
                                @php $cnt = $jobStatusCounts->get($st, 0); @endphp
                                @if($cnt > 0)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                          style="background:rgba(111,76,178,0.12); color:#6f4cb2;">
                                        {{ $cnt }} {{ $lbl }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </x-likeslocale.stat-card>

                <x-likeslocale.stat-card
                    title="Active Applicants"
                    :value="$applicantCount"
                    :description="$applicantStatusCounts->isEmpty() ? 'Total applicants across all your jobs.' : null"
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

                    @if($applicantStatusCounts->isNotEmpty())
                        @php
                            $appBreakdown = [
                                \App\Models\Application::STATUS_APPLIED     => 'New',
                                \App\Models\Application::STATUS_REVIEWING   => 'Reviewing',
                                \App\Models\Application::STATUS_SHORTLISTED => 'Shortlisted',
                            ];
                        @endphp
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach($appBreakdown as $st => $lbl)
                                @php $cnt = $applicantStatusCounts->get($st, 0); @endphp
                                @if($cnt > 0)
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                          style="background:rgba(80,183,164,0.18); color:#0f766e;">
                                        {{ $cnt }} {{ $lbl }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </x-likeslocale.stat-card>
            </div>

            {{-- Quick Actions --}}
            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
                <h3 class="text-2xl font-semibold">Quick Actions</h3>
                <p class="mt-1 text-gray-500">Manage your recruitment workflow efficiently.</p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-likeslocale.button :href="route('employer.company.edit')" variant="info">
                        Company Profile
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('employer.jobs.create')" variant="accent">
                        Create Job
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('employer.jobs.index')" variant="warning">
                        Manage Jobs
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('employer.applicants.index')" variant="success">
                        View Applicants
                    </x-likeslocale.button>
                </div>
            </div>

            {{-- Recent Applicants --}}
            @if($recentApplicants->isNotEmpty())
                <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Recent Applicants</h3>
                            <p class="mt-1 text-sm text-gray-500">Latest candidates across your job listings.</p>
                        </div>
                        <a href="{{ route('employer.applicants.index') }}" class="text-sm font-medium text-[#6f4cb2] hover:underline shrink-0">
                            View all
                        </a>
                    </div>

                    <div class="mt-5 space-y-3">
                        @foreach($recentApplicants as $application)
                            @php
                                $applicantName = $application->jobSeeker?->user?->name ?? 'Applicant';
                                $initial = strtoupper(mb_substr($applicantName, 0, 1));
                                $applicantTone = \App\Models\Application::toneFor($application->status);
                                $applicantLabel = \App\Models\Application::labelFor($application->status);
                                $profilePhoto = $application->jobSeeker?->documents
                                    ?->firstWhere('document_type', \App\Models\JobSeekerDocument::TYPE_PROFILE_PHOTO);
                            @endphp

                            <div class="rounded-2xl border border-gray-200 p-4">
                                <div class="flex items-center gap-3">
                                    <div class="shrink-0">
                                        @if($profilePhoto)
                                            <img src="{{ asset('storage/' . $profilePhoto->file_path) }}"
                                                 alt="{{ $applicantName }}"
                                                 class="w-9 h-9 rounded-xl object-cover border border-gray-200">
                                        @else
                                            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-sm font-semibold shadow-sm bg-[#6f4cb2]">
                                                {{ $initial }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-gray-900 truncate">{{ $applicantName }}</p>
                                            <x-likeslocale.status-pill :tone="$applicantTone">
                                                {{ $applicantLabel }}
                                            </x-likeslocale.status-pill>
                                        </div>
                                        <p class="mt-0.5 text-sm text-gray-500 truncate">
                                            {{ $application->job?->title ?? 'Job removed' }}
                                            @if($application->applied_at)
                                                <span class="mx-2 text-gray-300">|</span>{{ $application->applied_at->format('M d, Y') }}
                                            @endif
                                        </p>
                                    </div>

                                    <a href="{{ route('employer.applicants.index') }}" class="text-sm font-medium text-[#6f4cb2] hover:underline shrink-0">
                                        Review
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Recruitment Workflow: shown until employer has jobs and applicants --}}
            @if($jobCount === 0 || $applicantCount === 0)
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
            @endif
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
                            <p class="mt-1 text-sm text-gray-500">{{ $employer->industry }}</p>
                        @endif
                    </div>

                    <div class="w-14 h-14 rounded-3xl flex items-center justify-center shadow-sm shrink-0" style="background:#efe8fb; color:#6f4cb2;">
                        <x-heroicon-o-building-office-2 class="w-7 h-7" />
                    </div>
                </div>

                @if($companyCompletion < 100)
                    @php
                        $missingFields = collect([
                            !$employer->company_name        ? 'Company name'   : null,
                            !$employer->industry            ? 'Industry'       : null,
                            !$employer->logo_path           ? 'Logo'           : null,
                            !$employer->website             ? 'Website'        : null,
                            !$employer->company_description ? 'Description'    : null,
                            !$employer->contact_person      ? 'Contact person' : null,
                            !$employer->contact_email       ? 'Contact email'  : null,
                        ])->filter()->values();
                    @endphp

                    @if($missingFields->isNotEmpty())
                        <div class="mt-4 rounded-2xl border border-amber-100 bg-amber-50 p-3">
                            <p class="text-xs font-semibold text-amber-800 uppercase tracking-wide mb-1.5">Missing</p>
                            <p class="text-sm text-amber-700">{{ $missingFields->join(', ') }}</p>
                        </div>
                    @endif
                @endif

                <div class="mt-5">
                    <x-likeslocale.button :href="route('employer.company.edit')" variant="info">
                        Update Profile
                    </x-likeslocale.button>
                </div>
            </div>

            {{-- Access Status --}}
            <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
                <div class="flex items-center gap-2 mb-4">
                    <x-heroicon-o-shield-check class="w-5 h-5 text-[#50b7a4]" />
                    <h3 class="text-base font-semibold text-gray-900">Account Access</h3>
                </div>

                @if($entitlement && $entitlement->status === \App\Models\Entitlement::STATUS_ACTIVE)
                    <x-likeslocale.status-pill tone="success">Active</x-likeslocale.status-pill>
                    <p class="mt-3 text-sm text-gray-600">
                        Your employer access is active and managed by the Kairox team.
                    </p>
                    @if($entitlement->expires_at)
                        <p class="mt-2 text-xs text-gray-400">
                            Expires {{ $entitlement->expires_at->format('M d, Y') }}
                        </p>
                    @endif
                @else
                    <x-likeslocale.status-pill tone="warning">Pending</x-likeslocale.status-pill>
                    <p class="mt-3 text-sm text-gray-600">
                        Your access is pending activation by the Kairox team.
                    </p>
                    <div class="mt-4">
                        <a href="{{ route('contact') }}"
                           class="text-sm font-medium text-[#50b7a4] hover:underline">
                            Contact us to activate →
                        </a>
                    </div>
                @endif
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
