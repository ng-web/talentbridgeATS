<?php

namespace App\Services\Privacy;

use App\Models\PrivacyIncident;
use App\Models\PrivacyIncidentDecision;
use App\Models\PrivacyIncidentEvent;
use App\Models\PrivacyIncidentHold;
use App\Models\PrivacyIncidentScopeVersion;
use App\Models\User;
use App\Services\Security\PrivacyAuditService;
use App\Support\PrivacySecurityPermissions;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class IncidentService
{
    public function __construct(
        private readonly PrivacyAuditService $audit,
        private readonly LegalHoldService $holds,
        private readonly IncidentMutationBarrier $barrier,
    ) {}

    public function create(IncidentActorEvidence $evidence, array $attributes, string $idempotencyKey): PrivacyIncident
    {
        $this->uuid($idempotencyKey);

        try {
            return DB::transaction(function () use ($evidence, $attributes, $idempotencyKey): PrivacyIncident {
                $actor = $this->actor($evidence, PrivacySecurityPermissions::INCIDENTS_MANAGE);
                $values = $this->creationValues($attributes, $actor);
                $this->authorizedOwner($values['owner_user_id']);
                $this->barrier->reached(IncidentMutationBarrier::AFTER_CREATE_AUTHORITY);
                $existing = PrivacyIncident::query()->where('creation_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing) {
                    return $this->existingCreate($existing, $actor, $values);
                }
                $incident = $this->persist(new PrivacyIncident, [
                    'creation_key' => $idempotencyKey,
                    'status' => 'open',
                    'technical_severity' => $values['technical_severity'],
                    'classification_code' => $values['classification_code'],
                    'technical_summary' => $values['technical_summary'],
                    'detected_at' => $values['detected_at'],
                    'opened_at' => now(),
                    'created_by_user_id' => $actor->id,
                    'owner_user_id' => $values['owner_user_id'],
                    'escalation_status' => 'not_escalated',
                ]);
                $this->appendScope($incident, $actor, $values['scope']);
                $this->event($incident, $actor, 'incident_created', $idempotencyKey, [
                    'technical_severity' => $incident->technical_severity,
                    'classification_code' => $incident->classification_code,
                ]);
                $this->audit->record('incident.created', $actor, $incident, metadata: [
                    'incident_status' => 'open', 'technical_severity' => $incident->technical_severity,
                    'classification_code' => $incident->classification_code,
                ]);

                return $incident->fresh();
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return DB::transaction(function () use ($evidence, $attributes, $idempotencyKey, $exception): PrivacyIncident {
                $actor = $this->actor($evidence, PrivacySecurityPermissions::INCIDENTS_MANAGE);
                $values = $this->creationValues($attributes, $actor);
                $this->authorizedOwner($values['owner_user_id']);
                $existing = PrivacyIncident::query()->where('creation_key', $idempotencyKey)->lockForUpdate()->first();
                if (! $existing) {
                    throw $exception;
                }

                return $this->existingCreate($existing, $actor, $values);
            });
        }
    }

    public function assign(IncidentActorEvidence $evidence, PrivacyIncident $incident, int $ownerId, string $key): PrivacyIncident
    {
        return $this->mutate($evidence, $incident, PrivacySecurityPermissions::INCIDENTS_MANAGE, $key,
            function (User $actor, PrivacyIncident $current) use ($ownerId, $key): void {
                $this->authorizedOwner($ownerId);
                $current->forceFill(['owner_user_id' => $ownerId, 'lock_version' => $current->lock_version + 1])->save();
                $this->event($current, $actor, 'incident_assigned', $key, ['owner_user_id' => $ownerId]);
                $this->audit->record('incident.assigned', $actor, $current, metadata: ['incident_status' => $current->status]);
            });
    }

    public function changeSeverity(IncidentActorEvidence $evidence, PrivacyIncident $incident, string $severity, string $key): PrivacyIncident
    {
        return $this->mutate($evidence, $incident, PrivacySecurityPermissions::INCIDENTS_MANAGE, $key,
            function (User $actor, PrivacyIncident $current) use ($severity, $key): void {
                $severity = $this->allowed($severity, IncidentTaxonomy::SEVERITIES, 'technical_severity');
                $current->forceFill(['technical_severity' => $severity, 'lock_version' => $current->lock_version + 1])->save();
                $this->event($current, $actor, 'severity_changed', $key, ['technical_severity' => $severity]);
                $this->audit->record('incident.severity_changed', $actor, $current, metadata: ['technical_severity' => $severity]);
            });
    }

    public function updateScope(IncidentActorEvidence $evidence, PrivacyIncident $incident, array $attributes, string $key): PrivacyIncident
    {
        return $this->mutate($evidence, $incident, PrivacySecurityPermissions::INCIDENTS_MANAGE, $key,
            function (User $actor, PrivacyIncident $current) use ($attributes, $key): void {
                $scope = $this->scopeValues($attributes);
                $this->appendScope($current, $actor, $scope);
                $this->event($current, $actor, 'scope_updated', $key, [
                    'category_count' => count($scope['data_categories']), 'system_count' => count($scope['system_codes']),
                    'subject_count_recorded' => $scope['potentially_affected_subject_count'] !== null,
                ]);
                $this->audit->record('incident.scope_changed', $actor, $current, metadata: [
                    'scope_count' => count($scope['data_categories']), 'system_count' => count($scope['system_codes']),
                    'subject_count_recorded' => $scope['potentially_affected_subject_count'] !== null,
                ]);
            });
    }

    public function transition(IncidentActorEvidence $evidence, PrivacyIncident $incident, string $target, string $action, string $result, string $key): PrivacyIncident
    {
        return $this->mutate($evidence, $incident, PrivacySecurityPermissions::INCIDENTS_MANAGE, $key,
            function (User $actor, PrivacyIncident $current) use ($target, $action, $result, $key): void {
                $target = $this->allowed($target, IncidentTaxonomy::STATUSES, 'status');
                $action = $this->allowed($action, IncidentTaxonomy::ACTION_CODES, 'action_code');
                $result = $this->allowed($result, IncidentTaxonomy::ACTION_RESULTS, 'action_result');
                if (! $current->mayTransitionTo($target)) {
                    throw ValidationException::withMessages(['status' => 'That incident state transition is not permitted.']);
                }
                if (! IncidentTaxonomy::supportsTransitionEvidence($target, $action, $result)) {
                    throw ValidationException::withMessages(['action_result' => 'That technical action and result cannot evidence the requested incident state.']);
                }
                $from = $current->status;
                $timestamps = match ($target) {
                    'contained' => ['contained_at' => now()],
                    'recovering' => ['recovery_started_at' => now()],
                    'resolved' => ['recovered_at' => now(), 'resolved_at' => now()],
                    default => [],
                };
                $current->forceFill([...$timestamps, 'status' => $target, 'lock_version' => $current->lock_version + 1])->save();
                $type = match ($target) {
                    'triage' => 'triage_started', 'investigating' => 'investigation_updated',
                    'contained' => 'containment_completed', 'recovering' => 'recovery_started',
                    'resolved' => 'incident_resolved', default => 'investigation_updated',
                };
                $this->event($current, $actor, $type, $key, ['from_status' => $from, 'to_status' => $target, 'action_code' => $action, 'action_result' => $result]);
                $this->audit->record('incident.state_changed', $actor, $current, metadata: ['from_status' => $from, 'incident_status' => $target, 'action_code' => $action, 'action_result' => $result]);
            });
    }

    public function escalate(IncidentActorEvidence $evidence, PrivacyIncident $incident, string $key): PrivacyIncident
    {
        return $this->mutate($evidence, $incident, PrivacySecurityPermissions::INCIDENTS_ESCALATE, $key,
            function (User $actor, PrivacyIncident $current) use ($key): void {
                if ($current->escalation_status !== 'not_escalated') {
                    throw ValidationException::withMessages(['escalation' => 'The incident was already escalated.']);
                }
                $current->forceFill(['escalation_status' => 'escalated', 'escalated_at' => now(), 'escalated_by_user_id' => $actor->id, 'lock_version' => $current->lock_version + 1])->save();
                $this->event($current, $actor, 'processor_escalated', $key);
                $this->audit->record('incident.escalated', $actor, $current, metadata: ['escalation_status' => 'escalated']);
            });
    }

    public function acknowledge(IncidentActorEvidence $evidence, PrivacyIncident $incident, string $key): PrivacyIncident
    {
        return $this->mutate($evidence, $incident, PrivacySecurityPermissions::INCIDENTS_CONTROLLER_DECIDE, $key,
            function (User $actor, PrivacyIncident $current) use ($key): void {
                if ($current->escalation_status !== 'escalated') {
                    throw ValidationException::withMessages(['escalation' => 'Only an unacknowledged escalation can be acknowledged.']);
                }
                $current->forceFill(['escalation_status' => 'acknowledged', 'acknowledged_at' => now(), 'acknowledged_by_user_id' => $actor->id, 'lock_version' => $current->lock_version + 1])->save();
                $this->event($current, $actor, 'controller_acknowledged', $key);
                $this->audit->record('incident.controller_acknowledged', $actor, $current, metadata: ['escalation_status' => 'acknowledged']);
            });
    }

    public function recordDecision(IncidentActorEvidence $evidence, PrivacyIncident $incident, array $values, string $key): PrivacyIncident
    {
        return $this->mutate($evidence, $incident, PrivacySecurityPermissions::INCIDENTS_CONTROLLER_DECIDE, $key,
            function (User $actor, PrivacyIncident $current) use ($values, $key): void {
                $version = (int) $current->decisions()->max('version') + 1;
                $decision = $this->persist(new PrivacyIncidentDecision, [
                    'privacy_incident_id' => $current->id, 'version' => $version,
                    'breach_assessment' => $this->allowed($values['breach_assessment'], IncidentTaxonomy::BREACH_ASSESSMENTS, 'breach_assessment'),
                    'authority_notification_decision' => $this->allowed($values['authority_notification_decision'], IncidentTaxonomy::NOTIFICATION_DECISIONS, 'authority_notification_decision'),
                    'subject_notification_decision' => $this->allowed($values['subject_notification_decision'], IncidentTaxonomy::NOTIFICATION_DECISIONS, 'subject_notification_decision'),
                    'reason_code' => $this->allowed($values['reason_code'], IncidentTaxonomy::DECISION_REASONS, 'reason_code'),
                    'rationale' => isset($values['rationale']) ? $this->shortText($values['rationale'], 'rationale') : null,
                    'decided_by_user_id' => $actor->id, 'actor_security_version' => $actor->security_version, 'decided_at' => now(),
                ]);
                $current->forceFill(['lock_version' => $current->lock_version + 1])->save();
                $this->event($current, $actor, 'controller_decision_recorded', $key, ['decision_version' => $version]);
                $this->audit->record('incident.controller_decision_recorded', $actor, $current, metadata: [
                    'breach_assessment' => $decision->breach_assessment, 'authority_decision' => $decision->authority_notification_decision,
                    'subject_decision' => $decision->subject_notification_decision, 'decision_version' => $version, 'reason_code' => $decision->reason_code,
                ]);
            });
    }

    public function issueHold(IncidentActorEvidence $evidence, PrivacyIncident $incident, array $attributes, string $key): PrivacyIncident
    {
        $this->uuid($key);

        return DB::transaction(function () use ($evidence, $incident, $attributes, $key): PrivacyIncident {
            $this->holds->lockSubject((int) $attributes['subject_user_id']);
            $actor = $this->actor($evidence, PrivacySecurityPermissions::INCIDENTS_MANAGE);
            if (! $actor->hasDirectPermission(PrivacySecurityPermissions::LEGAL_HOLDS_MANAGE)) {
                throw ValidationException::withMessages(['authorization' => 'Direct legal-hold authority is also required.']);
            }
            $current = PrivacyIncident::query()->lockForUpdate()->findOrFail($incident->id);
            $this->barrier->reached(IncidentMutationBarrier::AFTER_INCIDENT_LOCK);
            if ($current->events()->where('idempotency_key', $key)->exists()) {
                return $current->fresh();
            }
            $hold = $this->holds->issue($actor, $attributes);
            $this->persist(new PrivacyIncidentHold, ['privacy_incident_id' => $current->id, 'legal_hold_id' => $hold->id, 'recorded_by_user_id' => $actor->id, 'recorded_at' => now()]);
            $this->event($current, $actor, 'evidence_preserved', $key, ['legal_hold_uuid' => $hold->uuid]);
            $meta = ['hold_scope' => $hold->scope_type, 'hold_code' => $hold->hold_code];
            if ($hold->data_category) {
                $meta['data_category'] = $hold->data_category;
            }
            $this->audit->record('incident.hold_issued', $actor, $current, $hold->subject_user_id, metadata: $meta);

            return $current->fresh();
        });
    }

    public function close(IncidentActorEvidence $evidence, PrivacyIncident $incident, string $key): PrivacyIncident
    {
        return $this->mutate($evidence, $incident, PrivacySecurityPermissions::INCIDENTS_CLOSE, $key,
            function (User $actor, PrivacyIncident $current) use ($key): void {
                if ($current->status !== 'resolved' || ! $current->owner_user_id || ! $current->latestDecision()) {
                    throw ValidationException::withMessages(['status' => 'Closure requires a resolved incident, an owner, and explicit controller decision evidence.']);
                }
                $this->authorizedOwner((int) $current->owner_user_id);
                if ($current->escalation_status === 'escalated') {
                    throw ValidationException::withMessages(['escalation' => 'An escalated incident must be explicitly acknowledged before closure.']);
                }
                $current->forceFill(['status' => 'closed', 'closed_at' => now(), 'lock_version' => $current->lock_version + 1])->save();
                $this->event($current, $actor, 'incident_closed', $key);
                $this->audit->record('incident.closed', $actor, $current, metadata: ['incident_status' => 'closed']);
            });
    }

    public function reopen(IncidentActorEvidence $evidence, PrivacyIncident $incident, string $reason, string $key): PrivacyIncident
    {
        return $this->mutate($evidence, $incident, PrivacySecurityPermissions::INCIDENTS_REOPEN, $key,
            function (User $actor, PrivacyIncident $current) use ($reason, $key): void {
                if ($current->status !== 'closed') {
                    throw ValidationException::withMessages(['status' => 'Only a closed incident can be reopened.']);
                }
                $reason = $this->allowed($reason, IncidentTaxonomy::REOPEN_REASONS, 'reason_code');
                $current->forceFill(['status' => 'investigating', 'reopened_at' => now(), 'lock_version' => $current->lock_version + 1])->save();
                $this->event($current, $actor, 'incident_reopened', $key, ['reason_code' => $reason]);
                $this->audit->record('incident.reopened', $actor, $current, reasonCode: $reason, metadata: ['incident_status' => 'investigating', 'reason_code' => $reason]);
            });
    }

    /** @return array{report_count:int, repair_count:int} */
    public function reconcile(User $initiator, bool $execute): array
    {
        $actor = User::query()->find($initiator->id);
        if (! $actor || ! $actor->hasRole('admin') || ! $actor->hasDirectPermission(PrivacySecurityPermissions::INCIDENTS_RECONCILE)) {
            throw ValidationException::withMessages(['authorization' => 'Direct incident reconciliation authority is required.']);
        }
        $ids = PrivacyIncident::query()->orderBy('id')->pluck('id');
        $reports = 0;
        $repairs = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($actor, $execute, $id, &$reports, &$repairs): void {
                $lockedActor = User::withTrashed()->lockForUpdate()->find($actor->id);
                if (! $lockedActor || $lockedActor->trashed() || ! $lockedActor->hasDirectPermission(PrivacySecurityPermissions::INCIDENTS_RECONCILE)) {
                    throw ValidationException::withMessages(['authorization' => 'Incident reconciliation authority became stale.']);
                }
                $incident = PrivacyIncident::query()->lockForUpdate()->findOrFail($id);
                $ownerInvalid = ! $incident->owner_user_id || ! User::query()->whereKey($incident->owner_user_id)->get()->contains(fn (User $user) => $user->hasRole('admin') && $user->hasDirectPermission(PrivacySecurityPermissions::INCIDENTS_MANAGE));
                $timestampInvalid = ($incident->status === 'closed' && ! $incident->closed_at)
                    || (in_array($incident->status, ['resolved', 'closed'], true) && ! $incident->resolved_at)
                    || ($incident->escalation_status === 'acknowledged' && (! $incident->acknowledged_at || ! $incident->acknowledged_by_user_id));
                if (! $ownerInvalid && ! $timestampInvalid) {
                    return;
                }
                $reports++;
                if (! $execute || $incident->review_required_at) {
                    return;
                }
                $incident->forceFill(['review_required_at' => now(), 'lock_version' => $incident->lock_version + 1])->save();
                $this->event($incident, $lockedActor, 'review_required', (string) Str::uuid(), ['reason_code' => $ownerInvalid ? 'owner_authority_stale' : 'timestamp_inconsistent']);
                $this->audit->record('incident.reconciliation_review', $lockedActor, $incident, metadata: ['incident_status' => $incident->status, 'repair_count' => 1, 'report_count' => 1]);
                $repairs++;
            });
        }

        return ['report_count' => $reports, 'repair_count' => $repairs];
    }

    /** @param callable(User, PrivacyIncident):void $operation */
    private function mutate(IncidentActorEvidence $evidence, PrivacyIncident $incident, string $permission, string $key, callable $operation): PrivacyIncident
    {
        $this->uuid($key);

        return DB::transaction(function () use ($evidence, $incident, $permission, $key, $operation): PrivacyIncident {
            $actor = $this->actor($evidence, $permission);
            $current = PrivacyIncident::query()->lockForUpdate()->findOrFail($incident->id);
            $this->barrier->reached(IncidentMutationBarrier::AFTER_INCIDENT_LOCK);
            if ($current->events()->where('idempotency_key', $key)->exists()) {
                return $current->fresh();
            }
            $operation($actor, $current);

            return $current->fresh();
        });
    }

    private function actor(IncidentActorEvidence $evidence, string $permission): User
    {
        $actor = User::withTrashed()->lockForUpdate()->find($evidence->userId);
        if (! $actor || $actor->trashed() || (int) $actor->security_version !== $evidence->securityVersion
            || ! $actor->hasRole('admin') || ! $actor->hasDirectPermission($permission)) {
            throw ValidationException::withMessages(['authorization' => 'Current direct incident authority is required.']);
        }

        return $actor;
    }

    private function authorizedOwner(int $id): User
    {
        $owner = User::withTrashed()->lockForUpdate()->find($id);
        if (! $owner || $owner->trashed() || ! $owner->hasRole('admin') || ! $owner->hasDirectPermission(PrivacySecurityPermissions::INCIDENTS_MANAGE)) {
            throw ValidationException::withMessages(['owner_user_id' => 'The owner must be an active directly authorized incident administrator.']);
        }

        return $owner;
    }

    private function event(PrivacyIncident $incident, User $actor, string $type, string $key, array $metadata = []): PrivacyIncidentEvent
    {
        if (! in_array($type, IncidentTaxonomy::EVENT_TYPES, true)) {
            throw new \LogicException('Unsupported incident event.');
        }

        return $this->persist(new PrivacyIncidentEvent, [
            'privacy_incident_id' => $incident->id, 'sequence' => (int) $incident->events()->max('sequence') + 1,
            'event_type' => $type, 'actor_user_id' => $actor->id, 'actor_security_version' => $actor->security_version,
            'idempotency_key' => $key, 'occurred_at' => now(), 'safe_metadata' => $metadata ?: null,
        ]);
    }

    private function appendScope(PrivacyIncident $incident, User $actor, array $scope): PrivacyIncidentScopeVersion
    {
        return $this->persist(new PrivacyIncidentScopeVersion, [
            'privacy_incident_id' => $incident->id, 'version' => (int) $incident->scopeVersions()->max('version') + 1,
            ...$scope, 'recorded_by_user_id' => $actor->id, 'actor_security_version' => $actor->security_version,
            'recorded_at' => now(),
        ]);
    }

    private function scopeValues(array $values): array
    {
        $categories = array_values(array_unique($values['data_categories'] ?? []));
        $systems = array_values(array_unique($values['system_codes'] ?? []));
        if (array_diff($categories, RetentionDataCategories::all()) || array_diff($systems, IncidentTaxonomy::SYSTEMS)) {
            throw ValidationException::withMessages(['scope' => 'Incident scope contains an unsupported controlled code.']);
        }
        $count = $values['potentially_affected_subject_count'] ?? null;
        if ($count !== null && (! is_int($count) || $count < 0 || $count > 4_294_967_295)) {
            throw ValidationException::withMessages(['potentially_affected_subject_count' => 'Potentially affected count must be a non-negative aggregate.']);
        }

        return ['data_categories' => $categories, 'system_codes' => $systems, 'potentially_affected_subject_count' => $count];
    }

    private function creationValues(array $attributes, User $actor): array
    {
        return [
            'technical_severity' => $this->allowed($attributes['technical_severity'] ?? null, IncidentTaxonomy::SEVERITIES, 'technical_severity'),
            'classification_code' => $this->allowed($attributes['classification_code'] ?? null, IncidentTaxonomy::CLASSIFICATIONS, 'classification_code'),
            'technical_summary' => $this->shortText($attributes['technical_summary'] ?? null, 'technical_summary'),
            'detected_at' => $attributes['detected_at'] ?? null,
            'owner_user_id' => isset($attributes['owner_user_id']) ? (int) $attributes['owner_user_id'] : $actor->id,
            'scope' => $this->scopeValues($attributes),
        ];
    }

    private function existingCreate(PrivacyIncident $incident, User $actor, array $values): PrivacyIncident
    {
        if ((int) $incident->created_by_user_id !== $actor->id) {
            throw ValidationException::withMessages(['idempotency_key' => 'That creation key is unavailable.']);
        }

        $scope = $incident->scopeVersions()->reorder()->orderBy('version')->first();
        $samePayload = $incident->technical_severity === $values['technical_severity']
            && $incident->classification_code === $values['classification_code']
            && hash_equals($incident->technical_summary, $values['technical_summary'])
            && $incident->detected_at?->equalTo($values['detected_at'])
            && (int) $incident->owner_user_id === $values['owner_user_id']
            && $scope !== null
            && $scope->data_categories === $values['scope']['data_categories']
            && $scope->system_codes === $values['scope']['system_codes']
            && $scope->potentially_affected_subject_count === $values['scope']['potentially_affected_subject_count'];

        if (! $samePayload) {
            throw ValidationException::withMessages(['idempotency_key' => 'That creation key was already used for different incident data.']);
        }

        return $incident->fresh();
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? ''), ['23000', '23505'], true)
            && in_array((int) ($exception->errorInfo[1] ?? 0), [19, 1062, 1555, 2067], true);
    }

    private function allowed(mixed $value, array $allowed, string $field): string
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw ValidationException::withMessages([$field => 'An approved controlled value is required.']);
        }

        return $value;
    }

    private function shortText(mixed $value, string $field): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $value)) ?? '');
        if ($text === '' || mb_strlen($text) > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text)) {
            throw ValidationException::withMessages([$field => 'A safe summary of 1–500 characters is required.']);
        }

        return $text;
    }

    private function uuid(string $value): void
    {
        if (! Str::isUuid($value)) {
            throw ValidationException::withMessages(['idempotency_key' => 'A UUID idempotency key is required.']);
        }
    }

    /** @template T of \Illuminate\Database\Eloquent\Model @param T $model @return T */
    private function persist($model, array $attributes)
    {
        $model->forceFill($attributes)->save();

        return $model;
    }
}
