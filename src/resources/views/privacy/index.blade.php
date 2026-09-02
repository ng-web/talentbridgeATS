<x-layouts.portal :title="'Privacy Centre'" heading="Privacy Centre" subheading="View policy evidence and submit or track privacy requests." portalRole="jobseeker">
    <div class="space-y-6">
        @if(session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <h2 class="text-lg font-semibold text-gray-900">Current policy documents</h2>
            <div class="mt-4 space-y-3">
                @forelse($policies as $policy)
                    <a href="{{ $policy->content_reference }}" target="_blank" rel="noopener noreferrer" class="block rounded-2xl border border-gray-200 p-4 hover:border-[#6f4cb2]">
                        <span class="font-medium text-gray-900">{{ $policy->title }}</span>
                        <span class="ml-2 text-sm text-gray-500">Version {{ $policy->version }} · effective {{ $policy->effective_at->toFormattedDateString() }}</span>
                    </a>
                @empty
                    <p class="text-sm text-gray-500">No controller-approved policy document is currently published in the registry.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <h2 class="text-lg font-semibold text-gray-900">Your recorded policy evidence</h2>
            <div class="mt-4 space-y-2 text-sm">
                @forelse($acknowledgements as $acknowledgement)
                    <div class="rounded-xl bg-gray-50 p-3">
                        {{ $acknowledgement->policyDocument->title }} — version {{ $acknowledgement->policyDocument->version }}
                        <span class="text-gray-500">({{ str_replace('_', ' ', $acknowledgement->acknowledgement_type) }}, {{ $acknowledgement->acknowledged_at->toDayDateTimeString() }})</span>
                    </div>
                @empty
                    <p class="text-gray-500">No recorded acknowledgement. Existing accounts are not backfilled.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <h2 class="text-lg font-semibold text-gray-900">Submit a privacy request</h2>
            <p class="mt-1 text-sm text-gray-500">Submission starts controller review and does not automatically change or delete data.</p>
            <form method="POST" action="{{ route('privacy.store') }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
                @csrf
                <select name="request_type" required class="rounded-2xl border-gray-300">
                    <option value="">Choose a request category</option>
                    @foreach(\App\Models\PrivacyRequest::TYPES as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
                <x-likeslocale.button type="submit" variant="accent">Submit for review</x-likeslocale.button>
            </form>
            <x-input-error :messages="$errors->get('request_type')" class="mt-2" />
        </section>

        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <h2 class="text-lg font-semibold text-gray-900">Your requests</h2>
            <div class="mt-4 space-y-2">
                @forelse($privacyRequests as $privacyRequest)
                    <a href="{{ route('privacy.show', $privacyRequest) }}" class="flex items-center justify-between rounded-xl border border-gray-200 p-3 hover:border-[#6f4cb2]">
                        <span class="font-mono text-sm">{{ $privacyRequest->uuid }}</span>
                        <span class="text-sm text-gray-600">{{ ucfirst($privacyRequest->request_type) }} · {{ str_replace('_', ' ', ucfirst($privacyRequest->state)) }}</span>
                    </a>
                @empty
                    <p class="text-sm text-gray-500">No privacy requests submitted.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.portal>
