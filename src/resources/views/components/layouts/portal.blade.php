<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'TalentBridge Portal') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
</head>
<body class="bg-gray-100 text-gray-900 antialiased">
    <div x-data="{ mobileMenuOpen: false }" class="min-h-screen">
        <div
            x-show="mobileMenuOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 bg-black/40 md:hidden"
            @click="mobileMenuOpen = false"
        ></div>

        <!-- Desktop Sidebar -->
        <aside class="hidden md:flex fixed inset-y-0 left-0 z-30 w-72 flex-col text-white shadow-2xl" style="background:#6f4cb2;">
            <div class="flex h-full min-h-0 flex-col">
                <div class="px-8 py-8 border-b border-white/15 shrink-0">
                    <div class="text-2xl font-extrabold tracking-wide">KAIROX</div>
                    <div class="text-sm font-medium opacity-90">EXCHANGE</div>
                </div>

                <nav class="flex-1 min-h-0 overflow-y-auto px-5 py-6 space-y-2">
                    @if(($portalRole ?? null) === 'jobseeker')
                        <div class="px-4 pt-2 pb-1 text-[11px] uppercase tracking-[0.18em] text-white/55">
                            Job Seeker
                        </div>

                        <a href="{{ route('jobseeker.dashboard') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('jobseeker.dashboard') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10 hover:translate-x-1' }}">
                            <x-heroicon-o-home class="w-5 h-5" />
                            <span>Dashboard</span>
                        </a>

                        <a href="{{ route('jobseeker.profile.edit') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('jobseeker.profile.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10 hover:translate-x-1' }}">
                            <x-heroicon-o-user class="w-5 h-5" />
                            <span>Profile</span>
                        </a>

                        <a href="{{ route('jobseeker.jobs.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('jobseeker.jobs.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10 hover:translate-x-1' }}">
                            <x-heroicon-o-briefcase class="w-5 h-5" />
                            <span>Opportunities</span>
                        </a>

                        <a href="{{ route('jobseeker.applications.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('jobseeker.applications.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10 hover:translate-x-1' }}">
                            <x-heroicon-o-document-text class="w-5 h-5" />
                            <span>Applications</span>
                        </a>
                    @elseif(($portalRole ?? null) === 'employer')
                        <div class="px-4 pt-2 pb-1 text-[11px] uppercase tracking-[0.18em] text-white/55">
                            Employer
                        </div>

                        <a href="{{ route('employer.dashboard') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('employer.dashboard') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10 hover:translate-x-1' }}">
                            <x-heroicon-o-home class="w-5 h-5" />
                            <span>Dashboard</span>
                        </a>

                        <a href="{{ route('employer.company.edit') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('employer.company.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10 hover:translate-x-1' }}">
                            <x-heroicon-o-building-office class="w-5 h-5" />
                            <span>Company Profile</span>
                        </a>

                        <a href="{{ route('employer.jobs.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('employer.jobs.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10 hover:translate-x-1' }}">
                            <x-heroicon-o-briefcase class="w-5 h-5" />
                            <span>Jobs</span>
                        </a>

                        <a href="{{ route('employer.applicants.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('employer.applicants.*') || request()->routeIs('employer.applications.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10 hover:translate-x-1' }}">
                            <x-heroicon-o-users class="w-5 h-5" />
                            <span>Applicants</span>
                        </a>
                    @elseif(($portalRole ?? null) === 'admin')
                        <div class="px-4 pt-2 pb-1 text-[11px] uppercase tracking-[0.18em] text-white/55">
                            Administration
                        </div>

                        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('admin.dashboard') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10 hover:translate-x-1' }}">
                            <x-heroicon-o-chart-bar class="w-5 h-5" />
                            <span>Dashboard</span>
                        </a>

                        <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 {{ request()->routeIs('admin.users.*') ? 'bg-white/20' : 'hover:bg-white/10' }}">
                            <x-heroicon-o-users class="w-5 h-5" />
                            <span>Users</span>
                        </a>

                        <a href="{{ route('admin.jobs.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('admin.jobs.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10 hover:translate-x-1' }}">
                            <x-heroicon-o-cog-6-tooth class="w-5 h-5" />
                            <span>Manage Jobs</span>
                        </a>

                        <a href="{{ route('admin.entitlements.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('admin.entitlements.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10 hover:translate-x-1' }}">
                            <x-heroicon-o-key class="w-5 h-5" />
                            <span>Entitlements</span>
                        </a>

                        <a href="{{ route('admin.payments.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('admin.payments.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10 hover:translate-x-1' }}">
                            <x-heroicon-o-credit-card class="w-5 h-5" />
                            <span>Payments</span>
                        </a>
                    @endif
                </nav>

                <div class="shrink-0 border-t border-white/15 px-4 py-5">
                    @if(auth()->check())
                        <div class="mb-4 rounded-2xl border border-white/10 bg-white/10 px-4 py-3 shadow-sm">
                            <div class="text-sm font-semibold text-white leading-tight">
                                {{ auth()->user()->name }}
                            </div>
                            <div class="mt-1 text-xs text-white/75 break-all">
                                {{ auth()->user()->email }}
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-left font-medium hover:bg-white/20 transition-all duration-200">
                            Log Out
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Mobile Sidebar -->
        <aside
            x-show="mobileMenuOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="fixed inset-y-0 left-0 z-50 w-72 text-white flex flex-col md:hidden shadow-2xl"
            style="background:#6f4cb2;"
        >
            <div class="px-6 py-6 border-b border-white/15 flex items-center justify-between shrink-0">
                <div>
                    <div class="text-2xl font-extrabold tracking-wide">KAIROX</div>
                    <div class="text-sm font-medium opacity-90">EXCHANGE</div>
                </div>

                <button @click="mobileMenuOpen = false" class="rounded-lg p-2 hover:bg-white/10">
                    <x-heroicon-o-x-mark class="w-6 h-6" />
                </button>
            </div>

            <nav class="flex-1 min-h-0 overflow-y-auto px-5 py-6 space-y-2">
                @if(($portalRole ?? null) === 'jobseeker')
                    <div class="px-4 pt-2 pb-1 text-[11px] uppercase tracking-[0.18em] text-white/55">
                        Job Seeker
                    </div>

                    <a href="{{ route('jobseeker.dashboard') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('jobseeker.dashboard') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10' }}">
                        <x-heroicon-o-home class="w-5 h-5" />
                        <span>Dashboard</span>
                    </a>

                    <a href="{{ route('jobseeker.profile.edit') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('jobseeker.profile.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10' }}">
                        <x-heroicon-o-user class="w-5 h-5" />
                        <span>Profile</span>
                    </a>

                    <a href="{{ route('jobseeker.jobs.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('jobseeker.jobs.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10' }}">
                        <x-heroicon-o-briefcase class="w-5 h-5" />
                        <span>Opportunities</span>
                    </a>

                    <a href="{{ route('jobseeker.applications.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('jobseeker.applications.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10' }}">
                        <x-heroicon-o-document-text class="w-5 h-5" />
                        <span>Applications</span>
                    </a>
                @elseif(($portalRole ?? null) === 'employer')
                    <div class="px-4 pt-2 pb-1 text-[11px] uppercase tracking-[0.18em] text-white/55">
                        Employer
                    </div>

                    <a href="{{ route('employer.dashboard') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('employer.dashboard') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10' }}">
                        <x-heroicon-o-home class="w-5 h-5" />
                        <span>Dashboard</span>
                    </a>

                    <a href="{{ route('employer.company.edit') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('employer.company.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10' }}">
                        <x-heroicon-o-building-office class="w-5 h-5" />
                        <span>Company Profile</span>
                    </a>

                    <a href="{{ route('employer.jobs.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('employer.jobs.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10' }}">
                        <x-heroicon-o-briefcase class="w-5 h-5" />
                        <span>Jobs</span>
                    </a>

                    <a href="{{ route('employer.applicants.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('employer.applicants.*') || request()->routeIs('employer.applications.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10' }}">
                        <x-heroicon-o-users class="w-5 h-5" />
                        <span>Applicants</span>
                    </a>
                @elseif(($portalRole ?? null) === 'admin')
                    <div class="px-4 pt-2 pb-1 text-[11px] uppercase tracking-[0.18em] text-white/55">
                        Administration
                    </div>

                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('admin.dashboard') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10' }}">
                        <x-heroicon-o-chart-bar class="w-5 h-5" />
                        <span>Dashboard</span>
                    </a>

                    <a href="{{ route('admin.jobs.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('admin.jobs.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10' }}">
                        <x-heroicon-o-cog-6-tooth class="w-5 h-5" />
                        <span>Manage Jobs</span>
                    </a>

                    <a href="{{ route('admin.entitlements.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('admin.entitlements.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10' }}">
                        <x-heroicon-o-key class="w-5 h-5" />
                        <span>Entitlements</span>
                    </a>

                    <a href="{{ route('admin.payments.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 font-medium transition-all duration-200 {{ request()->routeIs('admin.payments.*') ? 'bg-white/20 border border-white/15 shadow-sm' : 'hover:bg-white/10' }}">
                        <x-heroicon-o-credit-card class="w-5 h-5" />
                        <span>Payments</span>
                    </a>
                @endif
            </nav>

            <div class="shrink-0 border-t border-white/15 px-5 py-5">
                @if(auth()->check())
                    <div class="mb-4 rounded-2xl border border-white/10 bg-white/10 px-4 py-3 shadow-sm">
                        <div class="text-sm font-semibold text-white leading-tight">
                            {{ auth()->user()->name }}
                        </div>
                        <div class="mt-1 text-xs text-white/75 break-all">
                            {{ auth()->user()->email }}
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full rounded-2xl border border-white/10 bg-white/10 px-4 py-3 text-left font-medium hover:bg-white/20 transition-all duration-200">
                        Log Out
                    </button>
                </form>
            </div>
        </aside>

        <div class="md:ml-72 min-h-screen">
            <header class="bg-white border-b border-gray-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 sm:py-6 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <button @click="mobileMenuOpen = true" class="md:hidden inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white p-2 text-gray-700">
                            <x-heroicon-o-bars-3 class="w-6 h-6" />
                        </button>

                        <div>
                            <h1 class="text-2xl sm:text-3xl font-semibold">{{ $heading ?? 'Portal' }}</h1>
                            @if(!empty($subheading ?? null))
                                <p class="mt-1 text-sm sm:text-base text-gray-500">{{ $subheading }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <!-- 🔔 Notifications -->
                        <x-notification-bell />
                    </div>
                </div>
            </header>

            <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-6">
                @if (session('success') || session('status') || session('error'))
                    <div
                        x-data="{ open: true }"
                        x-init="setTimeout(() => open = false, 3500)"
                        x-show="open"
                        x-transition:enter="transform ease-out duration-300 transition"
                        x-transition:enter-start="translate-y-2 opacity-0"
                        x-transition:enter-end="translate-y-0 opacity-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed top-6 right-6 z-50 w-full max-w-sm rounded-2xl border px-4 py-3 text-sm font-medium shadow-xl"
                        :class="{
                            'border-green-200 bg-green-50 text-green-800': {{ session('error') ? 'false' : 'true' }},
                            'border-red-200 bg-red-50 text-red-700': {{ session('error') ? 'true' : 'false' }}
                        }"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="pr-2">
                                {{ session('success') ?? session('status') ?? session('error') }}
                            </div>

                            <button type="button" @click="open = false" class="shrink-0 text-current opacity-70 hover:opacity-100">
                                <x-heroicon-o-x-mark class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
    @stack('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
</body>
</html>