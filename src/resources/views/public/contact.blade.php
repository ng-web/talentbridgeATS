<x-layouts.portal :title="'Contact Us'" heading="Contact Kairox Team" subheading="Send us a message and we'll get back to you within 1 business day." portalRole="{{ auth()->check() && auth()->user()->hasRole('employer') ? 'employer' : 'jobseeker' }}">
    <div class="max-w-2xl">
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">

            @if($errors->any())
                <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('contact.store') }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="full_name"
                               value="{{ old('full_name', $user?->name) }}"
                               required
                               class="block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('full_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" name="email"
                               value="{{ old('email', $user?->email) }}"
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp</label>
                        <input type="text" name="whatsapp"
                               value="{{ old('whatsapp') }}"
                               placeholder="+1 (876) 000-0000"
                               class="block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('whatsapp')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject <span class="text-red-500">*</span></label>
                    <select name="subject" required class="block w-full rounded-2xl border-gray-300 shadow-sm">
                        <option value="">Select a subject</option>
                        <option value="Employer Account Setup" @selected(old('subject') === 'Employer Account Setup')>Employer Account Setup</option>
                        <option value="Account Access Issue" @selected(old('subject') === 'Account Access Issue')>Account Access Issue</option>
                        <option value="Payment Enquiry" @selected(old('subject') === 'Payment Enquiry')>Payment Enquiry</option>
                        <option value="Programme Information" @selected(old('subject') === 'Programme Information')>Programme Information</option>
                        <option value="Application Support" @selected(old('subject') === 'Application Support')>Application Support</option>
                        <option value="Other" @selected(old('subject') === 'Other')>Other</option>
                    </select>
                    @error('subject')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message <span class="text-red-500">*</span></label>
                    <textarea name="message" rows="5"
                              placeholder="Tell us how we can help..."
                              required
                              class="block w-full rounded-2xl border-gray-300 shadow-sm">{{ old('message') }}</textarea>
                    @error('message')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <x-likeslocale.button type="submit" variant="accent">Send Message</x-likeslocale.button>
                    <x-likeslocale.button :href="url()->previous()" variant="slate">Go Back</x-likeslocale.button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.portal>
