@if($users->isEmpty())
    <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
        <h3 class="text-xl font-semibold text-gray-900">No users found</h3>
        <p class="mt-2 text-gray-500">Try adjusting your filters or add a new employer/sponsor account.</p>
    </div>
@else
    <div class="space-y-3">
        @foreach($users as $user)
            @php
                $roleLabel         = $user->primaryRoleLabel();
                $companyName       = $user->employer?->company_name;
                $accessLabel       = $user->accessSummaryLabel();
                $accessTone        = $user->accessSummaryTone();
                $latestPaymentLabel = $user->latestPaymentLabel();
                $latestPaymentTone  = $user->latestPaymentTone();
            @endphp

            <x-likeslocale.operation-row>
                <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold text-gray-900">{{ $user->name }}</p>
                            <x-likeslocale.status-pill tone="brand">{{ $roleLabel }}</x-likeslocale.status-pill>
                            <x-likeslocale.status-pill :tone="$accessTone">{{ $accessLabel }}</x-likeslocale.status-pill>
                            <x-likeslocale.status-pill :tone="$latestPaymentTone">{{ $latestPaymentLabel }}</x-likeslocale.status-pill>
                            @if($user->must_change_password)
                                <x-likeslocale.status-pill tone="warning">Must Change Password</x-likeslocale.status-pill>
                            @endif
                        </div>

                        <div class="border-t border-gray-100 mt-3 pt-2.5">
                            <div class="text-sm text-gray-600 flex flex-wrap gap-x-4 gap-y-1">
                                <span class="text-gray-500"><x-heroicon-o-envelope class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $user->email }}</span>
                                @if($companyName)
                                    <span><x-heroicon-o-building-office class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $companyName }}</span>
                                @endif
                                <span class="text-gray-500"><x-heroicon-o-calendar-days class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $user->created_at?->format('M d, Y') }}</span>
                                @if($user->latestPaymentRecord())
                                    <span><x-heroicon-o-banknotes class="w-3.5 h-3.5 inline-block mr-0.5 -mt-0.5" />{{ $user->latestPaymentRecord()->currency }} {{ number_format((float) $user->latestPaymentRecord()->amount, 2) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 xl:shrink-0">
                        <x-likeslocale.button :href="route('admin.users.show', $user)" variant="accent">
                            View User
                        </x-likeslocale.button>
                        <x-likeslocale.button :href="route('admin.entitlements.index', ['q' => $user->email])" variant="info">
                            Access
                        </x-likeslocale.button>
                        <x-likeslocale.button :href="route('admin.payments.index', ['q' => $user->email])" variant="success">
                            Payments
                        </x-likeslocale.button>
                    </div>
                </div>
            </x-likeslocale.operation-row>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
@endif
