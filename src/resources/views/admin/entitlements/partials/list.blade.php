@if($entitlements->isEmpty())
    <div class="mt-6 rounded-2xl bg-gray-50 border border-gray-100 p-6 text-center">
        <p class="text-gray-500">No entitlements found.</p>
    </div>
@else
    <div class="mt-6 rounded-3xl border border-gray-300 p-4 md:p-6" style="background:#e7e7ea;">
        <div class="space-y-4">
            @foreach($entitlements as $entitlement)
                <div class="rounded-2xl border border-gray-300 px-4 py-4 md:px-5 md:py-4 shadow-sm" style="background:#efeff2;">
                    <div class="rounded-2xl border border-transparent px-4 py-4 md:px-5 md:py-4 transition-all duration-200 ease-out hover:bg-[#f6f6f9] hover:border-gray-300 hover:shadow-md">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-gray-900">
                                        {{ $entitlement->user?->name ?? 'Unknown User' }}
                                    </p>

                                    <x-likeslocale.status-pill tone="brand">
                                        {{ \App\Models\Entitlement::typeLabelFor($entitlement->type) }}
                                    </x-likeslocale.status-pill>

                                    <x-likeslocale.status-pill :tone="\App\Models\Entitlement::toneFor($entitlement->status)">
                                        {{ \App\Models\Entitlement::labelFor($entitlement->status) }}
                                    </x-likeslocale.status-pill>
                                </div>

                                <p class="text-sm text-gray-500">
                                    {{ $entitlement->user?->email }}
                                </p>

                                <div class="text-sm text-gray-600">
                                    <span class="font-medium text-gray-900">Starts:</span>
                                    {{ $entitlement->starts_at?->format('M d, Y') ?? '—' }}
                                    <span class="mx-2 text-gray-300">|</span>
                                    <span class="font-medium text-gray-900">Expires:</span>
                                    {{ $entitlement->expires_at?->format('M d, Y') ?? 'No expiry' }}
                                </div>

                                @if($entitlement->source || $entitlement->notes)
                                    <div class="text-sm text-gray-500">
                                        @if($entitlement->source)
                                            <span><span class="font-medium text-gray-700">Source:</span> {{ $entitlement->source }}</span>
                                        @endif
                                        @if($entitlement->notes)
                                            <div class="mt-1"><span class="font-medium text-gray-700">Notes:</span> {{ $entitlement->notes }}</div>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('admin.entitlements.destroy', $entitlement) }}"
                                onsubmit="return confirm('Revoke this entitlement for {{ addslashes($entitlement->user?->name ?? 'this user') }}? This will remove platform access.');">
                                @csrf
                                @method('DELETE')

                                <x-likeslocale.button type="submit" variant="secondary">
                                    Revoke
                                </x-likeslocale.button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $entitlements->links() }}
        </div>
    </div>
@endif