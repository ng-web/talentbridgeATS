<x-layouts.portal :title="'Message Sent'" heading="Message Sent" subheading="We'll be in touch shortly." portalRole="{{ auth()->check() && auth()->user()->hasRole('employer') ? 'employer' : 'jobseeker' }}">
    <div class="max-w-xl">
        <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
            <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-5" style="background:#e7f7f3;">
                <x-heroicon-o-check-circle class="w-8 h-8 text-[#50b7a4]" />
            </div>

            <h3 class="text-2xl font-bold text-gray-900">Your message has been sent</h3>

            @if(session('subject'))
                <p class="mt-3 text-gray-500 text-sm">Re: <span class="font-medium text-gray-700">{{ session('subject') }}</span></p>
            @endif

            <p class="mt-5 text-gray-600 leading-7">
                A Kairox Exchange representative will respond within <strong>1 business day</strong>.
                Check your email inbox for a confirmation.
            </p>

            <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                @auth
                    @if(auth()->user()->hasRole('employer'))
                        <x-likeslocale.button :href="route('employer.dashboard')" variant="accent">Employer Dashboard</x-likeslocale.button>
                    @else
                        <x-likeslocale.button :href="route('jobseeker.dashboard')" variant="accent">My Dashboard</x-likeslocale.button>
                    @endif
                @endauth
                <x-likeslocale.button :href="route('pricing')" variant="slate">View Programmes</x-likeslocale.button>
            </div>
        </div>
    </div>
</x-layouts.portal>
