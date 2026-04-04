@if($payments->isEmpty())
    <div class="mt-6 rounded-2xl bg-gray-50 border border-gray-100 p-6 text-center">
        <p class="text-gray-500">No payments found.</p>
    </div>
@else
    <div class="mt-6 rounded-3xl border border-gray-300 p-4 md:p-6" style="background:#e7e7ea;">
        <div class="space-y-4">
            @foreach($payments as $payment)
                <div class="rounded-2xl border border-gray-300 px-4 py-4 md:px-5 md:py-4 shadow-sm" style="background:#efeff2;">
                    <div class="rounded-2xl border border-transparent px-4 py-4 md:px-5 md:py-4 transition-all duration-200 ease-out hover:bg-[#f6f6f9] hover:border-gray-300 hover:shadow-md">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                            <div class="space-y-2">
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
                                </div>

                                <p class="text-sm text-gray-500">
                                    {{ $payment->user?->email }}
                                </p>

                                <div class="text-sm text-gray-600">
                                    <span class="font-medium text-gray-900">Amount:</span>
                                    {{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}
                                    <span class="mx-2 text-gray-300">|</span>
                                    <span class="font-medium text-gray-900">Paid At:</span>
                                    {{ $payment->paid_at?->format('M d, Y') ?? '—' }}
                                </div>

                                <div class="text-sm text-gray-500 space-y-1">
                                    @if($payment->plan)
                                        <div>
                                            <span class="font-medium text-gray-700">Plan:</span>
                                            {{ $payment->plan->name }}
                                            @if($payment->plan->slug)
                                                <span class="text-gray-400">({{ $payment->plan->slug }})</span>
                                            @endif
                                        </div>
                                    @endif

                                    @if($payment->gateway)
                                        <div><span class="font-medium text-gray-700">Gateway:</span> {{ $payment->gateway }}</div>
                                    @endif

                                    @if($payment->order_id)
                                        <div><span class="font-medium text-gray-700">Order ID:</span> {{ $payment->order_id }}</div>
                                    @endif

                                    @if($payment->external_ref)
                                        <div><span class="font-medium text-gray-700">External Ref:</span> {{ $payment->external_ref }}</div>
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

                            <div class="flex flex-col sm:flex-row gap-2">
                                @if($payment->status === \App\Models\Payment::STATUS_REVIEW_REQUIRED)
                                    <form method="POST" action="{{ route('admin.payments.confirm', $payment) }}"
                                        onsubmit="return confirm('Confirm this payment and activate access for this user?');">
                                        @csrf
                                        <x-likeslocale.button type="submit" variant="accent">
                                            Confirm Payment
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
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $payments->links() }}
        </div>
    </div>
@endif