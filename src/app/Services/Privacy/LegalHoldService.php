<?php

namespace App\Services\Privacy;

use App\Models\JobSeekerDocument;
use App\Models\LegalHold;
use App\Models\User;
use App\Services\Security\PrivacyAuditService;
use App\Support\PrivacySecurityPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LegalHoldService
{
    public function __construct(private readonly PrivacyAuditService $audit) {}

    public function issue(User $actor, array $attributes): LegalHold
    {
        $controller = $this->actor($actor);

        return DB::transaction(function () use ($controller, $attributes): LegalHold {
            $this->lockSubject((int) $attributes['subject_user_id']);
            $this->assertScope($attributes, currentRead: true);
            $controller = $this->lockActor($controller->id);
            $hold = LegalHold::query()->create([...$attributes, 'status' => LegalHold::STATUS_ACTIVE, 'issued_by_user_id' => $controller->id]);
            $metadata = ['hold_scope' => $hold->scope_type, 'hold_code' => $hold->hold_code];
            if ($hold->data_category) {
                $metadata['data_category'] = $hold->data_category;
            }
            $this->audit->record('legal_hold_created', $controller, $hold, $hold->subject_user_id, metadata: $metadata);

            return $hold;
        });
    }

    public function release(User $actor, LegalHold $hold): LegalHold
    {
        $controller = $this->actor($actor);

        return DB::transaction(function () use ($controller, $hold): LegalHold {
            $this->lockSubject($hold->subject_user_id);
            $controller = $this->lockActor($controller->id);
            $current = LegalHold::query()->lockForUpdate()->findOrFail($hold->id);
            if ($current->status !== LegalHold::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['hold' => 'Only an active hold can be released.']);
            }
            $current->forceFill(['status' => LegalHold::STATUS_RELEASED, 'released_at' => now(), 'released_by_user_id' => $controller->id])->save();
            $metadata = ['hold_scope' => $current->scope_type, 'hold_code' => $current->hold_code];
            if ($current->data_category) {
                $metadata['data_category'] = $current->data_category;
            }
            $this->audit->record('legal_hold_released', $controller, $current, $current->subject_user_id, metadata: $metadata, correlationId: $current->correlation_id);

            return $current->fresh();
        });
    }

    public function activeApplies(int $subjectUserId, string $category, int $resourceId): bool
    {
        return $this->applicableQuery($subjectUserId, $category, $resourceId)->exists();
    }

    /**
     * Must be called while the caller owns the subject fence. A locking read is
     * intentional: an earlier REPEATABLE READ snapshot must not authorize deletion.
     */
    public function activeAppliesCurrent(int $subjectUserId, string $category, int $resourceId): bool
    {
        return $this->applicableQuery($subjectUserId, $category, $resourceId)
            ->lockForUpdate()
            ->get(['id'])
            ->isNotEmpty();
    }

    private function applicableQuery(int $subjectUserId, string $category, int $resourceId): \Illuminate\Database\Eloquent\Builder
    {
        return LegalHold::query()->where('subject_user_id', $subjectUserId)->where('status', LegalHold::STATUS_ACTIVE)
            ->where('effective_at', '<=', now())->whereNull('released_at')
            ->where(function ($q) use ($category, $resourceId): void {
                $q->where('scope_type', LegalHold::SCOPE_SUBJECT)
                    ->orWhere(fn ($q) => $q->where('scope_type', LegalHold::SCOPE_CATEGORY)->where('data_category', $category))
                    ->orWhere(fn ($q) => $q->where('scope_type', LegalHold::SCOPE_RESOURCE)->where('data_category', $category)->where('resource_id', $resourceId));
            });
    }

    public function lockSubject(int $subjectUserId): void
    {
        DB::table('retention_subject_locks')->insertOrIgnore(['subject_user_id' => $subjectUserId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('retention_subject_locks')->where('subject_user_id', $subjectUserId)->lockForUpdate()->first();
    }

    private function assertScope(array $values, bool $currentRead = false): void
    {
        $scope = $values['scope_type'];
        $category = $values['data_category'] ?? null;
        $resource = $values['resource_id'] ?? null;
        if (! in_array($scope, LegalHold::SCOPES, true)
            || ($scope === LegalHold::SCOPE_SUBJECT && ($category !== null || $resource !== null))
            || ($scope === LegalHold::SCOPE_CATEGORY && (! in_array($category, RetentionDataCategories::all(), true) || $resource !== null))
            || ($scope === LegalHold::SCOPE_RESOURCE && (! in_array($category, RetentionDataCategories::all(), true) || ! is_numeric($resource)))) {
            throw ValidationException::withMessages(['scope_type' => 'The hold scope is ambiguous or unsupported.']);
        }

        if ($scope === LegalHold::SCOPE_RESOURCE) {
            if ($category !== RetentionDataCategories::APPLICANT_DOCUMENT) {
                throw ValidationException::withMessages(['data_category' => 'Resource-level applicability cannot be established for this category. Use a broader hold.']);
            }
            $query = JobSeekerDocument::query()->with('jobSeeker');
            if ($currentRead) {
                $query->lockForUpdate();
            }
            $document = $query->find($resource);
            if (! $document || (int) $document->jobSeeker?->user_id !== (int) $values['subject_user_id']) {
                throw ValidationException::withMessages(['resource_id' => 'The resource-to-subject binding cannot be established.']);
            }
        }
    }

    private function actor(User $actor): User
    {
        $current = User::query()->find($actor->id);
        if (! $current || ! $current->hasRole('admin') || ! $current->hasDirectPermission(PrivacySecurityPermissions::LEGAL_HOLDS_MANAGE)) {
            throw ValidationException::withMessages(['authorization' => 'A current specifically authorized hold administrator is required.']);
        }

        return $current;
    }

    private function lockActor(int $userId): User
    {
        $current = User::withTrashed()->lockForUpdate()->find($userId);
        if (! $current || $current->trashed() || ! $current->hasRole('admin') || ! $current->hasDirectPermission(PrivacySecurityPermissions::LEGAL_HOLDS_MANAGE)) {
            throw ValidationException::withMessages(['authorization' => 'A current specifically authorized hold administrator is required.']);
        }

        return $current;
    }
}
