<x-layouts.portal :title="'Pricing'" heading="Pricing" subheading="Choose the access level that fits your role." portalRole="{{ auth()->check() && auth()->user()->hasRole('admin') ? 'admin' : (auth()->check() && auth()->user()->hasRole('employer') ? 'employer' : 'jobseeker') }}">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-3xl p-8 shadow border" style="background:#efe8fb; border-color:#d8caee;">
            <div class="flex items-center gap-3 mb-4">
                <x-likeslocale.status-pill tone="brand">Job Seeker</x-likeslocale.status-pill>
            </div>

            <h3 class="text-2xl font-semibold text-gray-900">Job Seeker Access</h3>
            <p class="mt-2 text-gray-600">
                Browse approved opportunities, apply to jobs, and manage your applications from your dashboard.
            </p>

            <div class="mt-6">
                <p class="text-4xl font-bold text-[#6f4cb2]">JMD 7,500</p>
                <p class="mt-1 text-sm text-gray-500">Monthly access example for pilot phase.</p>
            </div>

            <ul class="mt-6 space-y-2 text-sm text-gray-700">
                <li>Browse published opportunities</li>
                <li>Apply to jobs and work-study listings</li>
                <li>Track submitted applications</li>
                <li>Manage profile and documents</li>
            </ul>

            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    <x-likeslocale.button :href="route('payments.wipay.seeker')" variant="accent">
                        Pay for Access
                    </x-likeslocale.button>

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

        <div class="rounded-3xl p-8 shadow border" style="background:#e7f7f3; border-color:#bfe9df;">
            <div class="flex items-center gap-3 mb-4">
                <x-likeslocale.status-pill tone="success">Employer</x-likeslocale.status-pill>
            </div>

            <h3 class="text-2xl font-semibold text-gray-900">Employer Posting Access</h3>
            <p class="mt-2 text-gray-600">
                Create listings, manage company information, and review applicants through your employer dashboard.
            </p>

            <div class="mt-6">
                <p class="text-4xl font-bold text-[#50b7a4]">JMD 15,000</p>
                <p class="mt-1 text-sm text-gray-500">Monthly access example for pilot phase.</p>
            </div>

            <ul class="mt-6 space-y-2 text-sm text-gray-700">
                <li>Create and manage job listings</li>
                <li>Upload company branding</li>
                <li>Review applicants and update statuses</li>
                <li>Work within admin moderation workflow</li>
            </ul>

            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    <x-likeslocale.button :href="route('payments.wipay.employer')" variant="accent">
                        Activate Access
                    </x-likeslocale.button>

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