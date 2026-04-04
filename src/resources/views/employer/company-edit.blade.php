<x-layouts.portal :title="'Company Profile'" heading="Company Profile" subheading="Manage your employer information." portalRole="employer">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                <h3 class="text-xl font-semibold">Company Logo</h3>

                @if($employer->logo_path)
                    <div class="mt-5">
                        <img src="{{ asset('storage/'.$employer->logo_path) }}"
                             alt="Company Logo"
                             class="w-28 h-28 rounded-2xl object-cover border border-gray-200 bg-white">
                    </div>
                @else
                    <div class="mt-5 w-28 h-28 rounded-2xl flex items-center justify-center text-white text-3xl font-bold"
                         style="background:#6f4cb2;">
                        {{ strtoupper(mb_substr($employer->company_name ?: auth()->user()->name, 0, 1)) }}
                    </div>
                @endif

                <form method="POST"
                      action="{{ route('employer.company.logo.upload') }}"
                      enctype="multipart/form-data"
                      class="mt-6 space-y-4">
                    @csrf

                    <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp" class="block w-full text-sm text-gray-700">

                    @error('logo')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <x-likeslocale.button type="submit">
                        Upload Logo
                    </x-likeslocale.button>
                </form>
            </div>
        </div>

        <div class="xl:col-span-2">
            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100">
                <form method="POST" action="{{ route('employer.company.update') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="company_name" value="Company Name" />
                        <x-text-input id="company_name" name="company_name" type="text" class="mt-1 block w-full"
                            :value="old('company_name', $employer->company_name)" required />
                        <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="company_description" value="Company Description" />
                        <textarea id="company_description" name="company_description" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">{{ old('company_description', $employer->company_description) }}</textarea>
                        <x-input-error :messages="$errors->get('company_description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="industry" value="Industry" />
                        <x-text-input id="industry" name="industry" type="text" class="mt-1 block w-full"
                            :value="old('industry', $employer->industry)" />
                        <x-input-error :messages="$errors->get('industry')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="website" value="Website" />
                        <x-text-input id="website" name="website" type="url" class="mt-1 block w-full"
                            :value="old('website', $employer->website)" />
                        <x-input-error :messages="$errors->get('website')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="contact_person" value="Contact Person" />
                        <x-text-input id="contact_person" name="contact_person" type="text" class="mt-1 block w-full"
                            :value="old('contact_person', $employer->contact_person)" />
                        <x-input-error :messages="$errors->get('contact_person')" class="mt-2" />
                    </div>

                    <div>
                        <x-likeslocale.button type="submit">
                            Save Company Profile
                        </x-likeslocale.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.portal>