<x-layouts.portal :title="'Admin Dashboard'" heading="Admin Dashboard" subheading="Review jobs, payments, and access activity from one place." portalRole="admin">
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
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
    </div>

    <div class="mt-6 grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 rounded-3xl bg-white p-8 shadow border border-gray-100">
            <h3 class="text-2xl font-semibold">Admin Quick Actions</h3>
            <p class="mt-2 text-gray-500">
                Jump directly into the areas that need the most attention.
            </p>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-likeslocale.button :href="route('admin.jobs.index', ['status' => \App\Models\Job::STATUS_PENDING_REVIEW])" variant="accent">
                    Review Pending Jobs
                </x-likeslocale.button>

                <x-likeslocale.button :href="route('admin.payments.index', ['status' => \App\Models\Payment::STATUS_REVIEW_REQUIRED])">
                    Review Payments
                </x-likeslocale.button>

                <x-likeslocale.button :href="route('admin.entitlements.index', ['status' => \App\Models\Entitlement::STATUS_ACTIVE])" variant="slate">
                    View Active Access
                </x-likeslocale.button>
            </div>
        </div>

        <x-likeslocale.info-card
            title="Operational Note"
            bg="#efe8fb"
            border="#d8caee"
            iconBg="rgba(111,76,178,0.14)"
            iconColor="#6f4cb2"
        >
            <x-slot:icon>
                <x-heroicon-o-information-circle class="w-6 h-6" />
            </x-slot:icon>

            Use the dashboard for triage first, then drill into Jobs, Payments, or Entitlements for detailed actions.
        </x-likeslocale.info-card>
    </div>

    <div class="mt-6 grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">Recent Pending Jobs</h3>
                    <p class="mt-1 text-sm text-gray-500">Newest jobs awaiting moderation.</p>
                </div>

                <a href="{{ route('admin.jobs.index', ['status' => \App\Models\Job::STATUS_PENDING_REVIEW]) }}" class="text-sm font-medium text-[#6f4cb2] hover:underline">
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
                                            <span class="text-gray-400">·</span> {{ $job->location }}
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

        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">Payments Requiring Review</h3>
                    <p class="mt-1 text-sm text-gray-500">Newest payments needing confirmation.</p>
                </div>

                <a href="{{ route('admin.payments.index', ['status' => \App\Models\Payment::STATUS_REVIEW_REQUIRED]) }}" class="text-sm font-medium text-[#6f4cb2] hover:underline">
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
                                            <span class="text-gray-400">·</span> {{ $payment->plan->name }}
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
    </div>
</x-layouts.portal>