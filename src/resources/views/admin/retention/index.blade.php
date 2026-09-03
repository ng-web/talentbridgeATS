<x-layouts.portal :title="'Retention & Disposition'" heading="Retention, Holds & Disposition" subheading="Processor-side technical controls. Kairox remains responsible for legal decisions." portalRole="admin">
    <div class="space-y-6">
        @if(session('success'))<div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm text-amber-900">
            <strong>TECHNICAL ELIGIBILITY UNDER CONFIGURED RULE — NOT A LEGAL CONCLUSION.</strong>
            Destructive execution is never scheduled, requires current authorization, and rechecks rules, lifecycle, identity and active holds.
        </div>

        @if($directPermissions['privacy.retention.manage'])
        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">Draft retention rule</h2>
            <p class="mt-1 text-sm text-gray-600">No periods are preloaded. Enter only a Kairox-approved technical configuration; approval is a separate action.</p>
            <form method="POST" action="{{ route('admin.retention.rules.store') }}" class="mt-4 grid gap-3 md:grid-cols-3">@csrf
                <select name="data_category" class="rounded-xl border-gray-300"><option value="applicant_document">Applicant document (supported)</option></select>
                <input name="version" type="number" min="1" required placeholder="Version" class="rounded-xl border-gray-300">
                <select name="trigger_type" class="rounded-xl border-gray-300"><option value="record_created">Record created (technical)</option></select>
                <input name="retention_value" type="number" min="0" max="36500" required placeholder="Controller-approved value" class="rounded-xl border-gray-300">
                <select name="retention_unit" class="rounded-xl border-gray-300"><option value="days">Days</option><option value="months">Months</option><option value="years">Years</option></select>
                <select name="disposition_method" class="rounded-xl border-gray-300"><option value="detach_and_delete_file">Detach and delete file</option></select>
                <input name="effective_at" type="datetime-local" required class="rounded-xl border-gray-300">
                <x-likeslocale.button type="submit" variant="secondary">Create draft</x-likeslocale.button>
            </form>
        </section>
        @endif

        @if($directPermissions['privacy.retention.view'])
        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">Versioned rules</h2>
            <div class="mt-3 space-y-3">@forelse($rules as $rule)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4">
                    <div><div class="font-medium">{{ $rule->data_category }} · v{{ $rule->version }} · {{ strtoupper($rule->status) }}</div><div class="text-xs text-gray-500">{{ $rule->trigger_type }} + {{ $rule->retention_value }} {{ $rule->retention_unit }} → {{ $rule->disposition_method }} · effective {{ $rule->effective_at }} · SHA-256 {{ $rule->canonical_configuration_hash }}</div></div>
                    <div class="flex gap-2">
                        @if($rule->status === 'draft' && $directPermissions['privacy.retention.approve'])<form method="POST" action="{{ route('admin.retention.rules.approve', $rule) }}">@csrf<x-likeslocale.button type="submit" variant="warning">Approve</x-likeslocale.button></form>@endif
                        @if($rule->status === 'approved' && $directPermissions['privacy.retention.approve'])<form method="POST" action="{{ route('admin.retention.rules.retire', $rule) }}">@csrf<x-likeslocale.button type="submit" variant="secondary">Retire</x-likeslocale.button></form>@endif
                    </div>
                </div>
            @empty <p class="text-sm text-gray-500">No retention rules configured. Automated destructive action is therefore impossible.</p> @endforelse</div>
        </section>
        @endif

        @if($directPermissions['privacy.holds.manage'])
        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <h2 class="text-lg font-semibold">Issue legal hold</h2><p class="mt-1 text-sm text-gray-600">Use controlled codes only. Subject scope is broadest. Resource scope is supported only for applicant documents.</p>
            <form method="POST" action="{{ route('admin.retention.holds.store') }}" class="mt-4 grid gap-3 md:grid-cols-3">@csrf
                <input name="subject_user_id" type="number" min="1" required placeholder="Subject user ID" class="rounded-xl border-gray-300">
                <select name="scope_type" class="rounded-xl border-gray-300"><option value="subject">Entire subject</option><option value="category">Category</option><option value="resource">Specific resource</option></select>
                <select name="data_category" class="rounded-xl border-gray-300"><option value="">None for subject scope</option>@foreach($categories as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach</select>
                <input name="resource_id" type="number" min="1" placeholder="Resource ID (resource scope only)" class="rounded-xl border-gray-300">
                <select name="hold_code" class="rounded-xl border-gray-300">@foreach(\App\Models\LegalHold::HOLD_CODES as $code)<option value="{{ $code }}">{{ $code }}</option>@endforeach</select>
                <input name="effective_at" type="datetime-local" required class="rounded-xl border-gray-300">
                <x-likeslocale.button type="submit" variant="warning">Issue hold</x-likeslocale.button>
            </form>
        </section>
        @endif

        @if($directPermissions['privacy.holds.view'])
        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow"><h2 class="text-lg font-semibold">Legal holds</h2><div class="mt-3 space-y-2">@forelse($holds as $hold)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3 text-sm"><span>{{ $hold->uuid }} · subject #{{ $hold->subject_user_id }} · {{ $hold->scope_type }}{{ $hold->data_category ? ' / '.$hold->data_category : '' }} · {{ strtoupper($hold->status) }} · {{ $hold->hold_code }}</span>
            @if($hold->status === 'active' && $directPermissions['privacy.holds.manage'])<form method="POST" action="{{ route('admin.retention.holds.release', $hold) }}">@csrf<x-likeslocale.button type="submit" variant="secondary">Release hold</x-likeslocale.button></form>@endif</div>
        @empty<p class="text-sm text-gray-500">No holds recorded.</p>@endforelse</div></section>
        @endif

        @if($directPermissions['privacy.disposition.plan'] || $directPermissions['privacy.disposition.authorize'] || $directPermissions['privacy.disposition.execute'])
        <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow">
            <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-semibold">Disposition plans</h2><p class="text-sm text-gray-600">Current aggregate: {{ $eligibleCount }} technically eligible applicant documents. Creating a plan is non-destructive.</p></div>
            @if($directPermissions['privacy.disposition.plan'])<form method="POST" action="{{ route('admin.retention.plans.store') }}">@csrf<input type="hidden" name="data_category" value="applicant_document"><x-likeslocale.button type="submit" variant="secondary">Create reviewable plan</x-likeslocale.button></form>@endif</div>
            <div class="mt-3 space-y-3">@forelse($plans as $plan)<div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4 text-sm"><span>{{ $plan->uuid }} · {{ $plan->data_category }} · rule v{{ $plan->rule->version }} · {{ $plan->items_count }} items ({{ $plan->disposed_items_count }} disposed / {{ $plan->skipped_items_count }} skipped / {{ $plan->failed_items_count }} failed; cleanup {{ $plan->pending_cleanup_count }} pending / {{ $plan->failed_cleanup_count }} failed) · {{ strtoupper($plan->status) }} · expires {{ $plan->expires_at }}</span><div class="flex gap-2">
                @if($plan->status === 'planned' && $directPermissions['privacy.disposition.authorize'])<form method="POST" action="{{ route('admin.retention.plans.authorize', $plan) }}">@csrf<x-likeslocale.button type="submit" variant="warning">Authorize</x-likeslocale.button></form>@endif
                @if(in_array($plan->status, ['planned','authorized']) && $directPermissions['privacy.disposition.authorize'])<form method="POST" action="{{ route('admin.retention.plans.revoke', $plan) }}">@csrf<x-likeslocale.button type="submit" variant="secondary">Revoke</x-likeslocale.button></form>@endif
                @if($plan->status === 'authorized' && $directPermissions['privacy.disposition.execute'])<form method="POST" action="{{ route('admin.retention.plans.execute', $plan) }}">@csrf<x-likeslocale.button type="submit" variant="danger">Execute destructive disposition</x-likeslocale.button></form>@endif
            </div></div>@empty<p class="text-sm text-gray-500">No plans.</p>@endforelse</div>
        </section>
        @endif
    </div>
</x-layouts.portal>
