<x-layouts.portal :title="'Payments'" heading="Payments" subheading="Record and review manual platform payments." portalRole="admin">
    <div class="space-y-6" x-data='{ "showForm": @json($errors->any()) }'>

        {{-- Record Payment toggle --}}
        <div class="rounded-3xl bg-white shadow border border-gray-100 overflow-hidden">
            <button type="button"
                    @click="showForm = !showForm"
                    class="w-full flex items-center justify-between p-6 md:p-8 text-left hover:bg-gray-50 transition-colors">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">Record Manual Payment</h3>
                    <p class="mt-1 text-sm text-gray-500">Record a cash, bank transfer, or cheque payment and activate the entitlement.</p>
                </div>
                <span class="shrink-0 ml-4 inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 shadow-sm">
                    <span x-text="showForm ? 'Collapse' : 'Expand'">Expand</span>
                    <x-heroicon-o-chevron-down class="w-4 h-4 transition-transform duration-200"
                        x-bind:class="showForm ? 'rotate-180' : ''" />
                </span>
            </button>

            <div x-show="showForm" class="border-t border-gray-100">
                <div class="p-6 md:p-8 pt-6">
                <form method="POST" action="{{ route('admin.payments.store') }}" class="space-y-5">
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
                        <x-likeslocale.button type="submit" variant="accent">Save Payment</x-likeslocale.button>
                    </div>
                </form>
                </div>
            </div>
        </div>

        {{-- Payment history --}}
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                <h3 class="text-xl font-semibold text-gray-900">Payment History</h3>
                <p class="mt-1 text-sm text-gray-500">Manual and future gateway payments will appear here.</p>

                @if(session('success'))
                    <div class="mt-4 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                <form id="payment-filters-form" method="GET" action="{{ route('admin.payments.index') }}" class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3">
                    @if($filters['unactivated'] ?? false)
                        <input type="hidden" name="unactivated" value="1">
                    @endif
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

                    @if($activeGateways->count() > 1)
                        <select id="gateway_filter" name="gateway" class="w-full sm:w-40 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                            <option value="">All gateways</option>
                            @foreach($activeGateways as $gw)
                                <option value="{{ $gw }}" @selected(($filters['gateway'] ?? '') === $gw)>
                                    {{ ucfirst($gw) }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" id="gateway_filter" name="gateway" value="">
                    @endif

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