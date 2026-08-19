<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminOverride;
use App\Models\AuditLog;
use App\Models\Entitlement;
use App\Models\Payment;
use App\Models\PaymentAssistanceRequest;
use App\Models\Program;
use App\Models\User;
use App\Services\Security\AccountSetupService;
use App\Services\Security\AdminSessionService;
use App\Services\Security\PrivacyAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

final class UserController extends Controller
{
    public function __construct(
        private readonly PrivacyAuditService $auditService,
        private readonly AdminSessionService $sessions,
        private readonly AccountSetupService $accountSetup,
    ) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $role = trim((string) $request->query('role', ''));
        $program = trim((string) $request->query('program', ''));
        $access = trim((string) $request->query('access', ''));
        $payment = trim((string) $request->query('payment', ''));
        $passwordChange = trim((string) $request->query('password_change', ''));

        $users = User::query()
            ->with([
                'roles',
                'employer',
                'jobSeeker.program',
                'currentEntitlement',
                'latestEntitlement',
                'latestPayment.plan',
            ])
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $subQuery) use ($q) {
                    $subQuery
                        ->where('name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%')
                        ->orWhereHas('employer', function (Builder $employerQuery) use ($q) {
                            $employerQuery->where('company_name', 'like', '%'.$q.'%');
                        });
                });
            })
            ->when($role !== '', fn (Builder $query) => $query->role($role))
            ->when($program !== '', function (Builder $query) use ($program) {
                $query->whereHas('jobSeeker', fn (Builder $jobSeekerQuery) => $jobSeekerQuery->where('program_id', $program));
            })
            ->when($access !== '', fn (Builder $query) => $query->whereAccessSummary($access))
            ->when($payment !== '', function (Builder $query) use ($payment) {
                if ($payment === 'no_payment') {
                    $query->whereDoesntHave('payments');

                    return;
                }

                if (in_array($payment, Payment::STATUSES, true)) {
                    $query->whereHas('latestPayment', fn (Builder $paymentQuery) => $paymentQuery->where('status', $payment));
                }
            })
            ->when($passwordChange !== '', function (Builder $query) use ($passwordChange) {
                if ($passwordChange === 'yes') {
                    $query->where('must_change_password', true);
                }

                if ($passwordChange === 'no') {
                    $query->where('must_change_password', false);
                }
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $data = [
            'users' => $users,
            'programs' => Program::query()->where('is_active', true)->orderBy('name')->get(),
            'filters' => compact('q', 'role', 'program', 'access', 'payment', 'passwordChange'),
        ];

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->view('admin.users.partials.list', $data);
        }

        return view('admin.users.index', $data);
    }

    public function show(User $user): View
    {
        $user->load([
            'roles',
            'employer',
            'jobSeeker.documents',
            'jobSeeker.program',
            'jobSeeker.latestApplication.job.employer',
            'entitlements' => fn ($query) => $query->latest(),
            'payments' => fn ($query) => $query->with('plan')->latest(),
        ]);

        $user->jobSeeker?->loadCount('applications');

        $activeEntitlements = $user->entitlements->filter(fn (Entitlement $entitlement) => $entitlement->isActive());
        $recentPayments = $user->payments->take(5);
        $seekerDocuments = $user->jobSeeker?->documents->keyBy('document_type');

        return view('admin.users.show', [
            'user' => $user,
            'activeEntitlements' => $activeEntitlements,
            'recentPayments' => $recentPayments,
            'seekerDocuments' => $seekerDocuments,
            'programs' => Program::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function deleted(): View
    {
        $users = User::onlyTrashed()
            ->with(['roles', 'employer', 'jobSeeker'])
            ->latest('deleted_at')
            ->paginate(20);

        return view('admin.users.deleted', compact('users'));
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($this->isFinalActiveAdmin($user)) {
            return back()->with('error', 'You cannot move the final active admin account to the recycle bin.');
        }

        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot move your own account to the recycle bin.');
        }

        DB::transaction(function () use ($user): void {
            $revoked = $this->sessions->invalidateAll($user);
            $user->delete();

            $this->audit('user_soft_deleted', $user, [
                'session_revocation_count' => $revoked,
            ], 'account_suspended');
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User moved to the recycle bin. They can be restored later.');
    }

    public function restore(int $id): RedirectResponse
    {
        $user = User::onlyTrashed()->findOrFail($id);
        DB::transaction(function () use ($user): void {
            $user->restore();
            $this->audit('user_restored', $user, [], 'account_reactivated');
        });

        return redirect()
            ->route('admin.users.deleted')
            ->with('success', 'User restored successfully.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $user = User::onlyTrashed()
            ->with(['employer.jobs', 'jobSeeker.applications'])
            ->findOrFail($id);

        if ((int) $user->id === (int) auth()->id()) {
            return back()->with('error', 'You cannot permanently delete your own account.');
        }

        $blockers = $this->forceDeleteBlockers($user);

        if (! empty($blockers)) {
            $this->audit('user_force_delete_attempted', $user, [
                'blocked_by' => $blockers,
            ]);

            return back()->with('error', 'Permanent deletion is blocked because this user has: '.implode(', ', $blockers).'.');
        }

        DB::transaction(function () use ($user): void {
            $revoked = $this->sessions->invalidateAll($user);
            $user->notifications()->delete();
            $user->syncRoles([]);
            $user->forceDelete();

            $this->auditService->record(
                event: 'user_force_deleted',
                actor: auth()->user(),
                resource: $user,
                reasonCode: 'permanent_deletion_approved',
                metadata: ['session_revocation_count' => $revoked],
            );
        });

        return redirect()
            ->route('admin.users.deleted')
            ->with('success', 'User permanently deleted.');
    }

    public function sendAccountSetupLink(Request $request, User $user): RedirectResponse
    {
        try {
            $this->accountSetup->issue($user, $request->user());

            return back()->with('success', 'A single-use, expiring account setup link was sent. Existing sessions and credentials were invalidated.');
        } catch (Throwable $e) {
            Log::error('Secure account setup email failed', [
                'target_user_id' => $user->id,
                'admin_user_id' => auth()->id(),
                'exception_class' => $e::class,
            ]);

            return back()->with('error', 'Account setup link could not be sent. No setup credentials were issued. Please verify mail delivery and try again.');
        }
    }

    public function forcePasswordChange(User $user): RedirectResponse
    {
        $user->update([
            'must_change_password' => true,
        ]);

        $revoked = $this->sessions->invalidateAll($user);
        $this->audit('password_change_required', $user, [
            'session_revocation_count' => $revoked,
        ], 'admin_required_password_change');

        return back()->with('success', 'User will be required to change password on next login.');
    }

    public function clearPasswordChange(User $user): RedirectResponse
    {
        $user->update([
            'must_change_password' => false,
        ]);

        $this->audit('password_change_requirement_cleared', $user, [], 'admin_cleared_password_change');

        return back()->with('success', 'Password change requirement cleared.');
    }

    public function grantAccess(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', Entitlement::TYPES)],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            $expiresAt = $validated['expires_at'] ?? now()->addMonth()->toDateTimeString();

            Entitlement::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => $validated['type'],
                ],
                [
                    'status' => Entitlement::STATUS_ACTIVE,
                    'starts_at' => now(),
                    'expires_at' => $expiresAt,
                    'source' => 'admin_user_detail',
                    'notes' => $validated['notes'] ?? 'Access granted from admin user detail page.',
                ]
            );

            $this->auditService->record(
                event: 'access_granted',
                actor: auth()->user(),
                resource: $user,
                subjectUserId: $user->id,
                reasonCode: 'admin_access_grant',
                metadata: [
                    'type' => $validated['type'],
                    'expires_at' => $expiresAt,
                ],
            );
        });

        return back()->with('success', 'Access granted successfully.');
    }

    public function revokeAccess(User $user, string $type): RedirectResponse
    {
        if (! in_array($type, Entitlement::TYPES, true)) {
            abort(404);
        }

        $entitlement = Entitlement::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->first();

        if (! $entitlement) {
            return back()->with('error', 'No matching entitlement was found for this user.');
        }

        DB::transaction(function () use ($entitlement, $type, $user): void {
            $entitlement->update([
                'status' => Entitlement::STATUS_REVOKED,
                'expires_at' => now(),
                'notes' => trim(($entitlement->notes ? $entitlement->notes."\n" : '').'Revoked from admin user detail page.'),
            ]);

            $this->auditService->record(
                event: 'access_revoked',
                actor: auth()->user(),
                resource: $user,
                subjectUserId: $user->id,
                reasonCode: 'admin_access_revoke',
                metadata: ['type' => $type],
            );
        });

        return back()->with('success', 'Access revoked successfully.');
    }

    public function updateProgram(Request $request, User $user): RedirectResponse
    {
        $jobSeeker = $user->jobSeeker;

        abort_unless($jobSeeker, 404);

        $validated = $request->validate([
            'program_id' => [
                'nullable',
                'integer',
                Rule::exists('programs', 'id')->where(function ($query) use ($jobSeeker) {
                    $query->where('is_active', true);

                    if ($jobSeeker->program_id) {
                        $query->orWhere('id', $jobSeeker->program_id);
                    }
                }),
            ],
        ]);

        $oldProgramId = $jobSeeker->program_id;
        $newProgramId = $validated['program_id'] ?? null;

        if ((int) $oldProgramId === (int) $newProgramId) {
            return back()->with('success', 'Applicant Program is unchanged.');
        }

        $jobSeeker->update(['program_id' => $newProgramId]);

        $action = match (true) {
            $oldProgramId === null => 'job_seeker_program_assigned',
            $newProgramId === null => 'job_seeker_program_cleared',
            default => 'job_seeker_program_changed',
        };

        $this->audit($action, $user, [
            'old_program_id' => $oldProgramId,
            'new_program_id' => $newProgramId,
        ]);

        return back()->with('success', 'Applicant Program updated successfully.');
    }

    private function isFinalActiveAdmin(User $user): bool
    {
        return $user->hasRole('admin')
            && User::role('admin')
                ->whereKeyNot($user->id)
                ->count() === 0;
    }

    private function forceDeleteBlockers(User $user): array
    {
        $blockers = [];

        if ($user->payments()->exists()) {
            $blockers[] = 'payments';
        }

        if ($user->entitlements()->exists()) {
            $blockers[] = 'entitlements';
        }

        if ($user->jobSeeker?->applications()->exists()) {
            $blockers[] = 'applications';
        }

        if (
            $user->jobSeeker?->documents()->exists()
            || $user->jobSeeker?->resume_path
            || $user->jobSeeker?->cover_letter_path
        ) {
            $blockers[] = 'applicant documents';
        }

        if (PaymentAssistanceRequest::query()->where('user_id', $user->id)->exists()) {
            $blockers[] = 'payment assistance or contact history';
        }

        if ($user->employer?->jobs()->exists()) {
            $blockers[] = 'employer jobs';
        }

        if (
            AdminOverride::query()->where('user_id', $user->id)->exists()
            || AdminOverride::query()->where('granted_by', $user->id)->exists()
        ) {
            $blockers[] = 'admin overrides';
        }

        if (
            $user->auditLogs()->exists()
            || AuditLog::query()
                ->where('entity_type', User::class)
                ->where('entity_id', $user->id)
                ->exists()
        ) {
            $blockers[] = 'audit logs';
        }

        return $blockers;
    }

    private function audit(string $action, User $user, array $meta = [], ?string $reasonCode = null): void
    {
        $this->auditService->record(
            event: $action,
            actor: auth()->user(),
            resource: $user,
            subjectUserId: $user->id,
            reasonCode: $reasonCode,
            metadata: $meta,
        );
    }
}
