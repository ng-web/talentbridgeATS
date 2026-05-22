<x-layouts.portal :title="'Payments'" heading="Payments" subheading="Record and review manual platform payments." portalRole="admin">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-1">
            <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                <h3 class="text-xl font-semibold text-gray-900">Record Payment</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Record a manual paid transaction. The selected plan controls the amount and access that will be activated.
                </p>

                <form method="POST" action="{{ route('admin.payments.store') }}" class="mt-6 space-y-5">
                    @csrf

                    <div
                        x-data='{
                            selectedUserId: @json(old("user_id", "")),
                            initTomSelect() {
                                const select = this.$refs.paymentUserSelect;

                                if (!select || select.dataset.tomInitialized === "true") {
                                    return;
                                }

                                const component = this;

                                new TomSelect(select, {
                                    create: false,
                                    allowEmptyOption: true,
                                    maxOptions: 500,
                                    placeholder: "Select user",
                                    onChange(value) {
                                        component.selectedUserId = value;
                                    }
                                });

                                select.dataset.tomInitialized = "true";
                            }
                        }'
                        x-init="initTomSelect()"
                    >
                        <label for="payment_user_id" class="block text-sm font-medium text-gray-700">User</label>

                        <select
                            id="payment_user_id"
                            name="user_id"
                            x-model="selectedUserId"
                            x-ref="paymentUserSelect"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm"
                        >
                            <option value="">Select user</option>
                            @foreach($users as $user)
                                <option value="{{ $user['id'] }}" @selected(old('user_id') == $user['id'])>
                                    {{ $user['name'] }} ({{ $user['email'] }})
                                </option>
                            @endforeach
                        </select>

                        @error('user_id')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <p class="mt-2 text-xs text-gray-500">
                            Search by user name or email.
                        </p>
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const select = document.getElementById('payment_user_id');

                                if (!select || select.dataset.tomInitialized === 'true') return;

                                new TomSelect(select, {
                                    create: false,
                                    allowEmptyOption: true,
                                    maxOptions: 500,
                                    placeholder: 'Select user'
                                });

                                select.dataset.tomInitialized = 'true';
                            });
                        </script>
                    </div>

                    <div>
                        <label for="plan_id" class="block text-sm font-medium text-gray-700">Plan</label>
                        <select id="plan_id" name="plan_id" class="portal-form-select mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                            <option value="">Select plan</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" @selected(old('plan_id') == $plan->id)>
                                    {{ $plan->name }} — {{ $plan->currency }} {{ number_format((float) $plan->amount, 2) }}
                                </option>
                            @endforeach
                        </select>

                        @error('plan_id')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <p class="mt-2 text-sm text-gray-500">
                            The selected plan determines entitlement type, amount, currency, and access duration.
                        </p>
                    </div>

                    <div>
                        <label for="paid_at" class="block text-sm font-medium text-gray-700">Paid At</label>
                        <input id="paid_at" name="paid_at" type="date"
                               value="{{ old('paid_at', now()->format('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">

                        @error('paid_at')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        <p class="mt-2 text-xs text-gray-500">
                            Defaults to today for manual payments.
                        </p>
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                        <textarea id="notes" name="notes" rows="3"
                                  class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">{{ old('notes') }}</textarea>

                        @error('notes')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <x-likeslocale.button type="submit" variant="accent">
                            Save Payment
                        </x-likeslocale.button>
                    </div>
                </form>
            </div>
        </div>

        <div class="xl:col-span-2">
            <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                <h3 class="text-xl font-semibold text-gray-900">Payment History</h3>
                <p class="mt-1 text-sm text-gray-500">Manual and future gateway payments will appear here.</p>

                @if(session('success'))
                    <div class="mt-4 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                <form id="payment-filters-form" method="GET" action="{{ route('admin.payments.index') }}" class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3">
                    <input
                        id="q"
                        name="q"
                        type="text"
                        value="{{ $filters['q'] ?? '' }}"
                        placeholder="Search by order ID, name, or email"
                        class="flex-1 min-w-0 w-full sm:w-auto rounded-2xl border-gray-300 shadow-sm"
                    >

                    <select id="status_filter" name="status" class="w-full sm:w-40 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                        <option value="">All statuses</option>
                        @foreach(\App\Models\Payment::STATUSES as $paymentStatus)
                            <option value="{{ $paymentStatus }}" @selected(($filters['status'] ?? '') === $paymentStatus)>
                                {{ \App\Models\Payment::labelFor($paymentStatus) }}
                            </option>
                        @endforeach
                    </select>

                    <select id="gateway_filter" name="gateway" class="w-full sm:w-40 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                        <option value="">All gateways</option>
                        <option value="manual" @selected(($filters['gateway'] ?? '') === 'manual')>Manual</option>
                        <option value="wipay" @selected(($filters['gateway'] ?? '') === 'wipay')>WiPay</option>
                        <option value="stripe" @selected(($filters['gateway'] ?? '') === 'stripe')>Stripe</option>
                        <option value="paypal" @selected(($filters['gateway'] ?? '') === 'paypal')>PayPal</option>
                    </select>

                    <a href="{{ route('admin.payments.index') }}" class="shrink-0">
                        <x-likeslocale.button type="button" variant="secondary">
                            Reset
                        </x-likeslocale.button>
                    </a>
                </form>

                <div id="payments-list-region">
                    @include('admin.payments.partials.list', ['payments' => $payments])
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (() => {
            const form = document.getElementById('payment-filters-form');
            const listRegion = document.getElementById('payments-list-region');
            const qInput = document.getElementById('q');
            const statusSelect = document.getElementById('status_filter');
            const gatewaySelect = document.getElementById('gateway_filter');

            if (!form || !listRegion || !qInput || !statusSelect || !gatewaySelect) {
                return;
            }

            let debounceTimer = null;

            const buildUrl = (url = null) => {
                if (url) return url;

                const formData = new FormData(form);
                const params = new URLSearchParams();

                for (const [key, value] of formData.entries()) {
                    if (String(value).trim() !== '') {
                        params.set(key, value);
                    }
                }

                const action = form.getAttribute('action') || window.location.pathname;
                const query = params.toString();

                return query ? `${action}?${query}` : action;
            };

            const fetchList = async (url = null) => {
                const targetUrl = buildUrl(url);

                listRegion.style.opacity = '0.6';

                try {
                    const response = await fetch(targetUrl, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                        },
                    });

                    if (!response.ok) {
                        throw new Error(`Request failed with status ${response.status}`);
                    }

                    const html = await response.text();
                    listRegion.innerHTML = html;
                    window.history.replaceState({}, '', targetUrl);
                    bindPagination();
                } catch (error) {
                    console.error('Payment filtering failed:', error);
                } finally {
                    listRegion.style.opacity = '1';
                }
            };

            const debouncedFetch = () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => fetchList(), 300);
            };

            const bindPagination = () => {
                listRegion.querySelectorAll('.pagination a, nav[role="navigation"] a').forEach((link) => {
                    link.addEventListener('click', (event) => {
                        event.preventDefault();
                        fetchList(link.href);
                    });
                });
            };

            qInput.addEventListener('input', debouncedFetch);
            statusSelect.addEventListener('change', () => fetchList());
            gatewaySelect.addEventListener('change', () => fetchList());

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                fetchList();
            });

            bindPagination();
        })();
    </script>
    @endpush
</x-layouts.portal>