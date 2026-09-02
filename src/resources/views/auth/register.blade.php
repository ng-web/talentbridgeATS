<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-3xl font-bold text-gray-900">Create Your Account</h1>
        <p class="mt-2 text-sm text-gray-500">
            Join Kairox Exchange and access work and travel opportunities.
        </p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="name" :value="__('Full Name')" />
            <x-text-input id="name" class="mt-1 block w-full rounded-2xl" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        @if(config('privacy.registration.evidence_enabled'))
            <div class="space-y-3 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <h2 class="text-sm font-semibold text-gray-900">Privacy and terms</h2>

                @foreach(\App\Models\PolicyDocument::TYPES as $policyType)
                    @php($policy = $policyDocuments->get($policyType))
                    @if($policy)
                        <input type="hidden" name="policy_documents[{{ $policyType }}]" value="{{ $policy->id }}">
                        <label class="flex items-start gap-3 text-sm text-gray-700">
                            <input type="checkbox" name="policy_acknowledgements[{{ $policyType }}]" value="1" required class="mt-1 rounded border-gray-300">
                            <span>
                                @if($policyType === \App\Models\PolicyDocument::TYPE_PRIVACY_NOTICE)
                                    I acknowledge that I was shown the
                                @else
                                    I accept the
                                @endif
                                <a href="{{ $policy->content_reference }}" target="_blank" rel="noopener noreferrer" class="font-medium text-[#6f4cb2] underline">
                                    {{ $policy->title }} (version {{ $policy->version }})
                                </a>.
                            </span>
                        </label>
                    @elseif(config('privacy.registration.'.($policyType === \App\Models\PolicyDocument::TYPE_PRIVACY_NOTICE ? 'require_privacy_notice' : 'require_terms')))
                        <p class="text-sm text-red-700">Registration policy evidence is temporarily unavailable.</p>
                    @endif
                @endforeach

                <x-input-error :messages="$errors->get('policy_documents')" />
                <x-input-error :messages="$errors->get('policy_documents.privacy_notice')" />
                <x-input-error :messages="$errors->get('policy_documents.terms_of_service')" />
            </div>
        @endif

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1 block w-full rounded-2xl" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <input type="hidden" name="role" value="job_seeker">

        <div>
            <x-input-label for="program" :value="__('Current Program')" />
            <select id="program" name="program" required
                    class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                <option value="">Select a Program</option>
                @foreach($programs as $program)
                    <option value="{{ $program->slug }}"
                        @selected(old('program', $selectedProgram?->slug) === $program->slug)>
                        {{ $program->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('program')" class="mt-2" />
            <p class="mt-1 text-xs text-gray-400">Choose the Kairox Program you are currently pursuing.</p>
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1 block w-full rounded-2xl"
                          type="password"
                          name="password"
                          required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="mt-1 block w-full rounded-2xl"
                          type="password"
                          name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="space-y-3 pt-2">
            <x-likeslocale.button type="submit" variant="accent" class="w-full">
                Create Account
            </x-likeslocale.button>

            <a href="{{ route('login') }}" class="ll-btn ll-btn-outline w-full">
                Already Registered?
            </a>
        </div>
    </form>
</x-guest-layout>
