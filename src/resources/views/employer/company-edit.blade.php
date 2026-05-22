<x-layouts.portal :title="'Company Profile'" heading="Company Profile" subheading="Manage your employer information." portalRole="employer">
    @if(session('status'))
        <div class="mb-6 rounded-3xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Left: Logo --}}
        <div class="space-y-6">
            <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                <h3 class="text-xl font-semibold">Company Logo</h3>

                <div class="mt-5">
                    @if($employer->logo_path)
                        <img src="{{ asset('storage/'.$employer->logo_path) }}"
                             alt="Company Logo"
                             class="w-28 h-28 rounded-2xl object-cover border border-gray-200 bg-white">
                    @else
                        <div class="w-28 h-28 rounded-2xl flex items-center justify-center text-white text-3xl font-bold"
                             style="background:#6f4cb2;">
                            {{ strtoupper(mb_substr($employer->company_name ?: auth()->user()->name, 0, 1)) }}
                        </div>
                    @endif
                </div>

                <div class="mt-5 space-y-2">
                    {{-- Single-action upload --}}
                    <form x-data="{ loading: false }"
                          method="POST"
                          action="{{ route('employer.company.logo.upload') }}"
                          enctype="multipart/form-data">
                        @csrf
                        <label class="block cursor-pointer" :class="loading ? 'opacity-50 pointer-events-none' : ''">
                            <input type="file"
                                   name="logo"
                                   accept=".jpg,.jpeg,.png,.webp"
                                   class="sr-only"
                                   @change="loading = true; $el.closest('form').submit()">
                            <div class="flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 hover:border-[#6f4cb2]/40 hover:bg-violet-50/30 transition-all p-3 text-sm text-gray-500">
                                <x-heroicon-o-arrow-up-tray class="w-4 h-4 shrink-0" />
                                <span x-show="!loading">{{ $employer->logo_path ? 'Replace logo' : 'Upload logo' }}</span>
                                <span x-show="loading" x-cloak>Uploading…</span>
                            </div>
                        </label>
                        @error('logo')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </form>

                    @if($employer->logo_path)
                        <form method="POST"
                              action="{{ route('employer.company.logo.remove') }}"
                              onsubmit="return confirm('Remove your company logo?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="w-full text-xs font-medium text-red-500 hover:text-red-700 hover:underline text-center py-1">
                                Remove logo
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Right: Company details --}}
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
                        <textarea id="company_description" name="company_description"
                                  class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm"
                                  rows="4">{{ old('company_description', $employer->company_description) }}</textarea>
                        <x-input-error :messages="$errors->get('company_description')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                    </div>

                    <div class="border-t border-gray-100 pt-6">
                        <h4 class="text-sm font-semibold uppercase tracking-[0.14em] text-gray-400 mb-4">Contact Details</h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-input-label for="contact_person" value="Contact Person" />
                                <x-text-input id="contact_person" name="contact_person" type="text" class="mt-1 block w-full"
                                    :value="old('contact_person', $employer->contact_person)" />
                                <x-input-error :messages="$errors->get('contact_person')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="contact_email" value="Contact Email" />
                                <x-text-input id="contact_email" name="contact_email" type="email" class="mt-1 block w-full"
                                    :value="old('contact_email', $employer->contact_email)" />
                                <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <x-input-label value="Company / Switchboard Number" />
                        <div class="mt-1 flex gap-2">
                            <x-text-input id="phone_company" name="phone_company" type="tel"
                                class="flex-1 min-w-0"
                                :value="old('phone_company', $employer->phone_company)"
                                placeholder="+1 (868) 000-0000" />
                            <x-text-input id="phone_ext" name="phone_ext" type="text"
                                class="w-24"
                                :value="old('phone_ext', $employer->phone_ext)"
                                placeholder="Ext." />
                        </div>
                        <x-input-error :messages="$errors->get('phone_company')" class="mt-2" />
                        <x-input-error :messages="$errors->get('phone_ext')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="phone_direct" value="Direct Number" />
                        <x-text-input id="phone_direct" name="phone_direct" type="tel" class="mt-1 block w-full"
                            :value="old('phone_direct', $employer->phone_direct)"
                            placeholder="+1 (868) 000-0000" />
                        <x-input-error :messages="$errors->get('phone_direct')" class="mt-2" />
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
