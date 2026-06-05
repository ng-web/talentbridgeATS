<x-layouts.portal :title="'Pricing'" heading="Programs & Pricing" subheading="Choose the programme that best fits your goals." portalRole="{{ auth()->check() && auth()->user()->hasRole('admin') ? 'admin' : (auth()->check() && auth()->user()->hasRole('employer') ? 'employer' : 'jobseeker') }}">

    {{-- Job Seeker Programs --}}
    <div class="mb-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-1">Job Seeker Programmes</h2>
        <p class="text-sm text-gray-500">One-time programme fee. Access is granted for 12 months from activation.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-10">
        @foreach($seekerPlans as $plan)
            @php
                $meta     = is_array($plan->meta) ? $plan->meta : [];
                $features = $meta['features'] ?? [];
                $desc     = $meta['description'] ?? '';
                $isPremium = ($meta['package'] ?? '') === 'premium';
            @endphp

            <div class="rounded-3xl p-8 shadow border flex flex-col {{ $isPremium ? 'ring-2 ring-[#6f4cb2]' : '' }}"
                 style="background:#efe8fb; border-color:#d8caee;">

                <div class="flex items-center gap-3 mb-4">
                    <x-likeslocale.status-pill tone="brand">Job Seeker</x-likeslocale.status-pill>
                    
                </div>

                <h3 class="text-xl font-semibold text-gray-900">{{ $plan->name }}</h3>

                @if($desc)
                    <p class="mt-2 text-sm text-gray-600">{{ $desc }}</p>
                @endif

                <div class="mt-5">
                    <p class="text-4xl font-bold text-[#6f4cb2]">
                        {{ $plan->currency }} {{ number_format((float) $plan->amount, 0) }}
                    </p>
                    <p class="mt-1 text-sm text-gray-500">One-time fee &middot; 12-month access</p>
                </div>

                @if(!empty($features))
                    <ul class="mt-6 space-y-2.5 text-sm text-gray-700 flex-1">
                        @foreach($features as $feature)
                            <li class="flex items-start gap-2">
                                <x-heroicon-o-check-circle class="w-4 h-4 mt-0.5 shrink-0 text-[#6f4cb2]" />
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                @php $requiresAssistance = $plan->currency === 'USD' && $plan->amount >= 3000; @endphp

                <div class="mt-8 flex flex-wrap gap-3">
                    @if($requiresAssistance)
                        <x-likeslocale.button
                            :href="route('payment-assistance.create', $plan->slug)"
                            variant="accent">
                            Request Payment Assistance
                        </x-likeslocale.button>
                        <p class="w-full text-xs text-gray-400 mt-1">
                            This programme requires a payment arrangement — our team will contact you within 1 business day.
                        </p>
                    @else
                        @auth
                            <x-likeslocale.button
                                :href="route('payments.wipay.seeker', $plan->slug)"
                                variant="accent">
                                Apply Now
                            </x-likeslocale.button>
                        @else
                            <x-likeslocale.button :href="route('register')" variant="accent">
                                Get Started
                            </x-likeslocale.button>
                            <x-likeslocale.button :href="route('login')" variant="slate">
                                Sign In
                            </x-likeslocale.button>
                        @endauth
                    @endif
                </div>
            </div>
        @endforeach

        @if($seekerPlans->isEmpty())
            <div class="md:col-span-2 xl:col-span-3 rounded-3xl p-8 shadow border" style="background:#efe8fb; border-color:#d8caee;">
                <p class="text-xl font-semibold text-[#6f4cb2]">Contact us for programme pricing</p>
            </div>
        @endif
    </div>

    {{-- Employer Section --}}
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-1">Sponsors & Employers</h2>
        <p class="text-sm text-gray-500">Employer accounts are onboarded directly by the Kairox team.</p>
    </div>

    <div class="rounded-3xl p-8 shadow border max-w-2xl" style="background:#e7f7f3; border-color:#bfe9df;">
        <div class="flex items-center gap-3 mb-4">
            <x-likeslocale.status-pill tone="success">Employer</x-likeslocale.status-pill>
        </div>

        <h3 class="text-2xl font-semibold text-gray-900">Employer & Sponsor Access</h3>
        <p class="mt-3 text-gray-600 leading-relaxed">
            Post job listings, manage your company profile, and review applicants.
            Employer accounts are set up and managed directly by our team — no self-registration required.
        </p>

        <ul class="mt-6 space-y-2.5 text-sm text-gray-700">
            @foreach([
                'Create and manage job and work-study listings',
                'Upload company logo and manage your brand profile',
                'Review applicants, view documents, and update statuses',
                'All postings reviewed by our team before going live',
            ] as $feature)
                <li class="flex items-start gap-2">
                    <x-heroicon-o-check-circle class="w-4 h-4 mt-0.5 shrink-0 text-[#50b7a4]" />
                    {{ $feature }}
                </li>
            @endforeach
        </ul>

        <div class="mt-8">
            <p class="text-sm text-gray-500">
                Interested in listing opportunities on Kairox Exchange?
                <a href="{{ route('contact') }}" class="text-[#50b7a4] font-medium hover:underline">Contact our team</a>
                to get set up.
            </p>
        </div>
    </div>

</x-layouts.portal>
