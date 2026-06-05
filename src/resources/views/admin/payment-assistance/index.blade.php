<x-layouts.portal :title="'Payment Assistance'" heading="Payment Assistance Requests" subheading="Manage high-value programme payment enquiries." portalRole="admin">
    <div class="space-y-6">

        {{-- Filters --}}
        <div class="rounded-3xl bg-white p-6 shadow border border-gray-100">
            <form method="GET" action="{{ route('admin.payment-assistance.index') }}"
                  class="flex flex-col sm:flex-row gap-3">
                <input name="q" type="text" value="{{ $q }}"
                       placeholder="Search by name or email"
                       class="flex-1 min-w-0 rounded-2xl border-gray-300 shadow-sm">

                <select name="status" class="w-full sm:w-44 shrink-0 rounded-2xl border-gray-300 shadow-sm">
                    <option value="">All statuses</option>
                    @foreach(\App\Models\PaymentAssistanceRequest::STATUSES as $s)
                        <option value="{{ $s }}" @selected($status === $s)>
                            {{ \App\Models\PaymentAssistanceRequest::labelFor($s) }}
                        </option>
                    @endforeach
                </select>

                <x-likeslocale.button type="submit" variant="accent">Apply</x-likeslocale.button>
                <a href="{{ route('admin.payment-assistance.index') }}">
                    <x-likeslocale.button type="button" variant="secondary">Reset</x-likeslocale.button>
                </a>
            </form>
        </div>

        @if(session('success'))
            <div class="rounded-3xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if($requests->isEmpty())
            <div class="rounded-3xl bg-white p-8 shadow border border-gray-100 text-center">
                <h3 class="text-xl font-semibold text-gray-900">No requests found</h3>
                <p class="mt-2 text-gray-500">Payment assistance requests will appear here.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($requests as $req)
                    <x-likeslocale.operation-row>
                        <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-gray-900">{{ $req->full_name }}</p>
                                    <x-likeslocale.status-pill tone="brand">{{ $req->program_name }}</x-likeslocale.status-pill>
                                    <x-likeslocale.status-pill :tone="\App\Models\PaymentAssistanceRequest::toneFor($req->status)">
                                        {{ \App\Models\PaymentAssistanceRequest::labelFor($req->status) }}
                                    </x-likeslocale.status-pill>
                                </div>

                                <div class="border-t border-gray-100 mt-3 pt-2.5 space-y-1.5 text-sm">
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-gray-600">
                                        <span>
                                            <a href="mailto:{{ $req->email }}"
                                               class="text-[#6f4cb2] hover:underline">{{ $req->email }}</a>
                                        </span>
                                        @if($req->phone)
                                            <span><span class="font-medium text-gray-700">Phone:</span> {{ $req->phone }}</span>
                                        @endif
                                        @if($req->whatsapp)
                                            <span><span class="font-medium text-gray-700">WhatsApp:</span> {{ $req->whatsapp }}</span>
                                        @endif
                                        <span>
                                            <span class="font-medium text-gray-700">Amount:</span>
                                            <span class="font-bold text-[#6f4cb2]">
                                                {{ $req->currency }} {{ number_format((float)$req->amount, 0) }}
                                            </span>
                                        </span>
                                        <span><span class="font-medium text-gray-700">Submitted:</span> {{ $req->created_at->format('M d, Y') }}</span>
                                    </div>

                                    @if($req->message)
                                        <div class="mt-2 rounded-xl bg-gray-50 border border-gray-200 px-3 py-2 text-sm text-gray-700">
                                            {{ $req->message }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <form method="POST"
                                  action="{{ route('admin.payment-assistance.update-status', $req) }}"
                                  class="flex gap-2 items-center xl:shrink-0">
                                @csrf
                                @method('PATCH')
                                <select name="status" class="rounded-2xl border-gray-300 shadow-sm text-sm">
                                    @foreach(\App\Models\PaymentAssistanceRequest::STATUSES as $s)
                                        <option value="{{ $s }}" @selected($req->status === $s)>
                                            {{ \App\Models\PaymentAssistanceRequest::labelFor($s) }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-likeslocale.button type="submit" variant="accent">Save</x-likeslocale.button>
                            </form>
                        </div>
                    </x-likeslocale.operation-row>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</x-layouts.portal>
