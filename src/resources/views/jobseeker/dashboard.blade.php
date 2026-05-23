<x-layouts.portal :title="'Job Seeker Dashboard'" :heading="'Welcome, '.auth()->user()->name" subheading="Manage your profile, applications, and opportunities." portalRole="jobseeker">

    @if($jobSeeker->profile_completeness < 60)
        <div class="mb-6 rounded-3xl border border-amber-200 bg-amber-50 p-5 flex items-start gap-4">
            <div class="shrink-0 w-10 h-10 rounded-2xl flex items-center justify-center" style="background:rgba(245,158,11,0.15); color:#b45309;">
                <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-amber-900">Your profile is {{ $jobSeeker->profile_completeness }}% complete</p>
                <p class="mt-1 text-sm text-amber-700">Employers are less likely to engage with incomplete profiles. Add your education, experience, skills, and upload a default resume.</p>
                <div class="mt-3">
                    <x-likeslocale.button :href="route('jobseeker.profile.edit')" variant="warning">
                        Complete Profile
                    </x-likeslocale.button>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-likeslocale.progress-card
                    title="Profile Completion"
                    :percent="$jobSeeker->profile_completeness"
                    :description="$jobSeeker->profile_completeness >= 100
                        ? 'Your profile is complete and your default resume is ready for applications.'
                        : 'Complete your details and upload your default resume to strengthen your applications.'"
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
                    :description="$applicationStatusCounts->isEmpty() ? 'Track all your submitted applications in one place.' : null"
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

                    @if($applicationStatusCounts->isNotEmpty())
                        @php
                            $activeBreakdown = [
                                \App\Models\Application::STATUS_APPLIED     => 'Applied',
                                \App\Models\Application::STATUS_REVIEWING   => 'Reviewing',
                                \App\Models\Application::STATUS_SHORTLISTED => 'Shortlisted',
                            ];
                        @endphp
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach($activeBreakdown as $st => $lbl)
                                @php $cnt = $applicationStatusCounts->get($st, 0); @endphp
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
                <p class="mt-1 text-gray-500">Move quickly through your most important tasks.</p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-likeslocale.button :href="route('jobseeker.profile.edit')" variant="info">
                        My Profile
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('jobseeker.jobs.index')" variant="success">
                        Browse Jobs
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('jobseeker.applications.index')" variant="accent">
                        My Applications
                    </x-likeslocale.button>
                </div>
            </div>

            {{-- Recent Applications --}}
            @if($recentApplications->isNotEmpty())
                <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Recent Applications</h3>
                            <p class="mt-1 text-sm text-gray-500">Your latest application activity.</p>
                        </div>
                        <a href="{{ route('jobseeker.applications.index') }}" class="text-sm font-medium text-[#6f4cb2] hover:underline shrink-0">
                            View all
                        </a>
                    </div>

                    <div class="mt-5 space-y-3">
                        @foreach($recentApplications as $application)
                            @php
                                $appJob = $application->job;
                                $appCompany = $appJob?->employer?->company_name ?: ($appJob?->employer?->user?->name ?: 'Company');
                                $appTone = \App\Models\Application::toneFor($application->status);
                                $appLabel = \App\Models\Application::labelFor($application->status);
                            @endphp

                            <div class="rounded-2xl border border-gray-200 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-semibold text-gray-900 truncate">
                                                {{ $appJob?->title ?? 'Job no longer available' }}
                                            </p>
                                            <x-likeslocale.status-pill :tone="$appTone">
                                                {{ $appLabel }}
                                            </x-likeslocale.status-pill>
                                        </div>
                                        <p class="mt-1 text-sm text-gray-500">
                                            {{ $appCompany }}
                                            @if($application->applied_at)
                                                <span class="mx-2 text-gray-300">|</span>{{ $application->applied_at->format('M d, Y') }}
                                            @endif
                                        </p>
                                    </div>

                                    @if($appJob)
                                        <a href="{{ route('jobseeker.jobs.show', $appJob) }}" class="text-sm font-medium text-[#6f4cb2] hover:underline shrink-0">
                                            View
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- 3-step guide: shown only until profile is complete and at least one application submitted --}}
            @if($jobSeeker->profile_completeness < 100 || $applicationCount === 0)
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                        <p class="mt-2 text-sm text-gray-600">Upload your default resume and keep it current.</p>
                    </div>

                    <div class="rounded-2xl bg-gray-50 p-5 border border-gray-100">
                        <div class="w-10 h-10 rounded-2xl flex items-center justify-center mb-4" style="background:#edf2f6; color:#6d8290;">
                            <x-heroicon-o-briefcase class="w-5 h-5" />
                        </div>
                        <p class="text-sm font-semibold text-gray-900">Step 3</p>
                        <p class="mt-2 text-sm text-gray-600">Browse approved opportunities and upload a tailored cover letter when applying.</p>
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            @php
                $isActive = $entitlement?->isActive();
                $cardBg = $isActive ? '#e7f7f3' : '#fef9ec';
                $cardBorder = $isActive ? '#bfe9df' : '#fde68a';
                $iconBg = $isActive ? 'rgba(80,183,164,0.18)' : 'rgba(245,158,11,0.15)';
                $iconColor = $isActive ? '#0f766e' : '#b45309';
                $labelColor = $isActive ? '#0f766e' : '#92400e';
            @endphp

            <div class="rounded-3xl p-8 shadow border overflow-hidden" style="background:{{ $cardBg }}; border-color:{{ $cardBorder }};">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium" style="color:{{ $labelColor }};">Platform Access</p>

                        <div class="mt-3">
                            @if($isActive)
                                <x-likeslocale.status-pill tone="success">Active</x-likeslocale.status-pill>
                            @elseif($entitlement?->status === \App\Models\Entitlement::STATUS_EXPIRED)
                                <x-likeslocale.status-pill tone="warning">Expired</x-likeslocale.status-pill>
                            @elseif($entitlement?->status === \App\Models\Entitlement::STATUS_REVOKED)
                                <x-likeslocale.status-pill tone="danger">Revoked</x-likeslocale.status-pill>
                            @else
                                <x-likeslocale.status-pill tone="neutral">No Active Subscription</x-likeslocale.status-pill>
                            @endif
                        </div>

                        <p class="mt-3 text-sm leading-6 text-gray-600">
                            @if($isActive && $entitlement->expires_at)
                                Access expires {{ $entitlement->expires_at->format('M d, Y') }}.
                            @elseif($isActive)
                                Your access is active with no set expiry.
                            @else
                                Subscribe to unlock job browsing and applications.
                            @endif
                        </p>
                    </div>
                    <div class="shrink-0 w-12 h-12 rounded-2xl flex items-center justify-center shadow-sm" style="background:{{ $iconBg }}; color:{{ $iconColor }};">
                        <x-heroicon-o-shield-check class="w-5 h-5" />
                    </div>
                </div>

                <div class="mt-5">
                    @if($isActive)
                        <x-likeslocale.button :href="route('jobseeker.jobs.index')" variant="success">
                            Browse Jobs
                        </x-likeslocale.button>
                    @else
                        <x-likeslocale.button :href="route('pricing')" variant="warning">
                            Activate Access
                        </x-likeslocale.button>
                    @endif
                </div>
            </div>

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
                    Your profile is complete. Keep your default resume current, and tailor your cover letter for each application.
                @else
                    Adding education, experience, and a default resume improves your profile completion and helps you apply faster.
                @endif

                <div class="mt-5">
                    <x-likeslocale.button :href="route('jobseeker.profile.edit')" variant="info">
                        Update Profile
                    </x-likeslocale.button>
                </div>
            </x-likeslocale.info-card>
        </div>
    </div>
</x-layouts.portal>
