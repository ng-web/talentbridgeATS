<x-guest-layout>
    <div class="max-w-md mx-auto mt-10 rounded-3xl bg-white p-8 shadow border border-gray-100">
        <h1 class="text-2xl font-semibold text-gray-900">Change Your Password</h1>
        <p class="mt-2 text-sm text-gray-500">
            For security, you need to change your temporary password before continuing.
        </p>

        <form method="POST" action="{{ route('forced-password.update') }}" class="mt-6 space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="block text-sm font-medium text-gray-700">Temporary Password</label>
                <input id="current_password" name="current_password" type="password" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                @error('current_password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                <input id="password" name="password" type="password" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                @error('password')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
            </div>

            <div>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl px-5 py-3 text-sm font-medium text-white transition hover:brightness-110" style="background:#6f4cb2;">
                    Update Password
                </button>
            </div>
        </form>
    </div>
</x-guest-layout>