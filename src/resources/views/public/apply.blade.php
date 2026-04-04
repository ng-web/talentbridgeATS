<x-layouts.portal
    :title="'Apply'"
    heading="Choose Your Program"
    subheading="Select the opportunity that best matches your goals and continue your application journey."
    portalRole="{{ auth()->check() && auth()->user()->hasRole('admin') ? 'admin' : (auth()->check() && auth()->user()->hasRole('employer') ? 'employer' : 'jobseeker') }}"
>
    @if($programs->isEmpty())
        <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
            <h3 class="text-xl font-semibold text-gray-900">No programs available yet</h3>
            <p class="mt-2 text-gray-500">Programs will appear here once they are published.</p>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
            @foreach($programs as $program)
                <div class="rounded-3xl border shadow p-8 flex flex-col justify-between"
                     style="background:#ffffff; border-color:#e5e7eb;">
                    <div>
                        <div class="flex items-center gap-3 mb-4">
                            <x-likeslocale.status-pill tone="brand">
                                Program
                            </x-likeslocale.status-pill>

                            @if(!empty($program->status))
                                <x-likeslocale.status-pill :tone="$program->status === 'active' ? 'success' : 'neutral'">
                                    {{ ucfirst($program->status) }}
                                </x-likeslocale.status-pill>
                            @endif
                        </div>

                        <h3 class="text-2xl font-semibold text-gray-900">
                            {{ $program->name }}
                        </h3>

                        @if(!empty($program->location) || !empty($program->country))
                            <p class="mt-3 text-sm text-gray-500">
                                {{ $program->location ?? '' }}
                                @if(!empty($program->location) && !empty($program->country))
                                    ·
                                @endif
                                {{ $program->country ?? '' }}
                            </p>
                        @endif

                        <p class="mt-4 text-sm leading-7 text-gray-600">
                            {{ \Illuminate\Support\Str::limit($program->description, 220) }}
                        </p>

                        @if(!empty($program->price))
                            <div class="mt-6">
                                <p class="text-3xl font-bold text-[#6f4cb2]">
                                    {{ $program->currency ?? 'JMD' }} {{ number_format((float) $program->price, 2) }}
                                </p>
                                <p class="mt-1 text-sm text-gray-500">
                                    Program access / placement pricing
                                </p>
                            </div>
                        @endif
                    </div>

                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            @if(auth()->user()->hasRole('job_seeker'))
                                <x-likeslocale.button :href="route('jobseeker.jobs.index')" variant="accent">
                                    Continue Application
                                </x-likeslocale.button>
                            @else
                                <x-likeslocale.button :href="route('register')" variant="accent">
                                    Get Started
                                </x-likeslocale.button>
                            @endif
                        @else
                            <x-likeslocale.button :href="route('register')" variant="accent">
                                Apply Now
                            </x-likeslocale.button>

                            <x-likeslocale.button :href="route('pricing')" variant="slate">
                                View Pricing
                            </x-likeslocale.button>
                        @endauth
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.portal>