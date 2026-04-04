<x-layouts.portal :title="'Posting Access Required'" heading="Employer Posting Access Required" subheading="Unlock access to create and manage job listings." portalRole="employer">
    <div class="max-w-3xl">
        <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <x-likeslocale.status-pill tone="warning">
                    Access Locked
                </x-likeslocale.status-pill>

                <x-likeslocale.status-pill tone="brand">
                    Employer
                </x-likeslocale.status-pill>
            </div>

            <h3 class="text-2xl font-semibold text-gray-900">Your employer posting access is not active</h3>
            <p class="mt-3 text-gray-600 leading-7">
                To create and manage job listings, your employer posting entitlement must be activated.
            </p>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-likeslocale.button :href="route('payments.wipay.employer')" variant="accent">
                    Activate Access
                </x-likeslocale.button>

                <x-likeslocale.button :href="route('employer.company.edit')" variant="slate">
                    Update Company Profile
                </x-likeslocale.button>

                <x-likeslocale.button :href="route('pricing')">
                    View Pricing
                </x-likeslocale.button>
            </div>
        </div>
    </div>
</x-layouts.portal>