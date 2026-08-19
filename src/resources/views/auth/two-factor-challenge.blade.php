<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Administrator verification</h1>
        <p class="mt-2 text-sm text-gray-600">Enter the current code from your authenticator app, or use one recovery code.</p>
    </div>

    <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="code" value="Authenticator code" />
            <x-text-input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="recovery_code" value="Recovery code" />
            <x-text-input id="recovery_code" name="recovery_code" type="text" autocomplete="one-time-code" class="mt-1 block w-full" />
            <x-input-error :messages="$errors->get('recovery_code')" class="mt-2" />
        </div>

        <x-primary-button>Verify and sign in</x-primary-button>
    </form>
</x-guest-layout>
