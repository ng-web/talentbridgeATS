<x-layouts.portal :title="'Policy Registry'" heading="Policy Registry" subheading="Register immutable references to Kairox-approved documents." portalRole="admin">
    <div class="space-y-6">
        @if(session('success'))<div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
        @can('privacy.policy.manage')
            <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
                <h2 class="text-lg font-semibold">Register a new immutable version</h2>
                <p class="mt-1 text-sm text-amber-700">Only enter a document Kairox has approved. No legal wording is created here.</p>
                <form method="POST" action="{{ route('admin.policy-documents.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                    @csrf
                    <select name="type" required class="rounded-xl border-gray-300"><option value="privacy_notice">Privacy Notice</option><option value="terms_of_service">Terms of Service</option></select>
                    <input name="version" required maxlength="50" placeholder="Approved version reference" class="rounded-xl border-gray-300">
                    <input name="title" required maxlength="255" placeholder="Approved document title" class="rounded-xl border-gray-300">
                    <input name="content_reference" type="url" required placeholder="https:// approved document URL" class="rounded-xl border-gray-300">
                    <input name="effective_at" type="datetime-local" required class="rounded-xl border-gray-300">
                    <x-likeslocale.button type="submit" variant="accent">Register version</x-likeslocale.button>
                </form>
            </section>
        @endcan
        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <div class="space-y-3">
                @forelse($documents as $document)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 p-4">
                        <div><div class="font-medium">{{ $document->title }} · {{ $document->version }}</div><div class="text-xs text-gray-500">{{ $document->type }} · Canonical reference SHA-256 {{ $document->canonical_reference_hash }} · {{ $document->is_active ? 'active' : 'historical/draft' }}</div></div>
                        @can('privacy.policy.manage')@unless($document->is_active)<form method="POST" action="{{ route('admin.policy-documents.activate', $document) }}">@csrf<x-likeslocale.button type="submit" variant="secondary">Activate approved version</x-likeslocale.button></form>@endunless @endcan
                    </div>
                @empty<p class="text-sm text-gray-500">No policy versions registered.</p>@endforelse
            </div>
        </section>
    </div>
</x-layouts.portal>
