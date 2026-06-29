<x-layouts.portal :title="'Users'" heading="Users" subheading="Manage accounts, access, and payment visibility from one place." portalRole="admin">
    <div class="space-y-6">
        <div class="rounded-3xl bg-white p-6 md:p-8 shadow border border-gray-100">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900">Search & Filter Users</h3>
                    <p class="mt-1 text-sm text-gray-500">Find employers, job seekers, and admins by identity, company, access, or password status.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-likeslocale.button :href="route('admin.users.deleted')" variant="secondary">
                        Recycle Bin
                    </x-likeslocale.button>

                    <x-likeslocale.button :href="route('admin.employers.create')" variant="accent">
                        Add Employer / Sponsor
                    </x-likeslocale.button>
                </div>
            </div>

            <form id="user-filter-form" method="GET" action="{{ route('admin.users.index') }}"
                  class="mt-4 flex flex-col sm:flex-row sm:flex-wrap xl:flex-nowrap items-center gap-3">

                <input id="user-q" name="q" type="text" value="{{ $filters['q'] ?? '' }}"
                    placeholder="Search by name, email, or company"
                    class="flex-1 min-w-0 w-full sm:w-auto rounded-2xl border-gray-300 shadow-sm">

                <select id="user-role" name="role" class="w-full sm:w-36 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All roles</option>
                    <option value="admin"      @selected(($filters['role'] ?? '') === 'admin')>Admin</option>
                    <option value="employer"   @selected(($filters['role'] ?? '') === 'employer')>Employer</option>
                    <option value="job_seeker" @selected(($filters['role'] ?? '') === 'job_seeker')>Job Seeker</option>
                </select>

                <select id="user-access" name="access" class="w-full sm:w-40 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All access</option>
                    <option value="active"   @selected(($filters['access'] ?? '') === 'active')>Has Active Access</option>
                    <option value="inactive" @selected(($filters['access'] ?? '') === 'inactive')>No Active Access</option>
                </select>

                <select id="user-pw" name="password_change" class="w-full sm:w-48 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All users</option>
                    <option value="yes" @selected(($filters['password_change'] ?? '') === 'yes')>Must Change Password</option>
                    <option value="no"  @selected(($filters['password_change'] ?? '') === 'no')>Password Changed</option>
                </select>

                <div class="flex gap-2 shrink-0 w-full sm:w-auto">
                    <x-likeslocale.button type="submit" variant="accent">Apply</x-likeslocale.button>
                    <a href="{{ route('admin.users.index') }}" id="user-filter-reset">
                        <x-likeslocale.button type="button" variant="secondary">Reset</x-likeslocale.button>
                    </a>
                </div>
            </form>
        </div>

        <div id="user-list-region">
            @include('admin.users.partials.list', ['users' => $users, 'filters' => $filters])
        </div>
    </div>

    @push('scripts')
    <script>
        (() => {
            const form      = document.getElementById('user-filter-form');
            const region    = document.getElementById('user-list-region');
            const qInput    = document.getElementById('user-q');
            const resetLink = document.getElementById('user-filter-reset');

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
                } catch (e) {
                    console.error('User filter failed', e);
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
            form.querySelectorAll('select').forEach(el => el.addEventListener('change', () => fetchList()));
            qInput?.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(fetchList, 300); });
            resetLink?.addEventListener('click', e => { e.preventDefault(); form.reset(); fetchList(); });

            bindPagination();
        })();
    </script>
    @endpush
</x-layouts.portal>
