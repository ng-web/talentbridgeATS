<x-layouts.portal
    title="Administrator Security"
    heading="Administrator Security"
    subheading="Multi-factor authentication is required for administrator access."
    portalRole="admin"
>
    <div class="max-w-3xl space-y-6">
        @if(session('error'))
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-red-900">{{ session('error') }}</div>
        @endif

        <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
            @if($user->hasEnabledTwoFactorAuthentication())
                <h2 class="text-xl font-semibold text-gray-900">MFA is active</h2>
                <p class="mt-2 text-sm text-gray-600">Your administrator account requires an authenticator or unused recovery code at sign-in.</p>

                <form method="POST" action="{{ route('admin.security.mfa.recovery-codes') }}" class="mt-5">
                    @csrf
                    <x-primary-button>Generate new recovery codes</x-primary-button>
                </form>
            @elseif($user->two_factor_secret)
                <h2 class="text-xl font-semibold text-gray-900">Confirm enrollment</h2>
                <p class="mt-2 text-sm text-gray-600">Scan this code with a TOTP-compatible authenticator, then enter the generated code. MFA is not active until confirmation succeeds.</p>

                <div class="mt-5 inline-block rounded-2xl border border-gray-200 bg-white p-4">{!! $user->twoFactorQrCodeSvg() !!}</div>

                <form method="POST" action="{{ route('admin.security.mfa.confirm') }}" class="mt-5 max-w-sm space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="code" value="Authenticator code" />
                        <x-text-input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->getBag('confirmTwoFactorAuthentication')->get('code')" class="mt-2" />
                    </div>
                    <x-primary-button>Confirm MFA</x-primary-button>
                </form>
            @else
                <h2 class="text-xl font-semibold text-gray-900">Enroll an authenticator</h2>
                <p class="mt-2 text-sm text-gray-600">A recent password confirmation is required. The application will generate the secret and recovery mechanism through Laravel Fortify.</p>

                <form method="POST" action="{{ route('admin.security.mfa.start') }}" class="mt-5">
                    @csrf
                    <x-primary-button>Start secure enrollment</x-primary-button>
                </form>
            @endif
        </section>
    </div>
</x-layouts.portal>
