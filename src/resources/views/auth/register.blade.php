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

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1 block w-full rounded-2xl" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="role" :value="__('Account Type')" />
            <select id="role" name="role" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm focus:border-[#6f4cb2] focus:ring-[#6f4cb2]" required>
                <option value="">Select account type</option>
                <option value="job_seeker" @selected(old('role') === 'job_seeker')>Job Seeker</option>
                <option value="employer" @selected(old('role') === 'employer')>Employer</option>
            </select>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
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