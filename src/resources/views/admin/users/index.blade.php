<x-layouts.portal :title="'Users'" heading="Users" subheading="Manage accounts, access, and payment visibility from one place." portalRole="admin">
    <div class="space-y-6">
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">Search & Filter Users</h3>
                    <p class="mt-1 text-sm text-gray-500">Find employers, job seekers, and admins by identity, company, access, or password status.</p>
                </div>

                <div>
                    <x-likeslocale.button :href="route('admin.employers.create')" variant="accent">
                        Add Employer / Sponsor
                    </x-likeslocale.button>
                </div>
            </div>

            <form method="GET" action="{{ route('admin.users.index') }}" class="mt-4 flex flex-col sm:flex-row sm:flex-wrap xl:flex-nowrap items-center gap-3">
                <input
                    id="q"
                    name="q"
                    type="text"
                    value="{{ $filters['q'] ?? '' }}"
                    placeholder="Search by name, email, or company"
                    class="flex-1 min-w-0 w-full sm:w-auto rounded-2xl border-gray-300 shadow-sm"
                >

                <select id="role" name="role" class="w-full sm:w-36 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All roles</option>
                    <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Admin</option>
                    <option value="employer" @selected(($filters['role'] ?? '') === 'employer')>Employer</option>
                    <option value="job_seeker" @selected(($filters['role'] ?? '') === 'job_seeker')>Job Seeker</option>
                </select>

                <select id="access" name="access" class="w-full sm:w-40 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All access</option>
                    <option value="active" @selected(($filters['access'] ?? '') === 'active')>Has Active Access</option>
                    <option value="inactive" @selected(($filters['access'] ?? '') === 'inactive')>No Active Access</option>
                </select>

                <select id="password_change" name="password_change" class="w-full sm:w-48 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All users</option>
                    <option value="yes" @selected(($filters['password_change'] ?? '') === 'yes')>Must Change Password</option>
                    <option value="no" @selected(($filters['password_change'] ?? '') === 'no')>Password Changed</option>
                </select>

                <div class="flex gap-2 shrink-0 w-full sm:w-auto">
                    <x-likeslocale.button type="submit" variant="accent">
                        Apply
                    </x-likeslocale.button>
                    <a href="{{ route('admin.users.index') }}">
                        <x-likeslocale.button type="button" variant="secondary">
                            Reset
                        </x-likeslocale.button>
                    </a>
                </div>
            </form>
        </div>

        @if($users->isEmpty())
            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
                <h3 class="text-xl font-semibold text-gray-900">No users found</h3>
                <p class="mt-2 text-gray-500">Try adjusting your filters or add a new employer/sponsor account.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($users as $user)
                        @php
                            $roleLabel = $user->primaryRoleLabel();
                            $companyName = $user->employer?->company_name;
                            $accessLabel = $user->accessSummaryLabel();
                            $accessTone = $user->accessSummaryTone();
                            $latestPaymentLabel = $user->latestPaymentLabel();
                            $latestPaymentTone = $user->latestPaymentTone();
                        @endphp

                        <x-likeslocale.operation-row>
                            <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-gray-900">{{ $user->name }}</p>

                                        <x-likeslocale.status-pill tone="brand">
                                            {{ $roleLabel }}
                                        </x-likeslocale.status-pill>

                                        <x-likeslocale.status-pill :tone="$accessTone">
                                            {{ $accessLabel }}
                                        </x-likeslocale.status-pill>

                                        <x-likeslocale.status-pill :tone="$latestPaymentTone">
                                            {{ $latestPaymentLabel }}
                                        </x-likeslocale.status-pill>

                                        @if($user->must_change_password)
                                            <x-likeslocale.status-pill tone="warning">
                                                Must Change Password
                                            </x-likeslocale.status-pill>
                                        @endif
                                    </div>

                                    <div class="border-t border-gray-100 mt-3 pt-2.5 space-y-1">
                                        <p class="text-sm text-gray-500">{{ $user->email }}</p>

                                        <div class="text-sm text-gray-600 flex flex-wrap gap-x-4 gap-y-1">
                                            @if($companyName)
                                                <span><span class="font-medium text-gray-900">Company:</span> {{ $companyName }}</span>
                                            @endif

                                            <span><span class="font-medium text-gray-900">Created:</span> {{ $user->created_at?->format('M d, Y') }}</span>

                                            @if($user->latestPaymentRecord())
                                                <span>
                                                    <span class="font-medium text-gray-900">Latest Payment:</span>
                                                    {{ $user->latestPaymentRecord()->currency }}
                                                    {{ number_format((float) $user->latestPaymentRecord()->amount, 2) }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-3 xl:shrink-0">
                                    <x-likeslocale.button :href="route('admin.users.show', $user)" variant="accent">
                                        View User
                                    </x-likeslocale.button>

                                    <x-likeslocale.button :href="route('admin.entitlements.index', ['q' => $user->email])" variant="info">
                                        Access
                                    </x-likeslocale.button>

                                    <x-likeslocale.button :href="route('admin.payments.index', ['q' => $user->email])" variant="success">
                                        Payments
                                    </x-likeslocale.button>
                                </div>
                            </div>
                        </x-likeslocale.operation-row>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</x-layouts.portal>