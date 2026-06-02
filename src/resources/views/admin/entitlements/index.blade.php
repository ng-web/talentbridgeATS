<x-layouts.portal :title="'Entitlements'" heading="Entitlements" subheading="Grant, update, and revoke platform access." portalRole="admin">
    <div class="space-y-6"
         x-data='{ "showForm": @json($errors->any() || $prefill !== null) }'>

        {{-- Grant / Edit Access toggle --}}
        <div class="rounded-3xl bg-white shadow border border-gray-100 overflow-hidden">
            <button type="button"
                    @click="showForm = !showForm"
                    class="w-full flex items-center justify-between p-6 md:p-8 text-left hover:bg-gray-50 transition-colors">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">Grant / Edit Access</h3>
                    <p class="mt-1 text-sm text-gray-500">Assign or update an entitlement for a seeker or employer.</p>
                </div>
                <x-heroicon-o-chevron-down class="w-5 h-5 text-gray-400 transition-transform duration-200 shrink-0 ml-4"
                    x-bind:class="showForm ? 'rotate-180' : ''" />
            </button>

            <div x-show="showForm" class="border-t border-gray-100 p-6 md:p-8">
                <form method="POST" action="{{ route('admin.entitlements.store') }}"
                      x-data='{
                          selectedUserId: @json(old("user_id", $prefill?->user_id ?? "")),
                          selectedType: @json(old("type", $prefill?->type ?? \App\Models\Entitlement::TYPE_JOB_SEEKER_ACCESS)),
                          durationType: @json(old("duration_type", "12m")),
                          expiresAt: @json(old("expires_at", now()->addYear()->format("Y-m-d"))),
                          startsAt: @json(old("starts_at", now()->format("Y-m-d"))),
                          usersMeta: @json(collect($users)->keyBy("id")),
                          detectedRole: "",
                          recommendedType: "",

                          init() {
                              this.syncUser();
                              this.applyDuration(this.durationType);
                          },

                          syncUser() {
                              const u = this.usersMeta[this.selectedUserId];
                              if (u) {
                                  this.detectedRole = u.role_label ?? "";
                                  if (u.access_type) {
                                      this.selectedType = u.access_type;
                                      this.recommendedType = u.access_type_label ?? "";
                                  } else {
                                      this.recommendedType = "No recommended type";
                                  }
                              } else {
                                  this.detectedRole = "";
                                  this.recommendedType = "";
                              }
                          },

                          applyDuration(type) {
                              this.durationType = type;
                              const base = new Date(this.startsAt || new Date());
                              const d = new Date(base);
                              if (type === "1m")  { d.setMonth(d.getMonth() + 1); this.expiresAt = d.toISOString().split("T")[0]; }
                              else if (type === "3m")  { d.setMonth(d.getMonth() + 3); this.expiresAt = d.toISOString().split("T")[0]; }
                              else if (type === "6m")  { d.setMonth(d.getMonth() + 6); this.expiresAt = d.toISOString().split("T")[0]; }
                              else if (type === "12m") { d.setMonth(d.getMonth() + 12); this.expiresAt = d.toISOString().split("T")[0]; }
                              else if (type === "none") { this.expiresAt = ""; }
                          }
                      }'
                      class="space-y-6">
                    @csrf

                    @if(session('success'))
                        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- User --}}
                        <div x-init="
                            $nextTick(() => {
                                const sel = $refs.userSelect;
                                if (!sel || sel.dataset.tomInit) return;
                                sel.dataset.tomInit = '1';
                                new TomSelect(sel, {
                                    create: false,
                                    allowEmptyOption: true,
                                    maxOptions: 500,
                                    placeholder: 'Search by name or email',
                                    onChange: v => { selectedUserId = v; syncUser(); }
                                });
                            })
                        ">
                            <label class="block text-sm font-medium text-gray-700 mb-1">User <span class="text-red-500">*</span></label>
                            <select name="user_id" x-model="selectedUserId" x-ref="userSelect"
                                    class="block w-full rounded-2xl border-gray-300 shadow-sm">
                                <option value="">Select user</option>
                                @foreach($users as $user)
                                    <option value="{{ $user['id'] }}"
                                        @selected(old('user_id', $prefill?->user_id) == $user['id'])>
                                        {{ $user['name'] }} ({{ $user['email'] }})
                                    </option>
                                @endforeach
                            </select>
                            @error('user_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror

                            <template x-if="selectedUserId">
                                <div class="mt-2 rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800">
                                    <span class="font-semibold">Role:</span> <span x-text="detectedRole"></span>
                                    <span class="mx-2 text-blue-300">|</span>
                                    <span class="font-semibold">Recommended:</span> <span x-text="recommendedType"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Type --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Entitlement Type <span class="text-red-500">*</span></label>
                            <select name="type" x-model="selectedType"
                                    class="block w-full rounded-2xl border-gray-300 shadow-sm">
                                @foreach(\App\Models\Entitlement::TYPES as $t)
                                    <option value="{{ $t }}">{{ \App\Models\Entitlement::typeLabelFor($t) }}</option>
                                @endforeach
                            </select>
                            @error('type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        {{-- Status --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="block w-full rounded-2xl border-gray-300 shadow-sm">
                                @foreach(\App\Models\Entitlement::STATUSES as $s)
                                    <option value="{{ $s }}"
                                        @selected(old('status', $prefill?->status ?? \App\Models\Entitlement::STATUS_ACTIVE) === $s)>
                                        {{ \App\Models\Entitlement::labelFor($s) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        {{-- Starts At --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Starts At</label>
                            <input type="date" name="starts_at" x-model="startsAt"
                                   @change="applyDuration(durationType)"
                                   class="block w-full rounded-2xl border-gray-300 shadow-sm">
                            @error('starts_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    {{-- Duration presets --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Access Duration</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach(['1m' => '1 Month', '3m' => '3 Months', '6m' => '6 Months', '12m' => '12 Months', 'none' => 'No Expiry', 'custom' => 'Custom Date'] as $val => $label)
                                <button type="button"
                                        @click="applyDuration('{{ $val }}')"
                                        x-bind:class="durationType === '{{ $val }}'
                                            ? 'bg-[#6f4cb2] text-white border-[#6f4cb2]'
                                            : 'bg-white text-gray-700 border-gray-300 hover:border-[#6f4cb2] hover:text-[#6f4cb2]'"
                                        class="px-4 py-1.5 rounded-xl border text-sm font-medium transition-colors">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>

                        <div x-show="durationType === 'custom'" class="mt-3">
                            <input type="date" x-model="expiresAt"
                                   class="block w-full sm:w-56 rounded-2xl border-gray-300 shadow-sm">
                        </div>

                        <input type="hidden" name="expires_at" x-bind:value="expiresAt">
                        <input type="hidden" name="duration_type" x-bind:value="durationType">

                        <p class="mt-2 text-xs text-gray-400"
                           x-show="expiresAt"
                           x-text="'Expires: ' + (expiresAt ? new Date(expiresAt).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : '')">
                        </p>
                        <p class="mt-2 text-xs text-gray-400" x-show="!expiresAt">No expiry — access will not automatically lapse.</p>

                        @error('expires_at')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2"
                                  placeholder="Optional internal note e.g. 'Pilot access', 'Renewed after expiry'"
                                  class="block w-full rounded-2xl border-gray-300 shadow-sm">{{ old('notes', $prefill?->notes ?? '') }}</textarea>
                        @error('notes')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex gap-3">
                        <x-likeslocale.button type="submit" variant="accent">Save Entitlement</x-likeslocale.button>
                        <x-likeslocale.button type="button" variant="secondary" @click="showForm = false">Cancel</x-likeslocale.button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Entitlements list --}}
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-4">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">Current Entitlements</h3>
                    <p class="mt-1 text-sm text-gray-500">Manage active and inactive access records.</p>
                </div>
                <x-likeslocale.button :href="route('admin.employers.create')" variant="accent">
                    Add Employer / Sponsor
                </x-likeslocale.button>
            </div>

            <form id="entitlement-filters-form" method="GET" action="{{ route('admin.entitlements.index') }}"
                  class="flex flex-col sm:flex-row sm:items-center gap-3">
                @if($filters['expiring'] ?? false)
                    <input type="hidden" name="expiring" value="1">
                @endif

                <input id="q" name="q" type="text" value="{{ $filters['q'] ?? '' }}"
                    placeholder="Search by user name or email"
                    class="flex-1 min-w-0 rounded-2xl border-gray-300 shadow-sm">

                <select id="filter_type" name="type" class="w-full sm:w-44 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All types</option>
                    @foreach(\App\Models\Entitlement::TYPES as $t)
                        <option value="{{ $t }}" @selected(($filters['type'] ?? '') === $t)>
                            {{ \App\Models\Entitlement::typeLabelFor($t) }}
                        </option>
                    @endforeach
                </select>

                <select id="filter_status" name="status" class="w-full sm:w-40 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All statuses</option>
                    @foreach(\App\Models\Entitlement::STATUSES as $s)
                        <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>
                            {{ \App\Models\Entitlement::labelFor($s) }}
                        </option>
                    @endforeach
                </select>

                <a href="{{ route('admin.entitlements.index') }}" class="shrink-0">
                    <x-likeslocale.button type="button" variant="secondary">Reset</x-likeslocale.button>
                </a>
            </form>

            <div id="entitlements-list-region" class="mt-4">
                @include('admin.entitlements.partials.list', ['entitlements' => $entitlements, 'filters' => $filters])
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (() => {
            const form      = document.getElementById('entitlement-filters-form');
            const region    = document.getElementById('entitlements-list-region');
            const qInput    = document.getElementById('q');
            const typeSelect   = document.getElementById('filter_type');
            const statusSelect = document.getElementById('filter_status');

            if (!form || !region) return;

            let timer = null;

            const fetchList = async (url = null) => {
                const params = new URLSearchParams(new FormData(form));
                const target = url ?? `${form.action}?${params.toString()}`;
                region.style.opacity = '0.6';
                try {
                    const res = await fetch(target, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
                    });
                    if (!res.ok) return;
                    region.innerHTML = await res.text();
                    window.history.replaceState({}, '', target);
                    bindPagination();
                } catch(e) {
                    console.error(e);
                } finally {
                    region.style.opacity = '1';
                }
            };

            const bindPagination = () => {
                region.querySelectorAll('.pagination a, nav[role="navigation"] a').forEach(link => {
                    link.addEventListener('click', e => { e.preventDefault(); fetchList(link.href); });
                });
            };

            form.addEventListener('submit', e => { e.preventDefault(); fetchList(); });
            typeSelect?.addEventListener('change', () => fetchList());
            statusSelect?.addEventListener('change', () => fetchList());
            qInput?.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(fetchList, 300); });

            bindPagination();
        })();
    </script>
    @endpush
</x-layouts.portal>
