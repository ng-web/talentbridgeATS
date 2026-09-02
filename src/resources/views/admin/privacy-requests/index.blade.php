<x-layouts.portal :title="'Privacy Requests'" heading="Privacy Requests" subheading="Controller request queue with minimized list data." portalRole="admin">
    <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead><tr class="border-b text-left text-xs uppercase text-gray-500"><th class="p-3">Reference</th><th class="p-3">Type</th><th class="p-3">State</th><th class="p-3">Submitted</th><th class="p-3">Assignee</th><th class="p-3">Verification</th></tr></thead>
            <tbody>
                @forelse($privacyRequests as $privacyRequest)
                    <tr class="border-b border-gray-100">
                        <td class="p-3"><a class="font-mono text-[#6f4cb2]" href="{{ route('admin.privacy-requests.show', $privacyRequest) }}">{{ $privacyRequest->uuid }}</a></td>
                        <td class="p-3">{{ ucfirst($privacyRequest->request_type) }}</td>
                        <td class="p-3">{{ str_replace('_', ' ', $privacyRequest->state) }}</td>
                        <td class="p-3">{{ $privacyRequest->submitted_at->toDateString() }}</td>
                        <td class="p-3">{{ $privacyRequest->assignee?->name ?? 'Unassigned' }}</td>
                        <td class="p-3">{{ str_replace('_', ' ', $privacyRequest->identity_verification_state) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-gray-500">No privacy requests.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $privacyRequests->links() }}</div>
    </div>
</x-layouts.portal>
