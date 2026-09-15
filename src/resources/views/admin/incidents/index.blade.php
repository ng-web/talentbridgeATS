<x-layouts.portal :title="'Privacy Incidents'" heading="Incident & Breach Operations" subheading="Privacy-safe processor operations; controller decisions remain human decisions." portalRole="admin">
<div class="space-y-6">
    @if(session('success'))<div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif
    <div class="rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900"><strong>TECHNICAL INCIDENT ≠ LEGAL BREACH DETERMINATION.</strong> Do not enter secrets, credentials, raw payloads, or personal records. The software does not determine notification obligations.</div>

    <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
        <h2 class="text-lg font-semibold">Controlled queue filters</h2>
        <form method="GET" class="mt-3 grid gap-3 md:grid-cols-5">
            <select name="status" class="rounded-xl border-gray-300"><option value="">Any status</option>@foreach($taxonomy::STATUSES as $v)<option @selected(request('status')===$v) value="{{ $v }}">{{ $v }}</option>@endforeach</select>
            <select name="severity" class="rounded-xl border-gray-300"><option value="">Any severity</option>@foreach($taxonomy::SEVERITIES as $v)<option @selected(request('severity')===$v) value="{{ $v }}">{{ $v }}</option>@endforeach</select>
            <select name="escalation" class="rounded-xl border-gray-300"><option value="">Any escalation</option>@foreach($taxonomy::ESCALATION_STATUSES as $v)<option @selected(request('escalation')===$v) value="{{ $v }}">{{ $v }}</option>@endforeach</select>
            <input name="detected_from" type="date" value="{{ request('detected_from') }}" class="rounded-xl border-gray-300">
            <x-likeslocale.button type="submit" variant="secondary">Filter</x-likeslocale.button>
        </form>
        <div class="mt-4 space-y-2">@forelse($incidents as $incident)
            <a class="block rounded-xl border p-4 hover:bg-gray-50" href="{{ route('admin.incidents.show',$incident) }}"><span class="font-medium">{{ $incident->uuid }}</span> · {{ strtoupper($incident->status) }} · {{ strtoupper($incident->technical_severity) }} · owner #{{ $incident->owner_user_id ?? 'unassigned' }} · {{ $incident->escalation_status }} · detected {{ $incident->detected_at }}</a>
        @empty<p class="text-sm text-gray-500">No incidents match these controlled filters.</p>@endforelse</div>
        <div class="mt-4">{{ $incidents->links() }}</div>
    </section>

    @if($canManage)
    <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
        <h2 class="text-lg font-semibold">Record suspected incident</h2>
        <p class="mt-1 text-sm text-gray-600">Use a short minimized technical summary only. Potential scope is not a finding that data was legally breached.</p>
        <form method="POST" action="{{ route('admin.incidents.store') }}" class="mt-4 grid gap-3 md:grid-cols-3">@csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
            <select name="technical_severity" required class="rounded-xl border-gray-300">@foreach($taxonomy::SEVERITIES as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach</select>
            <select name="classification_code" required class="rounded-xl border-gray-300">@foreach($taxonomy::CLASSIFICATIONS as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach</select>
            <input name="detected_at" type="datetime-local" required class="rounded-xl border-gray-300">
            <select name="owner_user_id" class="rounded-xl border-gray-300"><option value="">Current operator</option>@foreach($owners as $owner)<option value="{{ $owner->id }}">Administrator #{{ $owner->id }}</option>@endforeach</select>
            <input name="potentially_affected_subject_count" type="number" min="0" placeholder="Aggregate potentially affected count" class="rounded-xl border-gray-300">
            <textarea name="technical_summary" maxlength="500" required placeholder="Minimized technical summary (no secrets or PII)" class="rounded-xl border-gray-300 md:col-span-3"></textarea>
            <fieldset class="md:col-span-3"><legend class="text-sm font-medium">Potential systems</legend><div class="flex flex-wrap gap-3">@foreach($taxonomy::SYSTEMS as $v)<label class="text-sm"><input type="checkbox" name="system_codes[]" value="{{ $v }}"> {{ $v }}</label>@endforeach</div></fieldset>
            <fieldset class="md:col-span-3"><legend class="text-sm font-medium">Potential data categories</legend><div class="flex flex-wrap gap-3">@foreach($categories as $v)<label class="text-sm"><input type="checkbox" name="data_categories[]" value="{{ $v }}"> {{ $v }}</label>@endforeach</div></fieldset>
            <x-likeslocale.button type="submit" variant="warning">Record suspected incident</x-likeslocale.button>
        </form>
    </section>
    @endif
</div>
</x-layouts.portal>
