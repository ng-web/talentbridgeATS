<x-layouts.portal
    :title="$user->name"
    :heading="$user->name"
    subheading="Manage account access, payments, entitlements, and operational activity."
    portalRole="admin"
>
    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded-3xl border border-green-200 bg-green-50 p-5 text-green-900 shadow-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-3xl border border-red-200 bg-red-50 p-5 text-red-900 shadow-sm">
                {{ session('error') }}
            </div>
        @endif

        @if(session('provisioned_credentials'))
            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6 shadow">
                <h3 class="text-lg font-semibold text-amber-900">Temporary Credentials</h3>

                <p class="mt-2 text-sm text-amber-800">
                    Email could not be sent. Copy these credentials now and share them securely.
                </p>

                <div class="mt-4 rounded-2xl bg-white/70 border border-amber-200 p-4 text-sm text-amber-900 space-y-2">
                    <p>
                        <span class="font-semibold">Email:</span>
                        {{ session('provisioned_credentials.email') }}
                    </p>

                    <p>
                        <span class="font-semibold">Temporary Password:</span>
                        {{ session('provisioned_credentials.temporary_password') }}
                    </p>
                </div>
            </div>
        @endif

        @php
            $role = $user->roles->first()?->name;
            $roleLabel = match($role) {
                'admin' => 'Administrator',
                'employer' => 'Employer',
                'job_seeker' => 'Job Seeker',
                default => 'User',
            };

            $roleTone = match($role) {
                'admin' => 'danger',
                'employer' => 'brand',
                'job_seeker' => 'success',
                default => 'neutral',
            };

            $activeEntitlement = $activeEntitlements->first();

            $totalPayments = $user->payments->count();
            $successfulPayments = $user->payments->where('status', \App\Models\Payment::STATUS_PAID)->count();

            $latestPayment = $user->payments->first();
        @endphp

        <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
            <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-6">
                <div class="flex items-start gap-5">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-white text-2xl font-semibold shadow-sm bg-[#6f4cb2]">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-3xl font-bold text-gray-900">
                                {{ $user->name }}
                            </h2>

                            <x-likeslocale.status-pill :tone="$roleTone">
                                {{ $roleLabel }}
                            </x-likeslocale.status-pill>

                            @if($activeEntitlement)
                                <x-likeslocale.status-pill tone="success">
                                    Access Active
                                </x-likeslocale.status-pill>
                            @else
                                <x-likeslocale.status-pill tone="warning">
                                    Access Restricted
                                </x-likeslocale.status-pill>
                            @endif

                            @if($user->must_change_password ?? false)
                                <x-likeslocale.status-pill tone="danger">
                                    Password Change Required
                                </x-likeslocale.status-pill>
                            @endif
                        </div>

                        <p class="mt-2 text-gray-600">
                            {{ $user->email }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-gray-500">
                            <span>
                                <span class="font-medium text-gray-700">Joined:</span>
                                {{ $user->created_at?->format('M d, Y') }}
                            </span>

                            <span>
                                <span class="font-medium text-gray-700">Payments:</span>
                                {{ $totalPayments }}
                            </span>

                            <span>
                                <span class="font-medium text-gray-700">Successful:</span>
                                {{ $successfulPayments }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-likeslocale.button :href="route('admin.users.index')" variant="secondary">
                        Back to Users
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('admin.entitlements.index', ['q' => $user->email])">
                        View Entitlements
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('admin.payments.index', ['q' => $user->email])" variant="accent">
                        View Payments
                    </x-likeslocale.button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="space-y-6">
                <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
                    <h3 class="text-xl font-semibold text-gray-900">
                        Identity & Security
                    </h3>

                    <div class="mt-5 divide-y divide-gray-100 text-sm">
                        <div class="py-3 first:pt-0 last:pb-0">
                            <p class="font-medium text-gray-900">Account Role</p>
                            <p class="mt-1 text-gray-600">{{ $roleLabel }}</p>
                        </div>

                        <div class="py-3 first:pt-0 last:pb-0">
                            <p class="font-medium text-gray-900">Email Address</p>
                            <p class="mt-1 text-gray-600 break-all">{{ $user->email }}</p>
                        </div>

                        <div class="py-3 first:pt-0 last:pb-0">
                            <p class="font-medium text-gray-900">Password Change Required</p>

                            <div class="mt-1">
                                @if($user->must_change_password ?? false)
                                    <x-likeslocale.status-pill tone="danger">
                                        Required
                                    </x-likeslocale.status-pill>
                                @else
                                    <x-likeslocale.status-pill tone="success">
                                        Not Required
                                    </x-likeslocale.status-pill>
                                @endif
                            </div>
                        </div>

                        <div class="py-3 first:pt-0 last:pb-0">
                            <p class="font-medium text-gray-900">Last Updated</p>
                            <p class="mt-1 text-gray-600">
                                {{ $user->updated_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>

                        <div class="mt-6 border-t border-gray-100 pt-5">
                        <h4 class="text-base font-semibold text-gray-900">
                            Credential Actions
                        </h4>

                        <p class="mt-1 text-sm text-gray-500">
                            Use these actions when a user did not receive login details or needs a forced password reset.
                        </p>

                        <div class="mt-4 space-y-3">
                            <form method="POST"
                                  action="{{ route('admin.users.issue-temporary-password', $user) }}"
                                  onsubmit="return confirm('Issue a new temporary password for {{ addslashes($user->name) }}? The user will be required to change it on login.');">
                                @csrf

                                <x-likeslocale.button
                                    type="submit"
                                    variant="accent"
                                    class="w-full justify-center"
                                >
                                    Issue Temporary Password
                                </x-likeslocale.button>
                            </form>

                            @if(!($user->must_change_password ?? false))
                                <form method="POST"
                                      action="{{ route('admin.users.force-password-change', $user) }}"
                                      onsubmit="return confirm('Force {{ addslashes($user->name) }} to change their password on next login?');">
                                    @csrf
                                    @method('PATCH')

                                    <x-likeslocale.button
                                        type="submit"
                                        variant="secondary"
                                        class="w-full justify-center"
                                    >
                                        Force Password Change
                                    </x-likeslocale.button>
                                </form>
                            @else
                                <form method="POST"
                                      action="{{ route('admin.users.clear-password-change', $user) }}"
                                      onsubmit="return confirm('Clear the password change requirement for {{ addslashes($user->name) }}?');">
                                    @csrf
                                    @method('PATCH')

                                    <x-likeslocale.button
                                        type="submit"
                                        variant="secondary"
                                        class="w-full justify-center"
                                    >
                                        Clear Password Requirement
                                    </x-likeslocale.button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 border-t border-red-100 pt-5">
                        <h4 class="text-base font-semibold text-red-700">
                            Danger Zone
                        </h4>

                        <p class="mt-1 text-sm text-gray-500">
                            Move this user to the recycle bin. Their records are preserved and the account can be restored later.
                        </p>

                        <form method="POST"
                              action="{{ route('admin.users.destroy', $user) }}"
                              class="mt-4"
                              onsubmit="return confirm('Move {{ addslashes($user->name) }} to the recycle bin? They can be restored later.');">
                            @csrf
                            @method('DELETE')

                            <x-likeslocale.button type="submit" variant="secondary" class="w-full justify-center">
                                Move to Recycle Bin
                            </x-likeslocale.button>
                        </form>
                    </div>
                </div>

                @if($role === 'employer' && $user->employer)
                    <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
                        <h3 class="text-xl font-semibold text-gray-900">
                            Employer Profile
                        </h3>

                        <div class="mt-5 divide-y divide-gray-100 text-sm">
                            <div class="py-3 first:pt-0 last:pb-0">
                                <p class="font-medium text-gray-900">Company Name</p>
                                <p class="mt-1 text-gray-600">
                                    {{ $user->employer->company_name ?: 'Not provided' }}
                                </p>
                            </div>

                            <div class="py-3 first:pt-0 last:pb-0">
                                <p class="font-medium text-gray-900">Industry</p>
                                <p class="mt-1 text-gray-600">
                                    {{ $user->employer->industry ?: 'Not provided' }}
                                </p>
                            </div>

                            <div class="py-3 first:pt-0 last:pb-0">
                                <p class="font-medium text-gray-900">Website</p>
                                <p class="mt-1 text-gray-600 break-all">
                                    {{ $user->employer->website ?: 'Not provided' }}
                                </p>
                            </div>

                            <div class="py-3 first:pt-0 last:pb-0">
                                <p class="font-medium text-gray-900">Contact Person</p>
                                <p class="mt-1 text-gray-600">
                                    {{ $user->employer->contact_person ?: 'Not provided' }}
                                </p>
                            </div>

                            <div class="py-3 first:pt-0 last:pb-0">
                                <p class="font-medium text-gray-900">Notification Email</p>
                                <p class="mt-1 text-gray-600 break-all">
                                    {{ $user->employer->notificationEmail() ?: 'Not provided' }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                @if($role === 'job_seeker' && $user->jobSeeker)
                    <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
                        <h3 class="text-xl font-semibold text-gray-900">
                            Job Seeker Profile
                        </h3>

                        <div class="mt-5 divide-y divide-gray-100 text-sm">
                            <div class="py-3 first:pt-0 last:pb-0">
                                <p class="font-medium text-gray-900">Profile Completion</p>

                                <div class="mt-2 w-full bg-gray-200 rounded-full h-3">
                                    <div
                                        class="bg-violet-600 h-3 rounded-full"
                                        style="width: {{ $user->jobSeeker->profile_completeness ?? 0 }}%;"
                                    ></div>
                                </div>

                                <p class="mt-2 text-gray-600">
                                    {{ $user->jobSeeker->profile_completeness ?? 0 }}%
                                </p>
                            </div>

                            <div class="py-3 first:pt-0 last:pb-0">
                                <p class="font-medium text-gray-900">Location</p>
                                <p class="mt-1 text-gray-600">
                                    {{ $user->jobSeeker->location ?: 'Not provided' }}
                                </p>
                            </div>

                            <div class="py-3 first:pt-0 last:pb-0">
                                <p class="font-medium text-gray-900">Resume</p>

                                @if($user->jobSeeker->resume_path)
                                    <a
                                        href="{{ asset('storage/'.$user->jobSeeker->resume_path) }}"
                                        target="_blank"
                                        class="mt-1 inline-flex text-[#6f4cb2] hover:underline"
                                    >
                                        View Resume
                                    </a>
                                @else
                                    <p class="mt-1 text-gray-500">Not uploaded</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="xl:col-span-2 space-y-6">
                <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">
                                Access & Entitlements
                            </h3>

                            <p class="mt-1 text-sm text-gray-500">
                                Current and historical platform access records.
                            </p>
                        </div>

                        <a
                            href="{{ route('admin.entitlements.index', ['q' => $user->email]) }}"
                            class="text-sm font-medium text-[#6f4cb2] hover:underline"
                        >
                            Manage
                        </a>
                    </div>

                    <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <h4 class="text-base font-semibold text-gray-900">Grant Access</h4>
                        <p class="mt-1 text-sm text-gray-500">
                            Grant access directly to this user without leaving the user detail page.
                        </p>

                        <form method="POST" action="{{ route('admin.users.grant-access', $user) }}" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                            @csrf

                            <div>
                                <label for="type" class="block text-sm font-medium text-gray-700">Access Type</label>
                                <select id="type" name="type" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                                    @foreach(\App\Models\Entitlement::TYPES as $type)
                                        <option value="{{ $type }}">
                                            {{ \App\Models\Entitlement::typeLabelFor($type) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="expires_at" class="block text-sm font-medium text-gray-700">Expires At</label>
                                <input
                                    id="expires_at"
                                    name="expires_at"
                                    type="date"
                                    value="{{ now()->addMonth()->format('Y-m-d') }}"
                                    class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm"
                                >
                            </div>

                            <div class="flex items-end">
                                <x-likeslocale.button type="submit" variant="accent" class="w-full justify-center">
                                    Grant Access
                                </x-likeslocale.button>
                            </div>

                            <div class="md:col-span-3">
                                <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows="2"
                                    class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm"
                                    placeholder="Optional admin note"
                                ></textarea>
                            </div>
                        </form>
                    </div>

                    @if($user->entitlements->isEmpty())
                        <div class="mt-6 rounded-2xl bg-gray-50 border border-gray-100 p-5 text-sm text-gray-500 text-center">
                            No entitlements found for this user.
                        </div>
                    @else
                        <div class="mt-6 space-y-3">
                            @foreach($user->entitlements as $entitlement)
                                <div class="rounded-2xl border border-gray-200 p-5">
                                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-semibold text-gray-900">
                                                    {{ \App\Models\Entitlement::typeLabelFor($entitlement->type) }}
                                                </p>

                                                <x-likeslocale.status-pill :tone="\App\Models\Entitlement::toneFor($entitlement->status)">
                                                    {{ \App\Models\Entitlement::labelFor($entitlement->status) }}
                                                </x-likeslocale.status-pill>
                                            </div>

                                            <div class="mt-2 text-sm text-gray-500 flex flex-wrap gap-x-4 gap-y-1">
                                                <span>
                                                    <span class="font-medium text-gray-700">Starts:</span>
                                                    {{ $entitlement->starts_at?->format('M d, Y') ?: '—' }}
                                                </span>

                                                <span>
                                                    <span class="font-medium text-gray-700">Expires:</span>
                                                    {{ $entitlement->expires_at?->format('M d, Y') ?: 'Never' }}
                                                </span>

                                                <span>
                                                    <span class="font-medium text-gray-700">Source:</span>
                                                    {{ $entitlement->source ?: 'Manual' }}
                                                </span>
                                            </div>

                                            @if($entitlement->notes)
                                                <div class="mt-3 rounded-2xl bg-gray-50 border border-gray-100 p-3 text-sm text-gray-600">
                                                    {{ $entitlement->notes }}
                                                </div>
                                            @endif
                                        </div>

                                        <div class="shrink-0 flex flex-col items-start lg:items-end gap-3">
                                            @if($entitlement->isActive())
                                                <x-likeslocale.status-pill tone="success">
                                                    Currently Active
                                                </x-likeslocale.status-pill>

                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.users.revoke-access', [$user, $entitlement->type]) }}"
                                                    onsubmit="return confirm('Revoke {{ \App\Models\Entitlement::typeLabelFor($entitlement->type) }} for {{ addslashes($user->name) }}?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <x-likeslocale.button type="submit" variant="secondary">
                                                        Revoke Access
                                                    </x-likeslocale.button>
                                                </form>
                                            @else
                                                <x-likeslocale.status-pill tone="neutral">
                                                    Not Active
                                                </x-likeslocale.status-pill>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if($role === 'job_seeker' && $user->jobSeeker)
                    <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900">Compliance Documents</h3>
                                <p class="mt-1 text-sm text-gray-500">Documents uploaded by this job seeker.</p>
                            </div>

                            @php
                                $uploadedCount = $seekerDocuments?->count() ?? 0;
                                $totalCount = count(\App\Models\JobSeekerDocument::TYPES);
                            @endphp

                            <x-likeslocale.status-pill :tone="$uploadedCount === $totalCount ? 'success' : 'warning'">
                                {{ $uploadedCount }} / {{ $totalCount }} uploaded
                            </x-likeslocale.status-pill>
                        </div>

                        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                            @foreach(\App\Models\JobSeekerDocument::TYPES as $type)
                                @php
                                    $doc = $seekerDocuments[$type] ?? null;
                                    $label = \App\Models\JobSeekerDocument::labelFor($type);
                                @endphp

                                <div class="rounded-2xl border p-4 {{ $doc ? 'border-green-200 bg-green-50/40' : 'border-gray-100 bg-gray-50' }}">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-medium text-gray-900 leading-snug">{{ $label }}</p>

                                        @if($doc)
                                            <x-likeslocale.status-pill tone="success">Uploaded</x-likeslocale.status-pill>
                                        @else
                                            <x-likeslocale.status-pill tone="neutral">Missing</x-likeslocale.status-pill>
                                        @endif
                                    </div>

                                    @if($doc)
                                        <div class="mt-2 flex flex-wrap items-center gap-2">
                                            <a href="{{ asset('storage/' . $doc->file_path) }}"
                                               target="_blank"
                                               class="text-sm font-medium text-[#6f4cb2] hover:underline">
                                                View
                                            </a>
                                            <span class="text-xs text-gray-400">
                                                {{ $doc->uploaded_at?->format('M d, Y') }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">
                                Recent Payments
                            </h3>

                            <p class="mt-1 text-sm text-gray-500">
                                Payment and billing history for this user.
                            </p>
                        </div>

                        <a
                            href="{{ route('admin.payments.index', ['q' => $user->email]) }}"
                            class="text-sm font-medium text-[#6f4cb2] hover:underline"
                        >
                            View all
                        </a>
                    </div>

                    @if($recentPayments->isEmpty())
                        <div class="mt-6 rounded-2xl bg-gray-50 border border-gray-100 p-5 text-sm text-gray-500 text-center">
                            No payments found for this user.
                        </div>
                    @else
                        <div class="mt-6 space-y-3">
                            @foreach($recentPayments as $payment)
                                <div class="rounded-2xl border border-gray-200 p-5">
                                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="font-semibold text-gray-900">
                                                    {{ $payment->currency }}
                                                    {{ number_format((float) $payment->amount, 2) }}
                                                </p>

                                                <x-likeslocale.status-pill :tone="\App\Models\Payment::toneFor($payment->status)">
                                                    {{ \App\Models\Payment::labelFor($payment->status) }}
                                                </x-likeslocale.status-pill>
                                            </div>

                                            <div class="mt-2 text-sm text-gray-500 flex flex-wrap gap-x-4 gap-y-1">
                                                <span>
                                                    <span class="font-medium text-gray-700">Gateway:</span>
                                                    {{ strtoupper($payment->gateway) }}
                                                </span>

                                                <span>
                                                    <span class="font-medium text-gray-700">Order:</span>
                                                    {{ $payment->order_id }}
                                                </span>

                                                @if($payment->paid_at)
                                                    <span>
                                                        <span class="font-medium text-gray-700">Paid:</span>
                                                        {{ $payment->paid_at->format('M d, Y') }}
                                                    </span>
                                                @endif
                                            </div>

                                            @if($payment->plan?->name)
                                                <div class="mt-2 text-sm text-gray-600">
                                                    Plan:
                                                    <span class="font-medium">
                                                        {{ $payment->plan->name }}
                                                    </span>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="shrink-0">
                                            @if($payment->entitlement_activated_at)
                                                <x-likeslocale.status-pill tone="success">
                                                    Access Activated
                                                </x-likeslocale.status-pill>
                                            @else
                                                <x-likeslocale.status-pill tone="warning">
                                                    Pending Activation
                                                </x-likeslocale.status-pill>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.portal>
