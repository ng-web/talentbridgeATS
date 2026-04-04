<x-layouts.portal :title="'Entitlements'" heading="Entitlements" subheading="Grant, update, and revoke platform access." portalRole="admin">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-1">
            <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                <h3 class="text-xl font-semibold text-gray-900">Grant Access</h3>
                <p class="mt-1 text-sm text-gray-500">Assign access entitlements to seekers or employers.</p>

                <form method="POST" action="{{ route('admin.entitlements.store') }}" class="mt-6 space-y-5">
                    @csrf

                    <div
                        x-data='{
                            selectedUserId: @json(old("user_id", "")),
                            selectedEntitlementType: @json(old("type", \App\Models\Entitlement::TYPE_JOB_SEEKER_ACCESS)),
                            detectedRoleLabel: "",
                            recommendedAccessLabel: "",
                            usersMeta: @json(collect($users)->keyBy("id")),

                            syncFromUserId() {
                                const matched = this.usersMeta[this.selectedUserId];

                                if (matched) {
                                    this.detectedRoleLabel = matched.role_label ?? "Unknown";

                                    if (matched.access_type) {
                                        this.selectedEntitlementType = matched.access_type;
                                        this.recommendedAccessLabel = matched.access_type_label ?? "";
                                    } else {
                                        this.recommendedAccessLabel = "No recommended access type";
                                    }
                                } else {
                                    this.detectedRoleLabel = "";
                                    this.recommendedAccessLabel = "";
                                }
                            }
                        }'
                        x-init="syncFromUserId()"
                    >
                        <label for="user_id" class="block text-sm font-medium text-gray-700">User</label>

                        <select
                            id="user_id"
                            name="user_id"
                            x-model="selectedUserId"
                            x-ref="entitlementUserSelect"
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

                        <template x-if="selectedUserId">
                            <div class="mt-3 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                                <div>
                                    <span class="font-semibold">Detected role:</span>
                                    <span x-text="detectedRoleLabel || 'Unknown'"></span>
                                </div>
                                <div class="mt-1">
                                    <span class="font-semibold">Recommended access:</span>
                                    <span x-text="recommendedAccessLabel || 'No recommended access type'"></span>
                                </div>
                            </div>
                        </template>

                        <div class="mt-5">
                            <label for="type" class="block text-sm font-medium text-gray-700">Entitlement Type</label>
                            <select id="type" name="type" x-model="selectedEntitlementType" class="portal-form-select mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                                @foreach(\App\Models\Entitlement::TYPES as $type)
                                    <option value="{{ $type }}">
                                        {{ \App\Models\Entitlement::typeLabelFor($type) }}
                                    </option>
                                @endforeach
                            </select>

                            @error('type')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror

                            <p class="mt-2 text-xs text-gray-500">
                                The entitlement type auto-selects based on the chosen user role.
                            </p>
                        </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const select = document.getElementById('user_id');

                                if (!select || select.dataset.tomInitialized === 'true') return;

                                const component = Alpine.$data(select.closest('[x-data]'));

                                new TomSelect(select, {
                                    create: false,
                                    allowEmptyOption: true,
                                    maxOptions: 500,
                                    placeholder: 'Select user',
                                    onChange(value) {
                                        component.selectedUserId = value;
                                        component.syncFromUserId();
                                    }
                                });

                                select.dataset.tomInitialized = 'true';
                            });
                        </script>
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="status" name="status" class="portal-form-select mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                            @foreach(\App\Models\Entitlement::STATUSES as $status)
                                <option value="{{ $status }}" @selected(old('status', \App\Models\Entitlement::STATUS_ACTIVE) === $status)>
                                    {{ \App\Models\Entitlement::labelFor($status) }}
                                </option>
                            @endforeach
                        </select>

                        @error('status')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="starts_at" class="block text-sm font-medium text-gray-700">Starts At</label>
                        <input id="starts_at" name="starts_at" type="date"
                               value="{{ old('starts_at', now()->format('Y-m-d')) }}"
                               class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">

                        @error('starts_at')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="expires_at" class="block text-sm font-medium text-gray-700">Expires At</label>
                        <input id="expires_at" name="expires_at" type="date"
                               value="{{ old('expires_at') }}"
                               class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">

                        @error('expires_at')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
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
                            Save Entitlement
                        </x-likeslocale.button>
                    </div>
                </form>
            </div>
        </div>

        <div class="xl:col-span-2">
            <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">Current Entitlements</h3>
                    <p class="mt-1 text-sm text-gray-500">Manage active and inactive access records.</p>
                </div>

                <form id="entitlement-filters-form" method="GET" action="{{ route('admin.entitlements.index') }}" class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label for="q" class="block text-sm font-medium text-gray-700">Search users</label>
                        <input
                            id="q"
                            name="q"
                            type="text"
                            value="{{ $filters['q'] ?? '' }}"
                            placeholder="Search by user name or email"
                            class="mt-1 block w-full rounded-2xl border-gray-300 shadow-sm"
                        >
                        <p class="mt-2 text-xs text-gray-500">Filters update automatically as you type.</p>
                    </div>

                    <div>
                        <label for="filter_type" class="block text-sm font-medium text-gray-700">Type</label>
                        <select id="filter_type" name="type" class="portal-form-select mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                            <option value="">All types</option>
                            @foreach(\App\Models\Entitlement::TYPES as $entitlementType)
                                <option value="{{ $entitlementType }}" @selected(($filters['type'] ?? '') === $entitlementType)>
                                    {{ \App\Models\Entitlement::typeLabelFor($entitlementType) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="filter_status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="filter_status" name="status" class="portal-form-select mt-1 block w-full rounded-2xl border-gray-300 shadow-sm">
                            <option value="">All statuses</option>
                            @foreach(\App\Models\Entitlement::STATUSES as $entitlementStatus)
                                <option value="{{ $entitlementStatus }}" @selected(($filters['status'] ?? '') === $entitlementStatus)>
                                    {{ \App\Models\Entitlement::labelFor($entitlementStatus) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-4 flex justify-end">
                        <a href="{{ route('admin.entitlements.index') }}">
                            <x-likeslocale.button type="button" variant="secondary">
                                Reset
                            </x-likeslocale.button>
                        </a>
                    </div>
                </form>

                <div id="entitlements-list-region">
                    @include('admin.entitlements.partials.list', ['entitlements' => $entitlements])
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (() => {
            const form = document.getElementById('entitlement-filters-form');
            const listRegion = document.getElementById('entitlements-list-region');
            const qInput = document.getElementById('q');
            const typeSelect = document.getElementById('filter_type');
            const statusSelect = document.getElementById('filter_status');

            if (!form || !listRegion || !qInput || !typeSelect || !statusSelect) {
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
                    console.error('Entitlement filtering failed:', error);
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
            typeSelect.addEventListener('change', () => fetchList());
            statusSelect.addEventListener('change', () => fetchList());

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                fetchList();
            });

            bindPagination();
        })();
    </script>
    @endpush
</x-layouts.portal>