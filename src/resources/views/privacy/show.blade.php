<x-layouts.portal :title="'Privacy Request'" heading="Privacy Request" :subheading="$privacyRequest->uuid" portalRole="jobseeker">
    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <dl class="grid gap-4 sm:grid-cols-3">
                <div><dt class="text-xs uppercase text-gray-500">Category</dt><dd class="font-medium">{{ ucfirst($privacyRequest->request_type) }}</dd></div>
                <div><dt class="text-xs uppercase text-gray-500">Status</dt><dd class="font-medium">{{ str_replace('_', ' ', ucfirst($privacyRequest->state)) }}</dd></div>
                <div><dt class="text-xs uppercase text-gray-500">Submitted</dt><dd class="font-medium">{{ $privacyRequest->submitted_at->toDayDateTimeString() }}</dd></div>
            </dl>
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">Status history</h2>
            <div class="mt-4 space-y-3">
                @foreach($privacyRequest->events as $event)
                    <div class="rounded-xl bg-gray-50 p-3 text-sm">
                        <span class="font-medium">{{ str_replace('_', ' ', ucfirst($event->event)) }}</span>
                        @if($event->to_state)<span class="text-gray-600"> · {{ str_replace('_', ' ', $event->to_state) }}</span>@endif
                        <span class="block text-xs text-gray-500">{{ $event->created_at->toDayDateTimeString() }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.portal>
