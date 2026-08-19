<x-layouts.portal
    title="MFA Recovery Codes"
    heading="Store your recovery codes"
    subheading="Each code is single-use. This is the only display after generation."
    portalRole="admin"
>
    <div class="max-w-2xl rounded-3xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
        <p class="text-sm text-amber-900">Store these codes in an approved password manager. They will not be emailed or written to the audit log.</p>

        <ul class="mt-5 grid gap-2 rounded-2xl bg-white p-5 font-mono text-sm text-gray-900 sm:grid-cols-2">
            @foreach($recoveryCodes as $recoveryCode)
                <li>{{ $recoveryCode }}</li>
            @endforeach
        </ul>

        <a href="{{ route('admin.dashboard') }}" class="mt-6 inline-flex rounded-xl bg-[#6f4cb2] px-4 py-2 text-sm font-semibold text-white">Continue to admin</a>
    </div>
</x-layouts.portal>
