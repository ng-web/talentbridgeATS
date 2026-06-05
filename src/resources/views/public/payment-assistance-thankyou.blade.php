<x-layouts.portal :title="'Request Received'" heading="Request Received" subheading="Thank you for your interest in Kairox Exchange." portalRole="{{ auth()->check() && auth()->user()->hasRole('employer') ? 'employer' : 'jobseeker' }}">
    <div class="max-w-xl">
        <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-5" style="background:#e7f7f3;">
                <x-heroicon-o-check-circle class="w-8 h-8 text-[#50b7a4]" />
            </div>

            <h3 class="text-2xl font-bold text-gray-900">Your request has been submitted</h3>

            @if(session('program_name'))
                <div class="mt-5 rounded-2xl border border-gray-200 bg-gray-50 p-4 text-left">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Programme</p>
                    <p class="font-semibold text-gray-900">{{ session('program_name') }}</p>
                    @if(session('amount'))
                        <p class="text-[#6f4cb2] font-bold text-lg mt-1">{{ session('amount') }}</p>
                    @endif
                </div>
            @endif

            <p class="mt-5 text-gray-600 leading-7">
                A Kairox Exchange representative will contact you within <strong>1 business day</strong>
                to discuss payment arrangements and next steps. Check your inbox for a confirmation email.
            </p>

            <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                @auth
                    <x-likeslocale.button :href="route('jobseeker.dashboard')" variant="accent">
                        Go to Dashboard
                    </x-likeslocale.button>
                @endauth
                <x-likeslocale.button :href="route('pricing')" variant="slate">
                    Back to Programmes
                </x-likeslocale.button>
            </div>
        </div>
    </div>
</x-layouts.portal>
