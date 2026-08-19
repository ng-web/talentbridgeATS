<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Set up your account</h1>
        <p class="mt-2 text-sm text-gray-600">Choose a password. This secure setup link is single-use and expires automatically.</p>
    </div>

    <form method="POST" action="{{ route('account-setup.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email }}">

        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" name="password" type="password" autocomplete="new-password" class="mt-1 block w-full" required />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="mt-1 block w-full" required />
        </div>

        <x-primary-button>Establish password</x-primary-button>
    </form>

    <script>
        window.history.replaceState(null, document.title, @js(route('account-setup.store')));
    </script>
</x-guest-layout>
