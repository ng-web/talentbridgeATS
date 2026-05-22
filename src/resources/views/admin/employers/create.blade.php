<x-layouts.portal :title="'Add Employer / Sponsor'" heading="Add Employer / Sponsor" subheading="Create an employer or sponsor account and send login details automatically." portalRole="admin">
    <div class="max-w-4xl">
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <h3 class="text-xl font-semibold text-gray-900">Provision Employer / Sponsor</h3>
            <p class="mt-1 text-sm text-gray-500">The system will generate a temporary password and email the new user their login details.</p>

            <form method="POST" action="{{ route('admin.employers.store') }}" class="mt-6 space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('name')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="company_name" class="block text-sm font-medium text-gray-700">Company / Sponsor Name</label>
                        <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('company_name')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="contact_person" class="block text-sm font-medium text-gray-700">Contact Person</label>
                        <input id="contact_person" name="contact_person" type="text" value="{{ old('contact_person') }}" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('contact_person')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="industry" class="block text-sm font-medium text-gray-700">Industry</label>
                        <input id="industry" name="industry" type="text" value="{{ old('industry') }}" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                        @error('industry')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="website" class="block text-sm font-medium text-gray-700">Website</label>
                        <input
                            id="website"
                            name="website"
                            type="text"
                            value="{{ old('website') }}"
                            placeholder="www.company.com or https://www.company.com"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm"
                        >
                        <p class="mt-2 text-xs text-gray-500">
                            Optional. You can leave this blank if the company or sponsor does not have a website.
                        </p>
                        @error('website')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="company_description" class="block text-sm font-medium text-gray-700">Company Description</label>
                    <textarea id="company_description" name="company_description" rows="4" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">{{ old('company_description') }}</textarea>
                    @error('company_description')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="grant_access_now" value="1" @checked(old('grant_access_now'))>
                        <span class="text-sm text-gray-700">Grant employer posting access immediately</span>
                    </label>

                    <div class="mt-4">
                        <label for="plan_id" class="block text-sm font-medium text-gray-700">Employer Plan</label>
                        <select id="plan_id" name="plan_id" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                            <option value="">Select plan</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" @selected(old('plan_id') == $plan->id)>
                                    {{ $plan->name }} — {{ $plan->currency }} {{ number_format((float) $plan->amount, 2) }}
                                </option>
                            @endforeach
                        </select>
                        @error('plan_id')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-likeslocale.button type="submit" variant="accent">
                        Create Employer / Sponsor
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('admin.entitlements.index')" variant="secondary">
                        Back
                    </x-likeslocale.button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.portal>