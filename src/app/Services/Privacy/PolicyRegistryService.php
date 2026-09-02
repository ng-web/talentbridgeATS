<?php

namespace App\Services\Privacy;

use App\Models\PolicyAcknowledgement;
use App\Models\PolicyDocument;
use App\Models\User;
use App\Services\Security\PrivacyAuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PolicyRegistryService
{
    public function __construct(private readonly PrivacyAuditService $audit) {}

    public function createVersion(User $actor, array $attributes): PolicyDocument
    {
        $canonical = implode("\n", [
            $attributes['type'],
            $attributes['version'],
            $attributes['title'],
            $attributes['content_reference'],
            $attributes['effective_at'],
        ]);

        return DB::transaction(function () use ($actor, $attributes, $canonical): PolicyDocument {
            $document = PolicyDocument::query()->create([
                ...$attributes,
                'canonical_reference_hash' => hash('sha256', $canonical),
                'is_active' => false,
            ]);

            $this->audit->record(
                event: 'policy_document_created',
                actor: $actor,
                resource: $document,
                metadata: ['policy_type' => $document->type, 'policy_version' => $document->version],
            );

            return $document;
        });
    }

    public function activate(User $actor, PolicyDocument $document): PolicyDocument
    {
        return DB::transaction(function () use ($actor, $document): PolicyDocument {
            $documents = PolicyDocument::query()
                ->where('type', $document->type)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $locked = $documents->firstWhere('id', $document->id);

            if (! $locked) {
                throw ValidationException::withMessages(['policy_document' => 'The policy version no longer exists.']);
            }

            if ($locked->effective_at->isFuture()) {
                throw ValidationException::withMessages(['effective_at' => 'A policy version cannot be activated before its effective time.']);
            }

            PolicyDocument::query()
                ->where('type', $locked->type)
                ->where('is_active', true)
                ->whereKeyNot($locked->getKey())
                ->update(['is_active' => false, 'retired_at' => now()]);

            $locked->fill([
                'is_active' => true,
                'retired_at' => null,
                'approved_at' => now(),
                'approved_by_user_id' => $actor->id,
            ])->save();

            $this->audit->record(
                event: 'policy_document_activated',
                actor: $actor,
                resource: $locked,
                metadata: ['policy_type' => $locked->type, 'policy_version' => $locked->version],
            );

            return $locked->fresh();
        });
    }

    /**
     * Lock and validate the policy versions submitted with registration.
     *
     * @param  array<string, mixed>  $submitted
     * @return array<string, PolicyDocument>
     */
    public function lockRegistrationPolicies(array $submitted): array
    {
        if (! config('privacy.registration.evidence_enabled')) {
            return [];
        }

        $locked = [];
        foreach ($this->registrationRequirements() as $policyType => $settings) {
            $documents = PolicyDocument::query()
                ->where('type', $policyType)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $current = $documents
                ->filter(fn (PolicyDocument $document): bool => $document->is_active && $document->approved_at !== null && ! $document->effective_at->isFuture())
                ->sortByDesc('effective_at')
                ->first();
            $submittedId = $submitted[$policyType] ?? null;

            if ($settings['required'] && ! $current) {
                throw ValidationException::withMessages(['policy_documents' => 'Registration policy evidence is temporarily unavailable.']);
            }
            if (! $current) {
                continue;
            }
            if ((string) $submittedId !== (string) $current->id) {
                throw ValidationException::withMessages(['policy_documents' => 'The policy version changed. Review the current version and submit again.']);
            }
            $locked[$policyType] = $current;
        }

        return $locked;
    }

    /** @param array<string, mixed> $submitted */
    public function recordRegistrationAcknowledgements(User $user, array $submitted, ?array $lockedPolicies = null): void
    {
        if (! config('privacy.registration.evidence_enabled')) {
            return;
        }

        $requirements = $this->registrationRequirements();
        $lockedPolicies ??= $this->lockRegistrationPolicies($submitted);

        foreach ($requirements as $policyType => $settings) {
            $current = $lockedPolicies[$policyType] ?? null;
            if (! $current) {
                continue;
            }

            $acknowledgement = PolicyAcknowledgement::query()->create([
                'user_id' => $user->id,
                'policy_document_id' => $current->id,
                'acknowledgement_type' => $settings['type'],
                'source_context' => 'registration',
                'acknowledged_at' => now(),
            ]);

            $this->audit->record(
                event: 'policy_acknowledged',
                actor: $user,
                resource: $acknowledgement,
                subjectUserId: $user->id,
                metadata: ['policy_type' => $current->type, 'policy_version' => $current->version],
            );
        }
    }

    /** @return array<string, array{required: bool, type: string}> */
    private function registrationRequirements(): array
    {
        return [
            PolicyDocument::TYPE_PRIVACY_NOTICE => [
                'required' => (bool) config('privacy.registration.require_privacy_notice'),
                'type' => PolicyAcknowledgement::TYPE_NOTICE_ACKNOWLEDGED,
            ],
            PolicyDocument::TYPE_TERMS_OF_SERVICE => [
                'required' => (bool) config('privacy.registration.require_terms'),
                'type' => PolicyAcknowledgement::TYPE_TERMS_ACCEPTED,
            ],
        ];
    }
}
