<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\EmployerProvisionedMail;
use App\Models\Entitlement;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

final class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $role = trim((string) $request->query('role', ''));
        $access = trim((string) $request->query('access', ''));
        $passwordChange = trim((string) $request->query('password_change', ''));

        $users = User::query()
            ->with([
                'roles',
                'employer',
                'jobSeeker',
                'entitlements' => fn ($query) => $query->latest(),
                'payments' => fn ($query) => $query->latest(),
            ])
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $subQuery) use ($q) {
                    $subQuery
                        ->where('name', 'like', '%' . $q . '%')
                        ->orWhere('email', 'like', '%' . $q . '%')
                        ->orWhereHas('employer', function (Builder $employerQuery) use ($q) {
                            $employerQuery->where('company_name', 'like', '%' . $q . '%');
                        });
                });
            })
            ->when($role !== '', fn (Builder $query) => $query->role($role))
            ->when($access !== '', function (Builder $query) use ($access) {
                if ($access === 'active') {
                    $query->whereHas('entitlements', function (Builder $entitlementQuery) {
                        $entitlementQuery
                            ->where('status', Entitlement::STATUS_ACTIVE)
                            ->where(function (Builder $dateQuery) {
                                $dateQuery->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                            });
                    });
                }

                if ($access === 'inactive') {
                    $query->whereDoesntHave('entitlements', function (Builder $entitlementQuery) {
                        $entitlementQuery
                            ->where('status', Entitlement::STATUS_ACTIVE)
                            ->where(function (Builder $dateQuery) {
                                $dateQuery->whereNull('expires_at')
                                    ->orWhere('expires_at', '>', now());
                            });
                    });
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
            'users'   => $users,
            'filters' => compact('q', 'role', 'access', 'passwordChange'),
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
            'entitlements' => fn ($query) => $query->latest(),
            'payments.plan' => fn ($query) => $query->latest(),
        ]);

        $activeEntitlements = $user->entitlements->filter(fn (Entitlement $entitlement) => $entitlement->isActive());
        $recentPayments = $user->payments->take(5);
        $seekerDocuments = $user->jobSeeker?->documents->keyBy('document_type');

        return view('admin.users.show', [
            'user' => $user,
            'activeEntitlements' => $activeEntitlements,
            'recentPayments' => $recentPayments,
            'seekerDocuments' => $seekerDocuments,
        ]);
    }

    public function issueTemporaryPassword(User $user): RedirectResponse
    {
        $temporaryPassword = Str::password(12);

        $user->update([
            'password' => $temporaryPassword,
            'must_change_password' => true,
        ]);

        Log::warning('Temporary password issued by admin', [
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'admin_user_id' => auth()->id(),
        ]);

        try {
            Mail::to($user->email)->send(
                new EmployerProvisionedMail(
                    user: $user,
                    temporaryPassword: $temporaryPassword,
                    loginUrl: route('login'),
                )
            );

            return back()->with('success', 'Temporary password issued and login details emailed successfully.');
        } catch (Throwable $e) {
            Log::error('Temporary password email failed', [
                'target_user_id' => $user->id,
                'target_email' => $user->email,
                'admin_user_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);

            return back()
                ->with('error', 'Temporary password was issued, but the email could not be sent.')
                ->with('provisioned_credentials', [
                    'email' => $user->email,
                    'temporary_password' => $temporaryPassword,
                ]);
        }
    }

    public function forcePasswordChange(User $user): RedirectResponse
    {
        $user->update([
            'must_change_password' => true,
        ]);

        Log::warning('Password change forced by admin', [
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'admin_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'User will be required to change password on next login.');
    }

    public function clearPasswordChange(User $user): RedirectResponse
    {
        $user->update([
            'must_change_password' => false,
        ]);

        Log::warning('Password change requirement cleared by admin', [
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'admin_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Password change requirement cleared.');
    }

    public function grantAccess(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:' . implode(',', Entitlement::TYPES)],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        Entitlement::updateOrCreate(
            [
                'user_id' => $user->id,
                'type' => $validated['type'],
            ],
            [
                'status' => Entitlement::STATUS_ACTIVE,
                'starts_at' => now(),
                'expires_at' => $validated['expires_at'] ?? now()->addMonth(),
                'source' => 'admin_user_detail',
                'notes' => $validated['notes'] ?? 'Access granted from admin user detail page.',
            ]
        );

        Log::warning('Access granted from user detail page', [
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'type' => $validated['type'],
            'admin_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Access granted successfully.');
    }

    public function revokeAccess(User $user, string $type): RedirectResponse
    {
        if (!in_array($type, Entitlement::TYPES, true)) {
            abort(404);
        }

        $entitlement = Entitlement::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->first();

        if (!$entitlement) {
            return back()->with('error', 'No matching entitlement was found for this user.');
        }

        $entitlement->update([
            'status' => Entitlement::STATUS_REVOKED,
            'expires_at' => now(),
            'notes' => trim(($entitlement->notes ? $entitlement->notes . "\n" : '') . 'Revoked from admin user detail page.'),
        ]);

        Log::warning('Access revoked from user detail page', [
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'type' => $type,
            'admin_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Access revoked successfully.');
    }
}