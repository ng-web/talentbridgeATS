@if($entitlements->isEmpty())
    <div class="mt-6 rounded-2xl bg-gray-50 border border-gray-100 p-6 text-center">
        <p class="text-gray-500">No entitlements found.</p>
    </div>
@else
    <div class="mt-6 space-y-3">
        @foreach($entitlements as $entitlement)
            <x-likeslocale.operation-row>
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-gray-900">
                                        {{ $entitlement->user?->name ?? 'Unknown User' }}
                                    </p>

                                    <x-likeslocale.status-pill tone="brand">
                                        {{ \App\Models\Entitlement::typeLabelFor($entitlement->type) }}
                                    </x-likeslocale.status-pill>

                                    @php
                                        $isStaleExpired = $entitlement->status === \App\Models\Entitlement::STATUS_ACTIVE
                                            && $entitlement->expires_at
                                            && $entitlement->expires_at->isPast();
                                    @endphp

                                    <x-likeslocale.status-pill :tone="$isStaleExpired ? 'danger' : \App\Models\Entitlement::toneFor($entitlement->status)">
                                        {{ $isStaleExpired ? 'Expired (stale)' : \App\Models\Entitlement::labelFor($entitlement->status) }}
                                    </x-likeslocale.status-pill>
                                </div>

                                <div class="border-t border-gray-100 mt-3 pt-2.5 space-y-1.5 text-sm">
                                    <p class="text-gray-500">{{ $entitlement->user?->email }}</p>

                                    <div class="text-gray-600">
                                        <span class="font-medium text-gray-900">Starts:</span>
                                        {{ $entitlement->starts_at?->format('M d, Y') ?? '—' }}
                                        <span class="mx-2 text-gray-300">|</span>
                                        <span class="font-medium text-gray-900">Expires:</span>
                                        <span class="{{ $isStaleExpired ? 'text-red-600 font-medium' : '' }}">
                                            {{ $entitlement->expires_at?->format('M d, Y') ?? 'No expiry' }}
                                            @if($isStaleExpired) (expired)@endif
                                        </span>
                                    </div>

                                    @if($entitlement->source || $entitlement->notes)
                                        <div class="text-gray-500">
                                            @if($entitlement->source)
                                                <span><span class="font-medium text-gray-700">Source:</span> {{ $entitlement->source }}</span>
                                            @endif
                                            @if($entitlement->notes)
                                                <div><span class="font-medium text-gray-700">Notes:</span> {{ $entitlement->notes }}</div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 xl:items-end">
                                @if($isStaleExpired)
                                    <form method="POST" action="{{ route('admin.entitlements.store') }}">
                                        @csrf
                                        <input type="hidden" name="user_id"    value="{{ $entitlement->user_id }}">
                                        <input type="hidden" name="type"       value="{{ $entitlement->type }}">
                                        <input type="hidden" name="status"     value="{{ \App\Models\Entitlement::STATUS_ACTIVE }}">
                                        <input type="hidden" name="starts_at"  value="{{ now()->toDateString() }}">
                                        <input type="hidden" name="expires_at" value="{{ now()->addYear()->toDateString() }}">
                                        <input type="hidden" name="notes"      value="{{ $entitlement->notes }}">
                                        <x-likeslocale.button type="submit" variant="success">
                                            Renew 12 months
                                        </x-likeslocale.button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('admin.entitlements.destroy', $entitlement) }}"
                                    onsubmit="return confirm('Revoke this entitlement for {{ addslashes($entitlement->user?->name ?? 'this user') }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-likeslocale.button type="submit" variant="warning">
                                        Revoke
                                    </x-likeslocale.button>
                                </form>
                            </div>
                    </div>
                </x-likeslocale.operation-row>
            @endforeach
    </div>

    <div class="mt-6">
        {{ $entitlements->links() }}
    </div>
@endif