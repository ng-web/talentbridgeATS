<x-layouts.portal :title="'Privacy Request'" heading="Privacy Request" :subheading="$privacyRequest->uuid" portalRole="admin">
    <div class="space-y-6">
        @if(session('success'))<div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif

        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">Controller detail</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-3 text-sm">
                <div><dt class="text-gray-500">Subject</dt><dd>{{ $privacyRequest->subject->name }} · {{ $privacyRequest->subject->email }}</dd></div>
                <div><dt class="text-gray-500">Type / state</dt><dd>{{ $privacyRequest->request_type }} · {{ $privacyRequest->state }}</dd></div>
                <div><dt class="text-gray-500">Identity verification</dt><dd>{{ $privacyRequest->identity_verification_state }}</dd></div>
            </dl>
        </section>

        @can('privacy.requests.manage')
            <section class="grid gap-4 rounded-3xl border border-gray-100 bg-white p-6 shadow md:grid-cols-3">
                @if(in_array($privacyRequest->state, \App\Services\Privacy\PrivacyRequestWorkflow::ASSIGNABLE_STATES, true))
                    <form method="POST" action="{{ route('admin.privacy-requests.assign', $privacyRequest) }}" class="space-y-2">
                        @csrf
                        <label class="text-sm font-medium">Assign named admin</label>
                        <select name="assigned_admin_id" required class="w-full rounded-xl border-gray-300">
                            @foreach($administrators as $administrator)<option value="{{ $administrator->id }}" @selected($privacyRequest->assigned_admin_id === $administrator->id)>{{ $administrator->name }}</option>@endforeach
                        </select>
                        <x-likeslocale.button type="submit" variant="secondary">Assign</x-likeslocale.button>
                    </form>
                @else
                    <p class="text-sm text-gray-500">Assignment is locked after a controller decision or terminal state.</p>
                @endif

                <form method="POST" action="{{ route('admin.privacy-requests.transition', $privacyRequest) }}" class="space-y-2">
                    @csrf
                    <label class="text-sm font-medium">Allowed workflow transition</label>
                    <select name="state" required class="w-full rounded-xl border-gray-300">
                        @foreach([\App\Models\PrivacyRequest::STATE_UNDER_REVIEW, \App\Models\PrivacyRequest::STATE_IDENTITY_REQUIRED, \App\Models\PrivacyRequest::STATE_DECISION_REQUIRED, \App\Models\PrivacyRequest::STATE_FULFILLED, \App\Models\PrivacyRequest::STATE_CLOSED] as $state)<option value="{{ $state }}">{{ str_replace('_', ' ', $state) }}</option>@endforeach
                    </select>
                    <x-likeslocale.button type="submit" variant="secondary">Advance</x-likeslocale.button>
                </form>

                <form method="POST" action="{{ route('admin.privacy-requests.verify-identity', $privacyRequest) }}" class="space-y-2">
                    @csrf
                    <label class="text-sm font-medium">Record completed Kairox verification</label>
                    <select name="method" required class="w-full rounded-xl border-gray-300">
                        @foreach(config('privacy.identity_verification_methods', []) as $method)<option value="{{ $method }}">{{ str_replace('_', ' ', $method) }}</option>@endforeach
                    </select>
                    <x-likeslocale.button type="submit" variant="secondary">Record verification</x-likeslocale.button>
                </form>
            </section>
        @endcan

        @can('privacy.requests.decide')
            <section class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
                <h2 class="font-semibold">Controller decision — password confirmation required</h2>
                <form method="POST" action="{{ route('admin.privacy-requests.decide', $privacyRequest) }}" class="mt-3 flex flex-wrap gap-3">
                    @csrf
                    <select name="state" class="rounded-xl border-gray-300"><option value="approved">Approved</option><option value="partially_approved">Partially approved</option><option value="refused">Refused</option></select>
                    <select name="controller_decision_code" class="rounded-xl border-gray-300"><option value="controller_approved">Controller approved</option><option value="controller_partially_approved">Controller partially approved</option><option value="controller_refused">Controller refused</option></select>
                    <x-likeslocale.button type="submit" variant="accent">Record decision</x-likeslocale.button>
                </form>
            </section>
        @endcan

        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">Technical data inventory</h2>
            <div class="mt-4 grid gap-2 md:grid-cols-2">
                @foreach($inventory as $item)<div class="rounded-xl bg-gray-50 p-3 text-sm"><span class="font-medium">{{ str_replace('_', ' ', $item['category']) }}</span>: {{ $item['record_count'] }} · {{ $item['export_eligible'] ? 'eligible structured data' : 'not eligible by default' }} · {{ str_replace('_', ' ', $item['retention_flag']) }}</div>@endforeach
            </div>
        </section>

        @can('privacy.exports.authorize')
            <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
                <h2 class="text-lg font-semibold">Authorize private export scope</h2>
                <p class="mt-1 text-sm text-gray-500">High-risk documents are not available in this Pass 2 scope.</p>
                <form method="POST" action="{{ route('admin.privacy-requests.exports.authorize', $privacyRequest) }}" class="mt-3 flex flex-wrap gap-3">
                    @csrf
                    @foreach(\App\Models\DataExport::ALLOWED_SCOPES as $scope)<label class="text-sm"><input type="checkbox" name="scope[]" value="{{ $scope }}" class="rounded border-gray-300"> {{ str_replace('_', ' ', $scope) }}</label>@endforeach
                    <x-likeslocale.button type="submit" variant="accent">Authorize export</x-likeslocale.button>
                </form>
            </section>
        @endcan

        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">Private exports</h2>
            <div class="mt-3 space-y-2">
                @forelse($privacyRequest->exports as $export)
                    <div class="flex flex-wrap items-center gap-3 rounded-xl bg-gray-50 p-3 text-sm">
                        <span class="font-mono">{{ $export->uuid }}</span><span>{{ $export->status }}</span>
                        @if($export->status === \App\Models\DataExport::STATUS_AUTHORIZED)@can('privacy.exports.generate')<form method="POST" action="{{ route('admin.privacy-requests.exports.generate', [$privacyRequest, $export]) }}">@csrf<button class="text-[#6f4cb2] underline">Queue generation</button></form>@endcan @endif
                        @if($export->status === \App\Models\DataExport::STATUS_FAILED)@can('privacy.exports.generate')<form method="POST" action="{{ route('admin.privacy-requests.exports.retry', [$privacyRequest, $export]) }}">@csrf<button class="text-[#6f4cb2] underline">Explicitly retry generation</button></form>@endcan @endif
                        @if(in_array($export->status, [\App\Models\DataExport::STATUS_AUTHORIZED, \App\Models\DataExport::STATUS_FAILED, \App\Models\DataExport::STATUS_READY], true))@can('privacy.exports.authorize')<form method="POST" action="{{ route('admin.privacy-requests.exports.reauthorize', [$privacyRequest, $export]) }}">@csrf<button class="text-[#6f4cb2] underline">Reauthorize</button></form>@endcan @endif
                        @if($export->status === \App\Models\DataExport::STATUS_READY)@can('privacy.exports.download')<a class="text-[#6f4cb2] underline" href="{{ route('admin.privacy-requests.exports.download', [$privacyRequest, $export]) }}">Controlled download</a>@endcan @endif
                    </div>
                @empty<p class="text-sm text-gray-500">No exports authorized.</p>@endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">Append-only request history</h2>
            <div class="mt-3 space-y-2">@foreach($privacyRequest->events as $event)<div class="rounded-xl bg-gray-50 p-3 text-sm">{{ $event->event }} · {{ $event->from_state ?? 'start' }} → {{ $event->to_state ?? 'unchanged' }} · {{ $event->created_at->toDayDateTimeString() }}</div>@endforeach</div>
        </section>
    </div>
</x-layouts.portal>
