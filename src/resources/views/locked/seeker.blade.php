<x-layouts.portal :title="'Access Required'" heading="Job Seeker Access Required" subheading="Unlock access to browse and apply for opportunities." portalRole="jobseeker">
    <div class="max-w-6xl">
        <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <x-likeslocale.status-pill tone="warning">
                    Access Locked
                </x-likeslocale.status-pill>

                <x-likeslocale.status-pill tone="brand">
                    Job Seeker
                </x-likeslocale.status-pill>
            </div>

            <h3 class="text-2xl font-semibold text-gray-900">Choose a program access option</h3>

            <p class="mt-3 text-gray-600 leading-7 max-w-3xl">
                To browse approved opportunities and submit applications, your job seeker access must be activated.
                Select the program option that matches your application path.
            </p>

            @if($plans->isNotEmpty())
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-5">
                    @foreach($plans as $plan)
                        <div class="rounded-3xl border border-gray-200 bg-gray-50 p-6 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md">
                            <h4 class="text-lg font-semibold text-gray-900">
                                {{ $plan->name }}
                            </h4>

                            <p class="mt-4 text-3xl font-bold text-[#6f4cb2]">
                                {{ $plan->currency }} {{ number_format((float) $plan->amount, 2) }}
                            </p>

                            @if($plan->duration_days)
                                <p class="mt-2 text-sm text-gray-500">
                                    Access duration: {{ $plan->duration_days }} days
                                </p>
                            @endif

                            @if((float) $plan->amount > 3000)
                            <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                This package exceeds the current online card checkout limit. Please contact Kairox Exchange to arrange payment assistance.
                            </div>

                            <div class="mt-4">
                                <x-likeslocale.button
                                    :href="route('payment-assistance.create', $plan->slug)"
                                    variant="secondary"
                                    class="w-full justify-center"
                                >
                                    Request Payment Assistance
                                </x-likeslocale.button>
                            </div>
                        @else
                            <div class="mt-6">
                                <x-likeslocale.button
                                    :href="route('payments.wipay.seeker', ['plan' => $plan->slug])"
                                    variant="accent"
                                    class="w-full justify-center"
                                >
                                    Select Plan
                                </x-likeslocale.button>
                            </div>
                        @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    No active job seeker plans are currently configured. Please contact support.
                </div>
            @endif

            <div class="mt-8 flex flex-wrap gap-3">
                <x-likeslocale.button :href="route('jobseeker.profile.edit')" variant="slate">
                    Complete Profile
                </x-likeslocale.button>

                <x-likeslocale.button :href="route('pricing')" variant="secondary">
                    View Pricing
                </x-likeslocale.button>
            </div>
        </div>
    </div>
</x-layouts.portal>