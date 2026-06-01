<x-layouts.portal :title="'Access Pending'" heading="Employer Access Pending" subheading="Your account is being set up by the Kairox team." portalRole="employer">
    <div class="max-w-3xl">
        <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <x-likeslocale.status-pill tone="warning">
                    Access Pending
                </x-likeslocale.status-pill>
                <x-likeslocale.status-pill tone="brand">
                    Employer
                </x-likeslocale.status-pill>
            </div>

            <h3 class="text-2xl font-semibold text-gray-900">Your employer access is not yet active</h3>
            <p class="mt-3 text-gray-600 leading-7">
                Employer accounts on Kairox Exchange are activated by our team. If you believe your access
                should already be active, or if you'd like to get started, please reach out to us directly.
            </p>

            <div class="mt-6 rounded-2xl border border-amber-100 bg-amber-50 p-5">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-information-circle class="w-5 h-5 mt-0.5 shrink-0 text-amber-600" />
                    <p class="text-sm text-amber-800">
                        Once our team activates your account you'll have full access to post jobs,
                        manage your company profile, and review applicants.
                    </p>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <x-likeslocale.button href="mailto:info@kairox.com" variant="accent">
                    Contact Kairox Team
                </x-likeslocale.button>

                <x-likeslocale.button :href="route('employer.company.edit')" variant="slate">
                    Complete Company Profile
                </x-likeslocale.button>
            </div>
        </div>
    </div>
</x-layouts.portal>
