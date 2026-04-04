<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kairox Exchange</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f4f4f6] text-gray-900">
    <div class="relative isolate min-h-screen overflow-hidden">
        <img
            src="{{ asset('images/home_meeting.jpg') }}"
            alt="Kairox Exchange background"
            class="absolute inset-0 h-full w-full object-cover brightness-75"
        >

        <div class="absolute inset-0 bg-black/60"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-black/20 via-[#2d2147]/15 to-[#50b7a4]/8"></div>

        <header class="relative z-10">
            <div class="mx-auto max-w-7xl px-6 py-6 lg:px-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center justify-center sm:justify-start">
                        <img
                            src="{{ asset('images/kairox-logo.png') }}"
                            alt="Kairox Exchange"
                            class="h-12 w-auto sm:h-16"
                        >
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:flex sm:items-center sm:gap-3">
                        @auth
                            @if(auth()->user()->hasRole('admin'))
                                <a href="{{ route('admin.dashboard') }}" class="ll-btn ll-btn-slate w-full sm:w-auto">
                                    Dashboard
                                </a>
                            @elseif(auth()->user()->hasRole('employer'))
                                <a href="{{ route('employer.dashboard') }}" class="ll-btn ll-btn-slate w-full sm:w-auto">
                                    Dashboard
                                </a>
                            @else
                                <a href="{{ route('jobseeker.dashboard') }}" class="ll-btn ll-btn-slate w-full sm:w-auto">
                                    Dashboard
                                </a>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="ll-btn ll-btn-outline w-full sm:w-auto">
                                Sign In
                            </a>

                            <a href="{{ route('register') }}" class="ll-btn ll-btn-accent w-full sm:w-auto">
                                Register
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </header>

        <main class="relative z-10">
            <main class="relative z-10">
                <div class="mx-auto grid min-h-[calc(100vh-92px)] max-w-7xl items-center gap-10 px-6 pb-10 pt-6 sm:gap-12 sm:pb-12 sm:pt-8 lg:grid-cols-2 lg:px-8">
                    <div class="text-white">
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-medium uppercase tracking-[0.22em] text-white/80 backdrop-blur-sm">
                            <span class="inline-block h-2 w-2 rounded-full bg-[#50b7a4]"></span>
                            Work • Study • Travel
                        </div>

                        <h1 class="mt-4 max-w-2xl text-4xl font-bold leading-tight sm:text-5xl lg:text-6xl">
                            Work and travel opportunities for ambitious applicants
                        </h1>

                        <p class="mt-5 max-w-xl text-base leading-7 text-white/85 sm:text-lg">
                            Kairox Exchange connects job seekers and employers through a streamlined platform built for access, opportunity, and action.
                        </p>

                        <div class="relative mt-6">
                            <div class="absolute -inset-2 rounded-[2rem] bg-gradient-to-r from-[#50b7a4]/20 via-[#6f4cb2]/18 to-[#6d8290]/16 blur-2xl"></div>

                            <div class="relative flex flex-wrap gap-3">
                                <a href="{{ route('apply') }}" class="ll-btn ll-btn-accent">
                                    Apply Now
                                </a>

                                <a href="{{ route('pricing') }}" class="ll-btn ll-btn-primary">
                                    View Pricing
                                </a>

                                <a href="{{ route('register') }}" class="ll-btn ll-btn-slate">
                                    Create Account
                                </a>
                            </div>
                        </div>

                        <div class="mt-10 grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
                                <p class="text-3xl font-bold text-white">Job Seekers</p>
                                <p class="mt-2 text-sm text-white/75">
                                    Complete profiles, browse roles, and apply faster.
                                </p>
                            </div>

                            <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
                                <p class="text-3xl font-bold text-white">Employers</p>
                                <p class="mt-2 text-sm text-white/75">
                                    Create listings, manage applicants, and grow trust.
                                </p>
                            </div>

                            <!-- <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
                                <p class="text-3xl font-bold text-white">Admins</p>
                                <p class="mt-2 text-sm text-white/75">
                                    Moderate jobs, payments, and platform access cleanly.
                                </p>
                            </div> -->
                        </div>
                    </div>

                    <div class="lg:justify-self-end">
                        <div class="rounded-[2rem] border border-white/20 bg-white/10 p-4 shadow-2xl shadow-black/20 backdrop-blur-md sm:p-6">
                            <div class="rounded-[1.75rem] border border-white/40 bg-white/95 p-6 text-gray-900 shadow-xl sm:p-8">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-medium uppercase tracking-[0.2em] text-gray-500">
                                            Platform Overview
                                        </p>
                                        <h2 class="mt-2 text-2xl font-semibold text-gray-900">
                                            A smarter path to global opportunity
                                        </h2>
                                    </div>

                                    <div class="hidden sm:flex h-14 w-14 items-center justify-center rounded-3xl bg-[#efe8fb] text-[#6f4cb2]">
                                        <x-heroicon-o-globe-alt class="h-7 w-7" />
                                    </div>
                                </div>

                                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                    <div class="rounded-3xl p-5 shadow-sm" style="background:#efe8fb;">
                                        <p class="text-sm font-medium text-violet-700">For Job Seekers</p>
                                        <p class="mt-3 text-sm leading-6 text-gray-700">
                                            Browse approved opportunities, complete your profile, and submit applications quickly.
                                        </p>
                                    </div>

                                    <div class="rounded-3xl p-5 shadow-sm" style="background:#e7f7f3;">
                                        <p class="text-sm font-medium text-teal-700">For Employers</p>
                                        <p class="mt-3 text-sm leading-6 text-gray-700">
                                            Create listings, manage applicants, and present your company with confidence.
                                        </p>
                                    </div>

                                    <div class="rounded-3xl p-5 shadow-sm sm:col-span-2" style="background:#edf2f6;">
                                        <p class="text-sm font-medium" style="color:#6d8290;">Managed Workflow</p>
                                        <p class="mt-3 text-sm leading-6 text-gray-700">
                                            Admin moderation, payments, entitlements, and role-based dashboards keep the platform organized and scalable.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-6 flex flex-wrap gap-3">
                                    <a href="{{ route('login') }}" class="ll-btn ll-btn-outline">
                                        Sign In
                                    </a>

                                    <a href="{{ route('register') }}" class="ll-btn ll-btn-accent">
                                        Get Started
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
    </div>
</body>
</html>