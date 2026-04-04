<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100">
    <div class="min-h-screen lg:grid lg:grid-cols-2">
        <div class="relative hidden lg:block">
            <img
                src="{{ asset('images/auth-bg.jpg') }}"
                alt="Travel background"
                class="absolute inset-0 h-full w-full object-cover brightness-75"
            >

            <div class="absolute inset-0 bg-black/70"></div>
            <div class="absolute inset-0 bg-gradient-to-br from-black/20 via-[#2d2147]/15 to-[#50b7a4]/8"></div>

            <div class="relative z-10 flex h-full flex-col justify-center px-14 xl:px-20 text-white">
                <img
                    src="{{ asset('images/kairox-logo.png') }}"
                    alt="Kairox Exchange"
                    class="w-[320px] max-w-full drop-shadow-xl"
                >

                <div class="mt-10 max-w-xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-medium uppercase tracking-[0.22em] text-white/80 backdrop-blur-sm">
                        <span class="inline-block h-2 w-2 rounded-full bg-[#50b7a4]"></span>
                        Global Opportunity Platform
                    </div>

                    <h1 class="mt-6 text-5xl font-bold leading-tight tracking-[-0.02em] text-white drop-shadow-lg">
                        Embark on a journey of work and travel.
                    </h1>

                    <p class="mt-5 text-lg leading-8 text-white/88 drop-shadow-md">
                        Connecting talent with global work, study, and travel opportunities through a modern digital platform.
                    </p>

                    <div class="mt-10 grid max-w-lg grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
                            <p class="text-xl font-semibold text-white">Seekers</p>
                            <p class="mt-2 text-sm text-white/75">
                                Browse opportunities and apply with confidence.
                            </p>
                        </div>

                        <div class="rounded-3xl border border-white/10 bg-white/10 p-5 backdrop-blur-sm">
                            <p class="text-xl font-semibold text-white">Employers</p>
                            <p class="mt-2 text-sm text-white/75">
                                Create listings and manage applicants efficiently.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex min-h-screen items-center justify-center bg-[#f4f4f6] px-6 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center lg:hidden">
                    <img
                        src="{{ asset('images/kairox-logo.png') }}"
                        alt="Kairox Exchange"
                        class="mx-auto w-56 max-w-full"
                    >

                    <p class="mt-4 text-sm text-gray-500">
                        Embark on a journey of work and travel.
                    </p>
                </div>

                <div class="rounded-3xl border border-gray-200 bg-white/95 p-7 shadow-xl shadow-black/5 backdrop-blur sm:p-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</body>
</html>