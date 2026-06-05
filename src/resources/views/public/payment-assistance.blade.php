<x-layouts.portal :title="'Payment Assistance'" heading="Request Payment Assistance" subheading="A Kairox representative will contact you within 1 business day." portalRole="{{ auth()->check() && auth()->user()->hasRole('employer') ? 'employer' : 'jobseeker' }}">
    <div class="max-w-2xl">
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100 mb-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0" style="background:#efe8fb;color:#6f4cb2;">
                    <x-heroicon-o-academic-cap class="w-6 h-6" />
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Selected Programme</p>
                    <h3 class="text-lg font-bold text-gray-900">{{ $plan->name }}</h3>
                    <p class="text-2xl font-bold text-[#6f4cb2] mt-1">
                        {{ $plan->currency }} {{ number_format((float) $plan->amount, 0) }}
                    </p>
                    <p class="text-sm text-gray-500 mt-1">One-time programme fee &middot; 12-month access</p>
                </div>
            </div>
        </div>

        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <p class="text-sm text-gray-600 mb-6 leading-relaxed">
                This programme requires a payment arrangement outside of our standard online checkout.
                Fill in your details below and our team will reach out to discuss payment options.
            </p>

            @if($errors->any())
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('payment-assistance.store', $plan->slug) }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="full_name"
                               value="{{ old('full_name', $user?->name) }}"
                               placeholder="Your full legal name"
                               required
                               class="block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('full_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" name="email"
                               value="{{ old('email', $user?->email) }}"
                               placeholder="your@email.com"
                               required
                               class="block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="text" name="phone"
                               value="{{ old('phone', $jobSeeker?->phone) }}"
                               placeholder="+1 (876) 000-0000"
                               class="block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number</label>
                        <input type="text" name="whatsapp"
                               value="{{ old('whatsapp') }}"
                               placeholder="+1 (876) 000-0000"
                               class="block w-full rounded-2xl border-gray-300 shadow-sm">
                        <p class="mt-1 text-xs text-gray-400">If different from your phone number</p>
                        @error('whatsapp')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
                    <textarea name="message" rows="4"
                              placeholder="Any questions or information you'd like to share with our team..."
                              class="block w-full rounded-2xl border-gray-300 shadow-sm">{{ old('message') }}</textarea>
                    @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <x-likeslocale.button type="submit" variant="accent">
                        Submit Request
                    </x-likeslocale.button>
                    <x-likeslocale.button :href="route('pricing')" variant="slate">
                        Back to Pricing
                    </x-likeslocale.button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.portal>
