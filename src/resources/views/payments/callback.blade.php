<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Status</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f4f4f6] text-gray-900">
    <main class="min-h-screen flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-xl rounded-3xl bg-white border border-gray-200 shadow-xl p-8">
            <h1 class="text-3xl font-semibold">Payment Update</h1>

            <p class="mt-3 text-gray-600">
                Order ID: <span class="font-medium text-gray-900">{{ $payment->order_id }}</span>
            </p>

            <div class="mt-6 flex flex-wrap gap-2">
                <x-likeslocale.status-pill :tone="\App\Models\Payment::toneFor($payment->status)">
                    {{ \App\Models\Payment::labelFor($payment->status) }}
                </x-likeslocale.status-pill>

                <x-likeslocale.status-pill :tone="$verified ? 'success' : 'warning'">
                    {{ $verified ? 'Verified' : 'Awaiting Verification' }}
                </x-likeslocale.status-pill>
            </div>

            @if($message)
                <p class="mt-5 text-sm text-gray-600">{{ $message }}</p>
            @endif

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('pricing') }}" class="ll-btn ll-btn-primary">Back to Pricing</a>
                <a href="{{ url('/') }}" class="ll-btn ll-btn-outline">Return Home</a>
            </div>
        </div>
    </main>
</body>
</html>