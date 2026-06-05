@if($payments->isEmpty())
    <div class="mt-6 rounded-2xl bg-gray-50 border border-gray-100 p-6 text-center">
        <p class="text-gray-500">No payments found.</p>
    </div>
@else
    <div class="mt-6 space-y-3">
        @foreach($payments as $payment)
            <x-likeslocale.operation-row>
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-gray-900">
                                        {{ $payment->user?->name ?? 'Unknown User' }}
                                    </p>

                                    <x-likeslocale.status-pill tone="brand">
                                        {{ $payment->plan?->name ?? \App\Models\Entitlement::typeLabelFor($payment->entitlement_type) }}
                                    </x-likeslocale.status-pill>

                                    <x-likeslocale.status-pill :tone="\App\Models\Payment::toneFor($payment->status)">
                                        {{ \App\Models\Payment::labelFor($payment->status) }}
                                    </x-likeslocale.status-pill>

                                    @if($payment->entitlement_type === \App\Models\Entitlement::TYPE_JOB_SEEKER_ACCESS)
                                        @if($payment->status === \App\Models\Payment::STATUS_PAID && $payment->entitlement_activated_at === null)
                                            <x-likeslocale.status-pill tone="warning">Access Not Activated</x-likeslocale.status-pill>
                                        @elseif($payment->entitlement_activated_at !== null)
                                            <x-likeslocale.status-pill tone="success">Access Active</x-likeslocale.status-pill>
                                        @endif
                                    @endif
                                </div>

                                <div class="border-t border-gray-100 mt-3 pt-2.5 text-sm">
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-gray-600">
                                        <span class="font-semibold text-gray-900">
                                            <x-heroicon-o-banknotes class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}
                                        </span>
                                        <span class="text-gray-500"><x-heroicon-o-envelope class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $payment->user?->email }}</span>
                                        <span><x-heroicon-o-calendar-days class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $payment->paid_at?->format('M d, Y') ?? '—' }}</span>
                                        @if($payment->gateway)
                                            <span><x-heroicon-o-credit-card class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ ucfirst($payment->gateway) }}</span>
                                        @endif
                                        @if($payment->plan)
                                            <span class="text-gray-500"><x-heroicon-o-rectangle-stack class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $payment->plan->name }}</span>
                                        @endif
                                        @if($payment->order_id)
                                            <span class="text-gray-400"><x-heroicon-o-hashtag class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" /><span class="font-medium text-gray-500">Order:</span> {{ $payment->order_id }}</span>
                                        @endif
                                        @if($payment->external_ref)
                                            <span class="text-gray-400"><x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" /><span class="font-medium text-gray-500">Ref:</span> {{ $payment->external_ref }}</span>
                                        @endif

                                        @if(data_get($payment->raw_payload, 'notes'))
                                            <div><span class="font-medium text-gray-700">Notes:</span> {{ data_get($payment->raw_payload, 'notes') }}</div>
                                        @endif

                                        @if(data_get($payment->raw_payload, 'callback.message'))
                                            <div><span class="font-medium text-gray-700">Gateway Message:</span> {{ data_get($payment->raw_payload, 'callback.message') }}</div>
                                        @endif

                                        @if(data_get($payment->raw_payload, 'callback_review_reason'))
                                            <div class="text-amber-700">
                                                <span class="font-medium">Review Reason:</span>
                                                {{ data_get($payment->raw_payload, 'callback_review_reason') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-2">
                                @if($payment->status === \App\Models\Payment::STATUS_REVIEW_REQUIRED)
                                    <form method="POST" action="{{ route('admin.payments.confirm', $payment) }}"
                                        onsubmit="return confirm('Confirm this payment and activate access for this user?');">
                                        @csrf
                                        <x-likeslocale.button type="submit" variant="success">
                                            Confirm Payment
                                        </x-likeslocale.button>
                                    </form>
                                @endif

                                @if($payment->entitlement_type === \App\Models\Entitlement::TYPE_JOB_SEEKER_ACCESS
                                    && $payment->status === \App\Models\Payment::STATUS_PAID
                                    && $payment->entitlement_activated_at === null)
                                    <form method="POST" action="{{ route('admin.payments.activate', $payment) }}"
                                        onsubmit="return confirm('Activate access for {{ addslashes($payment->user?->name ?? 'this user') }}?');">
                                        @csrf
                                        <x-likeslocale.button type="submit" variant="accent">
                                            Activate Access
                                        </x-likeslocale.button>
                                    </form>
                                @endif

                                @if($payment->gateway === 'manual')
                                    <form method="POST" action="{{ route('admin.payments.destroy', $payment) }}"
                                        onsubmit="return confirm('Remove this manual payment record? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')

                                        <x-likeslocale.button type="submit" variant="secondary">
                                            Remove
                                        </x-likeslocale.button>
                                    </form>
                                @endif
                            </div>
                    </div>
                </x-likeslocale.operation-row>
            @endforeach
    </div>

    <div class="mt-6">
        {{ $payments->links() }}
    </div>
@endif