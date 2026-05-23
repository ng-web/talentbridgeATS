<x-layouts.portal :title="'Pricing'" heading="Pricing" subheading="Choose the access level that fits your role." portalRole="{{ auth()->check() && auth()->user()->hasRole('admin') ? 'admin' : (auth()->check() && auth()->user()->hasRole('employer') ? 'employer' : 'jobseeker') }}">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Job Seeker --}}
        <div class="rounded-3xl p-8 shadow border" style="background:#efe8fb; border-color:#d8caee;">
            <div class="flex items-center gap-3 mb-4">
                <x-likeslocale.status-pill tone="brand">Job Seeker</x-likeslocale.status-pill>
            </div>

            <h3 class="text-2xl font-semibold text-gray-900">Job Seeker Access</h3>
            <p class="mt-2 text-gray-600">
                Browse approved opportunities, apply to jobs, and manage your applications from your personal dashboard.
            </p>

            <div class="mt-6">
                @if($seekerPlan)
                    <p class="text-4xl font-bold text-[#6f4cb2]">
                        {{ $seekerPlan->currency }} {{ number_format((float) $seekerPlan->amount, 2) }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500">Per month &middot; {{ $seekerPlan->duration_days }}-day access period</p>
                @else
                    <p class="text-xl font-semibold text-[#6f4cb2]">Contact us for pricing</p>
                @endif
            </div>

            <ul class="mt-6 space-y-2.5 text-sm text-gray-700">
                @foreach([
                    'Browse all published job and work-study opportunities',
                    'Apply directly to listings with your profile documents',
                    'Track application status across all submissions',
                    'Manage your profile, resume, and compliance documents',
                ] as $feature)
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-check-circle class="w-4 h-4 mt-0.5 shrink-0 text-[#6f4cb2]" />
                        {{ $feature }}
                    </li>
                @endforeach
            </ul>

            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    @if($seekerPlan)
                        <x-likeslocale.button :href="route('payments.wipay.seeker')" variant="accent">
                            Activate Access
                        </x-likeslocale.button>
                    @endif
                    <x-likeslocale.button :href="route('jobseeker.dashboard')" variant="slate">
                        Dashboard
                    </x-likeslocale.button>
                @else
                    <x-likeslocale.button :href="route('register')" variant="accent">
                        Get Started
                    </x-likeslocale.button>
                    <x-likeslocale.button :href="route('login')" variant="slate">
                        Sign In
                    </x-likeslocale.button>
                @endauth
            </div>
        </div>

        {{-- Employer --}}
        <div class="rounded-3xl p-8 shadow border" style="background:#e7f7f3; border-color:#bfe9df;">
            <div class="flex items-center gap-3 mb-4">
                <x-likeslocale.status-pill tone="success">Employer</x-likeslocale.status-pill>
            </div>

            <h3 class="text-2xl font-semibold text-gray-900">Employer Posting Access</h3>
            <p class="mt-2 text-gray-600">
                Post job listings, manage your company profile, and review applicants through your employer dashboard.
            </p>

            <div class="mt-6">
                @if($employerPlan)
                    <p class="text-4xl font-bold text-[#50b7a4]">
                        {{ $employerPlan->currency }} {{ number_format((float) $employerPlan->amount, 2) }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500">Per month &middot; {{ $employerPlan->duration_days }}-day access period</p>
                @else
                    <p class="text-xl font-semibold text-[#50b7a4]">Contact us for pricing</p>
                @endif
            </div>

            <ul class="mt-6 space-y-2.5 text-sm text-gray-700">
                @foreach([
                    'Create and manage job and work-study listings',
                    'Upload company logo and manage your brand profile',
                    'Review applicants, view documents, and update statuses',
                    'All postings reviewed by our moderation team before going live',
                ] as $feature)
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-check-circle class="w-4 h-4 mt-0.5 shrink-0 text-[#50b7a4]" />
                        {{ $feature }}
                    </li>
                @endforeach
            </ul>

            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    @if($employerPlan)
                        <x-likeslocale.button :href="route('payments.wipay.employer')" variant="accent">
                            Activate Access
                        </x-likeslocale.button>
                    @endif
                    <x-likeslocale.button :href="route('employer.dashboard')" variant="slate">
                        Dashboard
                    </x-likeslocale.button>
                @else
                    <x-likeslocale.button :href="route('register')" variant="accent">
                        Get Started
                    </x-likeslocale.button>
                    <x-likeslocale.button :href="route('login')" variant="slate">
                        Sign In
                    </x-likeslocale.button>
                @endauth
            </div>
        </div>
    </div>
</x-layouts.portal>
