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

                        @if(!empty($program->age_range))
                            <x-likeslocale.status-pill tone="neutral">
                                {{ $program->age_range }}
                            </x-likeslocale.status-pill>
                        @endif
                    </div>

                    <h3 class="text-2xl font-semibold text-gray-900">
                        {{ $program->name }}
                    </h3>

                    <p class="mt-4 text-sm leading-7 text-gray-600">
                        {{ $program->description }}
                    </p>

                    @if(!empty($program->benefits))
                        <ul class="mt-6 space-y-2.5 text-sm text-gray-700">
                            @foreach($program->benefits as $benefit)
                                <li class="flex items-start gap-2">
                                    <x-heroicon-o-check-circle class="w-4 h-4 mt-0.5 shrink-0 text-[#6f4cb2]" />
                                    <span>{{ $benefit }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if(!empty($program->typical_roles))
                        <div class="mt-6 rounded-2xl bg-[#efe8fb] p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#6f4cb2]">Typical Roles</p>
                            <p class="mt-2 text-sm leading-6 text-gray-700">{{ $program->typical_roles }}</p>
                        </div>
                    @endif

                    @if(!empty($program->fields_available))
                        <div class="mt-6 rounded-2xl bg-[#efe8fb] p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-[#6f4cb2]">Fields Available</p>
                            <p class="mt-2 text-sm leading-6 text-gray-700">{{ $program->fields_available }}</p>
                        </div>
                    @endif
                </div>

                @if($showActions ?? false)
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
                @endif
            </div>
        @endforeach
    </div>
@endif
