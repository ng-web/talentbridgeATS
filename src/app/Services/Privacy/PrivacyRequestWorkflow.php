<?php

namespace App\Services\Privacy;

use App\Models\PrivacyRequest;
use App\Models\PrivacyRequestEvent;
use App\Models\User;
use App\Services\Security\PrivacyAuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PrivacyRequestWorkflow
{
    public const ASSIGNABLE_STATES = [
        PrivacyRequest::STATE_SUBMITTED,
        PrivacyRequest::STATE_UNDER_REVIEW,
        PrivacyRequest::STATE_IDENTITY_REQUIRED,
        PrivacyRequest::STATE_VERIFIED,
        PrivacyRequest::STATE_DECISION_REQUIRED,
    ];

    private const TRANSITIONS = [
        PrivacyRequest::STATE_SUBMITTED => [PrivacyRequest::STATE_UNDER_REVIEW],
        PrivacyRequest::STATE_UNDER_REVIEW => [PrivacyRequest::STATE_IDENTITY_REQUIRED],
        PrivacyRequest::STATE_IDENTITY_REQUIRED => [PrivacyRequest::STATE_CLOSED],
        PrivacyRequest::STATE_VERIFIED => [PrivacyRequest::STATE_DECISION_REQUIRED],
        PrivacyRequest::STATE_DECISION_REQUIRED => [PrivacyRequest::STATE_APPROVED, PrivacyRequest::STATE_PARTIALLY_APPROVED, PrivacyRequest::STATE_REFUSED],
        PrivacyRequest::STATE_APPROVED => [PrivacyRequest::STATE_FULFILLED],
        PrivacyRequest::STATE_PARTIALLY_APPROVED => [PrivacyRequest::STATE_FULFILLED],
        PrivacyRequest::STATE_REFUSED => [PrivacyRequest::STATE_CLOSED],
        PrivacyRequest::STATE_FULFILLED => [PrivacyRequest::STATE_CLOSED],
        PrivacyRequest::STATE_CLOSED => [],
    ];

    public function __construct(private readonly PrivacyAuditService $audit) {}

    public function submit(User $subject, string $type): PrivacyRequest
    {
        return DB::transaction(function () use ($subject, $type): PrivacyRequest {
            $request = PrivacyRequest::query()->create([
                'subject_user_id' => $subject->id,
                'request_type' => $type,
                'state' => PrivacyRequest::STATE_SUBMITTED,
                'identity_verification_state' => PrivacyRequest::IDENTITY_UNVERIFIED,
                'submitted_at' => now(),
            ]);

            $this->appendEvent($request, $subject, 'submitted', null, PrivacyRequest::STATE_SUBMITTED);
            $this->audit->record(
                event: 'privacy_request_submitted',
                actor: $subject,
                resource: $request,
                subjectUserId: $subject->id,
                metadata: ['request_type' => $type, 'request_state' => $request->state],
            );

            return $request;
        });
    }

    public function assign(User $actor, PrivacyRequest $request, User $assignee): PrivacyRequest
    {
        $this->assertControllerActor($actor);

        return DB::transaction(function () use ($actor, $request, $assignee): PrivacyRequest {
            $locked = PrivacyRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (! in_array($locked->state, self::ASSIGNABLE_STATES, true)) {
                throw ValidationException::withMessages(['assigned_admin_id' => 'This privacy request can no longer be assigned.']);
            }
            $locked->forceFill(['assigned_admin_id' => $assignee->id, 'assigned_at' => now()])->save();
            $this->appendEvent($locked, $actor, 'assigned', $locked->state, $locked->state, 'controller_assignment');
            $this->audit->record(
                event: 'privacy_request_assigned',
                actor: $actor,
                resource: $locked,
                subjectUserId: $locked->subject_user_id,
                metadata: ['request_type' => $locked->request_type, 'request_state' => $locked->state],
            );

            return $locked->fresh();
        });
    }

    public function transition(User $actor, PrivacyRequest $request, string $toState, ?string $reasonCode = null): PrivacyRequest
    {
        $this->assertControllerActor($actor);

        return DB::transaction(function () use ($actor, $request, $toState, $reasonCode): PrivacyRequest {
            $locked = PrivacyRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertTransitionAllowed($locked->state, $toState);
            $from = $locked->state;
            $updates = ['state' => $toState];

            if (in_array($toState, [PrivacyRequest::STATE_VERIFIED], true)) {
                throw ValidationException::withMessages(['state' => 'Only the identity-verification operation may verify a request.']);
            }

            if (in_array($toState, [PrivacyRequest::STATE_DECISION_REQUIRED, PrivacyRequest::STATE_APPROVED, PrivacyRequest::STATE_PARTIALLY_APPROVED, PrivacyRequest::STATE_REFUSED], true)
                && $locked->identity_verification_state !== PrivacyRequest::IDENTITY_VERIFIED) {
                throw ValidationException::withMessages(['state' => 'A controller-completed identity verification is required before a decision.']);
            }

            if ($toState === PrivacyRequest::STATE_FULFILLED
                && ! in_array($locked->controller_decision_code, ['controller_approved', 'controller_partially_approved'], true)) {
                throw ValidationException::withMessages(['state' => 'Only an approved or partially approved request may be fulfilled.']);
            }

            if ($toState === PrivacyRequest::STATE_IDENTITY_REQUIRED) {
                $updates['identity_verification_state'] = PrivacyRequest::IDENTITY_REQUIRED;
            }

            if (in_array($toState, [PrivacyRequest::STATE_APPROVED, PrivacyRequest::STATE_PARTIALLY_APPROVED, PrivacyRequest::STATE_REFUSED], true)) {
                $updates['decision_at'] = now();
                $updates['controller_decision_code'] = $reasonCode;
            }

            if ($toState === PrivacyRequest::STATE_FULFILLED) {
                $updates['completed_at'] = now();
            }

            if ($toState === PrivacyRequest::STATE_CLOSED) {
                $updates['closure_reason_code'] = $reasonCode;
            }

            $locked->forceFill($updates)->save();
            $this->appendEvent($locked, $actor, 'state_transitioned', $from, $toState, $reasonCode);

            $event = match (true) {
                in_array($toState, [PrivacyRequest::STATE_APPROVED, PrivacyRequest::STATE_PARTIALLY_APPROVED, PrivacyRequest::STATE_REFUSED], true) => 'privacy_request_decision_recorded',
                in_array($toState, [PrivacyRequest::STATE_FULFILLED, PrivacyRequest::STATE_CLOSED], true) => 'privacy_request_completed',
                default => 'privacy_request_state_changed',
            };

            $this->audit->record(
                event: $event,
                actor: $actor,
                resource: $locked,
                subjectUserId: $locked->subject_user_id,
                reasonCode: $reasonCode,
                metadata: ['request_type' => $locked->request_type, 'request_state' => $toState],
            );

            return $locked->fresh();
        });
    }

    public function verifyIdentity(User $actor, PrivacyRequest $request, string $method): PrivacyRequest
    {
        $this->assertControllerActor($actor);

        return DB::transaction(function () use ($actor, $request, $method): PrivacyRequest {
            $locked = PrivacyRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! in_array($locked->state, [PrivacyRequest::STATE_UNDER_REVIEW, PrivacyRequest::STATE_IDENTITY_REQUIRED], true)) {
                throw ValidationException::withMessages(['identity_verification_state' => 'Identity verification cannot be recorded in the current state.']);
            }

            $from = $locked->state;
            $locked->forceFill([
                'state' => PrivacyRequest::STATE_VERIFIED,
                'identity_verification_state' => PrivacyRequest::IDENTITY_VERIFIED,
                'identity_verification_method' => $method,
                'identity_verified_by' => $actor->id,
                'verified_at' => now(),
            ])->save();

            $this->appendEvent($locked, $actor, 'identity_verified', $from, PrivacyRequest::STATE_VERIFIED, 'controller_process_completed', [
                'method_code' => $method,
            ]);
            $this->audit->record(
                event: 'privacy_request_identity_verified',
                actor: $actor,
                resource: $locked,
                subjectUserId: $locked->subject_user_id,
                metadata: ['request_type' => $locked->request_type, 'request_state' => $locked->state],
            );

            return $locked->fresh();
        });
    }

    private function assertTransitionAllowed(string $from, string $to): void
    {
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages(['state' => 'That privacy request state transition is not allowed.']);
        }
    }

    private function assertControllerActor(User $actor): void
    {
        if (! $actor->hasRole('admin')) {
            throw ValidationException::withMessages(['state' => 'Only an authorized controller administrator may change internal request state.']);
        }
    }

    /** @param array<string, scalar|null> $metadata */
    private function appendEvent(
        PrivacyRequest $request,
        ?User $actor,
        string $event,
        ?string $from,
        ?string $to,
        ?string $reasonCode = null,
        array $metadata = [],
    ): void {
        PrivacyRequestEvent::query()->create([
            'privacy_request_id' => $request->id,
            'actor_user_id' => $actor?->id,
            'event' => $event,
            'from_state' => $from,
            'to_state' => $to,
            'reason_code' => $reasonCode,
            'safe_metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
