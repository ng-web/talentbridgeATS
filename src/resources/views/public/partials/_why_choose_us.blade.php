<section class="mt-10 rounded-3xl p-8 shadow border" style="background:#efe8fb; border-color:#d8caee;">
    <h2 class="text-2xl font-semibold text-gray-900">Why Choose Us?</h2>

    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        @foreach([
            ['title' => 'Age-Specific Programs', 'body' => 'Opportunities tailored to your stage in life'],
            ['title' => 'Full Support', 'body' => 'From application to arrival and beyond'],
            ['title' => 'Trusted Partners', 'body' => 'Only reputable employers and host organizations'],
            ['title' => 'Affordable Options', 'body' => 'Competitive fees and flexible payment plans'],
        ] as $reason)
            <div class="rounded-2xl bg-white p-5 border border-[#d8caee]">
                <p class="font-semibold text-gray-900">{{ $reason['title'] }}</p>
                <p class="mt-2 text-sm leading-6 text-gray-600">{{ $reason['body'] }}</p>
            </div>
        @endforeach
    </div>
</section>
