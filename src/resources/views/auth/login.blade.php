<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-3xl font-bold text-gray-900">Welcome Back</h1>
        <p class="mt-2 text-sm text-gray-500">
            Sign in to continue your Kairox Exchange journey.
        </p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1 block w-full rounded-2xl" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" />

                @if (Route::has('password.request'))
                    <a class="text-sm text-[#6f4cb2] hover:text-[#8161bf]" href="{{ route('password.request') }}">
                        {{ __('Forgot password?') }}
                    </a>
                @endif
            </div>

            <x-text-input id="password" class="mt-1 block w-full rounded-2xl"
                          type="password"
                          name="password"
                          required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center gap-3">
            <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-[#6f4cb2] shadow-sm focus:ring-[#6f4cb2]" name="remember">
            <label for="remember_me" class="text-sm text-gray-600">{{ __('Remember me') }}</label>
        </div>

        <div class="space-y-3 pt-2">
            <x-likeslocale.button type="submit" variant="accent" class="w-full">
                Sign In
            </x-likeslocale.button>

            <a href="{{ route('register') }}" class="ll-btn ll-btn-outline w-full">
                Create Account
            </a>
        </div>
    </form>
</x-guest-layout>