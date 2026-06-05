<x-layouts.portal :title="'Admin Dashboard'" heading="Admin Dashboard" subheading="Review jobs, payments, and access activity from one place." portalRole="admin">

    {{-- Top stat cards — clickable shortcuts to filtered views --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
        <a href="{{ route('admin.jobs.index', ['status' => \App\Models\Job::STATUS_PENDING_REVIEW]) }}"
           class="block rounded-3xl hover:ring-2 hover:ring-[#6f4cb2]/20 transition-all">
            <x-likeslocale.stat-card
                title="Pending Jobs"
                :value="$pendingJobsCount"
                description="Jobs currently awaiting moderation."
                bg="#efe8fb"
                border="#d8caee"
                valueColor="#6f4cb2"
                titleColor="#6f4cb2"
                chartColor="rgba(111,76,178,0.28)"
                activityLabel="Moderation"
            >
                <x-slot:icon>
                    <x-heroicon-o-clock class="w-5 h-5" />
                </x-slot:icon>
            </x-likeslocale.stat-card>
        </a>

        <a href="{{ route('admin.payments.index', ['status' => \App\Models\Payment::STATUS_REVIEW_REQUIRED]) }}"
           class="block rounded-3xl hover:ring-2 hover:ring-[#c3872b]/20 transition-all">
            <x-likeslocale.stat-card
                title="Payments in Review"
                :value="$reviewRequiredPaymentsCount"
                description="Payments needing admin confirmation."
                bg="#fff4e5"
                border="#f5d7a8"
                valueColor="#c3872b"
                titleColor="#9a6b1f"
                chartColor="rgba(195,135,43,0.28)"
                activityLabel="Payments"
            >
                <x-slot:icon>
                    <x-heroicon-o-credit-card class="w-5 h-5" />
                </x-slot:icon>
            </x-likeslocale.stat-card>
        </a>

        <a href="{{ route('admin.entitlements.index', ['status' => \App\Models\Entitlement::STATUS_ACTIVE]) }}"
           class="block rounded-3xl hover:ring-2 hover:ring-[#50b7a4]/20 transition-all">
            <x-likeslocale.stat-card
                title="Active Entitlements"
                :value="$activeEntitlementsCount"
                description="Users with current platform access."
                bg="#e7f7f3"
                border="#bfe9df"
                valueColor="#50b7a4"
                titleColor="#0f766e"
                chartColor="rgba(80,183,164,0.30)"
                activityLabel="Access"
            >
                <x-slot:icon>
                    <x-heroicon-o-key class="w-5 h-5" />
                </x-slot:icon>
            </x-likeslocale.stat-card>
        </a>

        <a href="{{ route('admin.entitlements.index', ['status' => \App\Models\Entitlement::STATUS_ACTIVE]) }}"
           class="block rounded-3xl hover:ring-2 hover:ring-gray-300/60 transition-all">
            <x-likeslocale.stat-card
                title="Expiring Soon"
                :value="$expiringEntitlementsCount"
                description="Entitlements expiring within 7 days."
                bg="#edf2f6"
                border="#cfd8df"
                valueColor="#6d8290"
                titleColor="#5d7380"
                chartColor="rgba(109,130,144,0.30)"
                activityLabel="Expiry"
            >
                <x-slot:icon>
                    <x-heroicon-o-bell-alert class="w-5 h-5" />
                </x-slot:icon>
            </x-likeslocale.stat-card>
        </a>
    </div>

    {{-- User growth strip --}}
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <a href="{{ route('admin.users.index', ['role' => 'job_seeker']) }}" class="block rounded-2xl border border-gray-200 bg-white p-5 shadow hover:ring-2 hover:ring-[#6f4cb2]/15 transition-all">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0" style="background:#efe8fb; color:#6f4cb2;">
                    <x-heroicon-o-user class="w-5 h-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-2xl font-bold text-gray-900">{{ $jobSeekerCount }}</p>
                    <p class="text-sm text-gray-500">Job Seekers registered</p>
                </div>
                <span class="text-sm font-medium text-[#6f4cb2] hover:underline shrink-0">Manage →</span>
            </div>
        </a>

        <a href="{{ route('admin.users.index', ['role' => 'employer']) }}" class="block rounded-2xl border border-gray-200 bg-white p-5 shadow hover:ring-2 hover:ring-[#50b7a4]/20 transition-all">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0" style="background:#e7f7f3; color:#0f766e;">
                    <x-heroicon-o-building-office-2 class="w-5 h-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-2xl font-bold text-gray-900">{{ $employerCount }}</p>
                    <p class="text-sm text-gray-500">Employers registered</p>
                </div>
                <span class="text-sm font-medium text-[#6f4cb2] hover:underline shrink-0">Manage →</span>
            </div>
        </a>
    </div>

    {{-- Payment Assistance --}}
    @if($newAssistanceRequestsCount > 0)
    <div class="mt-4">
        <a href="{{ route('admin.payment-assistance.index', ['status' => 'new']) }}"
           class="flex items-center gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow hover:ring-2 hover:ring-amber-300/40 transition-all">
            <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0" style="background:#fff4e5; color:#c3872b;">
                <x-heroicon-o-academic-cap class="w-5 h-5" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-2xl font-bold text-amber-800">{{ $newAssistanceRequestsCount }}</p>
                <p class="text-sm text-amber-700">New payment assistance {{ Str::plural('request', $newAssistanceRequestsCount) }} awaiting follow-up</p>
            </div>
            <span class="text-sm font-medium text-amber-700 hover:underline shrink-0">View Requests →</span>
        </a>
    </div>
    @endif

    {{-- Quick actions + operational note --}}
    <div class="mt-6 grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 rounded-3xl bg-white p-8 shadow border border-gray-100">
            <h3 class="text-2xl font-semibold">Admin Quick Actions</h3>
            <p class="mt-2 text-gray-500">
                Jump directly into the areas that need the most attention.
            </p>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-likeslocale.button :href="route('admin.users.index')" variant="accent">
                    Manage Users
                </x-likeslocale.button>

                <x-likeslocale.button :href="route('admin.jobs.index', ['status' => \App\Models\Job::STATUS_PENDING_REVIEW])" variant="warning">
                    Review Pending Jobs
                </x-likeslocale.button>

                <x-likeslocale.button :href="route('admin.payments.index', ['status' => \App\Models\Payment::STATUS_REVIEW_REQUIRED])" variant="success">
                    Review Payments
                </x-likeslocale.button>

                <x-likeslocale.button :href="route('admin.entitlements.index', ['status' => \App\Models\Entitlement::STATUS_ACTIVE])" variant="info">
                    View Active Access
                </x-likeslocale.button>
            </div>
        </div>

        @php
            $hasUrgentItems = $pendingJobsCount > 0 || $reviewRequiredPaymentsCount > 0 || $expiringEntitlementsCount > 0 || $unactivatedPaymentsCount > 0;
        @endphp

        <x-likeslocale.info-card
            :title="$hasUrgentItems ? 'Action Required' : 'All Clear'"
            :bg="$hasUrgentItems ? '#fff4e5' : '#efe8fb'"
            :border="$hasUrgentItems ? '#f5d7a8' : '#d8caee'"
            iconBg="rgba(111,76,178,0.14)"
            :iconColor="$hasUrgentItems ? '#c3872b' : '#6f4cb2'"
        >
            <x-slot:icon>
                @if($hasUrgentItems)
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6" />
                @else
                    <x-heroicon-o-check-circle class="w-6 h-6" />
                @endif
            </x-slot:icon>

            @if($hasUrgentItems)
                <ul class="space-y-2 text-sm">
                    @if($pendingJobsCount > 0)
                        <li>
                            <a href="{{ route('admin.jobs.index', ['status' => \App\Models\Job::STATUS_PENDING_REVIEW]) }}"
                               class="flex items-center justify-between gap-2 rounded-xl bg-amber-100/60 px-3 py-2 text-amber-900 hover:bg-amber-100 transition-colors">
                                <span>{{ $pendingJobsCount }} job{{ $pendingJobsCount > 1 ? 's' : '' }} awaiting approval</span>
                                <x-heroicon-o-arrow-right class="w-4 h-4 shrink-0" />
                            </a>
                        </li>
                    @endif
                    @if($unactivatedPaymentsCount > 0)
                        <li>
                            <a href="{{ route('admin.payments.index', ['unactivated' => 1]) }}"
                               class="flex items-center justify-between gap-2 rounded-xl bg-red-100/60 px-3 py-2 text-red-900 hover:bg-red-100 transition-colors">
                                <span>{{ $unactivatedPaymentsCount }} paid payment{{ $unactivatedPaymentsCount > 1 ? 's' : '' }} not yet activated</span>
                                <x-heroicon-o-arrow-right class="w-4 h-4 shrink-0" />
                            </a>
                        </li>
                    @endif
                    @if($reviewRequiredPaymentsCount > 0)
                        <li>
                            <a href="{{ route('admin.payments.index', ['status' => \App\Models\Payment::STATUS_REVIEW_REQUIRED]) }}"
                               class="flex items-center justify-between gap-2 rounded-xl bg-amber-100/60 px-3 py-2 text-amber-900 hover:bg-amber-100 transition-colors">
                                <span>{{ $reviewRequiredPaymentsCount }} payment{{ $reviewRequiredPaymentsCount > 1 ? 's' : '' }} need confirmation</span>
                                <x-heroicon-o-arrow-right class="w-4 h-4 shrink-0" />
                            </a>
                        </li>
                    @endif
                    @if($expiringEntitlementsCount > 0)
                        <li>
                            <a href="{{ route('admin.entitlements.index', ['expiring' => 1]) }}"
                               class="flex items-center justify-between gap-2 rounded-xl bg-amber-100/60 px-3 py-2 text-amber-900 hover:bg-amber-100 transition-colors">
                                <span>{{ $expiringEntitlementsCount }} entitlement{{ $expiringEntitlementsCount > 1 ? 's' : '' }} expiring within 7 days</span>
                                <x-heroicon-o-arrow-right class="w-4 h-4 shrink-0" />
                            </a>
                        </li>
                    @endif
                </ul>
            @else
                <p class="text-sm text-gray-700">No pending jobs, payments, or expiring entitlements. Platform is running normally.</p>
            @endif
        </x-likeslocale.info-card>
    </div>

    {{-- Bottom three-panel row --}}
    <div class="mt-6 grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Recent Pending Jobs --}}
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">Recent Pending Jobs</h3>
                    <p class="mt-1 text-sm text-gray-500">Newest jobs awaiting moderation.</p>
                </div>

                <a href="{{ route('admin.jobs.index', ['status' => \App\Models\Job::STATUS_PENDING_REVIEW]) }}" class="text-sm font-medium text-[#6f4cb2] hover:underline shrink-0">
                    View all
                </a>
            </div>

            @if($recentPendingJobs->isEmpty())
                <div class="mt-6 rounded-2xl bg-gray-50 border border-gray-100 p-5 text-sm text-gray-500 text-center">
                    No pending jobs right now.
                </div>
            @else
                <div class="mt-6 space-y-3">
                    @foreach($recentPendingJobs as $job)
                        @php
                            $companyName = $job->employer?->company_name ?: ($job->employer?->user?->name ?: 'Company');
                        @endphp

                        <div class="rounded-2xl border border-gray-200 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-gray-900">{{ $job->title }}</p>

                                        <x-likeslocale.status-pill :tone="\App\Models\Job::toneFor($job->status)">
                                            {{ \App\Models\Job::labelFor($job->status) }}
                                        </x-likeslocale.status-pill>
                                    </div>

                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ $companyName }}
                                        @if($job->location)
                                            <span class="mx-2 text-gray-300">|</span>{{ $job->location }}
                                        @endif
                                    </p>
                                </div>

                                <a href="{{ route('admin.jobs.index', ['q' => $job->title]) }}" class="text-sm font-medium text-[#6f4cb2] hover:underline shrink-0">
                                    Open
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Payments Requiring Review --}}
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">Payments Requiring Review</h3>
                    <p class="mt-1 text-sm text-gray-500">Newest payments needing confirmation.</p>
                </div>

                <a href="{{ route('admin.payments.index', ['status' => \App\Models\Payment::STATUS_REVIEW_REQUIRED]) }}" class="text-sm font-medium text-[#6f4cb2] hover:underline shrink-0">
                    View all
                </a>
            </div>

            @if($recentReviewPayments->isEmpty())
                <div class="mt-6 rounded-2xl bg-gray-50 border border-gray-100 p-5 text-sm text-gray-500 text-center">
                    No payments currently require review.
                </div>
            @else
                <div class="mt-6 space-y-3">
                    @foreach($recentReviewPayments as $payment)
                        <div class="rounded-2xl border border-gray-200 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-gray-900">
                                            {{ $payment->user?->name ?? 'Unknown User' }}
                                        </p>

                                        <x-likeslocale.status-pill :tone="\App\Models\Payment::toneFor($payment->status)">
                                            {{ \App\Models\Payment::labelFor($payment->status) }}
                                        </x-likeslocale.status-pill>
                                    </div>

                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ $payment->order_id }}
                                        @if($payment->plan?->name)
                                            <span class="mx-2 text-gray-300">|</span>{{ $payment->plan->name }}
                                        @endif
                                    </p>

                                    <p class="mt-1 text-sm text-gray-600">
                                        {{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}
                                    </p>
                                </div>

                                <a href="{{ route('admin.payments.index', ['q' => $payment->order_id]) }}" class="text-sm font-medium text-[#6f4cb2] hover:underline shrink-0">
                                    Open
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Expiring Entitlements --}}
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">Expiring Entitlements</h3>
                    <p class="mt-1 text-sm text-gray-500">Access expiring within 7 days.</p>
                </div>

                <a href="{{ route('admin.entitlements.index', ['status' => \App\Models\Entitlement::STATUS_ACTIVE]) }}" class="text-sm font-medium text-[#6f4cb2] hover:underline shrink-0">
                    View all
                </a>
            </div>

            @if($expiringEntitlements->isEmpty())
                <div class="mt-6 rounded-2xl bg-gray-50 border border-gray-100 p-5 text-sm text-gray-500 text-center">
                    No entitlements expiring this week.
                </div>
            @else
                <div class="mt-6 space-y-3">
                    @foreach($expiringEntitlements as $entitlement)
                        <div class="rounded-2xl border border-amber-100 bg-amber-50/60 p-4">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900">
                                        {{ $entitlement->user?->name ?? 'Unknown User' }}
                                    </p>
                                    <p class="mt-1 text-sm text-gray-500">
                                        {{ \App\Models\Entitlement::typeLabelFor($entitlement->type) }}
                                    </p>
                                    <p class="mt-1 text-xs font-medium text-amber-700">
                                        Expires {{ $entitlement->expires_at->format('M d, Y') }}
                                        <span class="mx-2 text-gray-300">|</span>{{ $entitlement->expires_at->diffForHumans() }}
                                    </p>
                                </div>

                                <a href="{{ route('admin.entitlements.index') }}" class="text-sm font-medium text-[#6f4cb2] hover:underline shrink-0">
                                    Manage
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.portal>
