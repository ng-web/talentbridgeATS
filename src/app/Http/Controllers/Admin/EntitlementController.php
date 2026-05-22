<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Entitlement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class EntitlementController extends Controller
{
    public function index(Request $request): View|Response
    {
        $q = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $status = trim((string) $request->query('status', ''));

        $entitlements = Entitlement::query()
            ->with('user')
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('user', function ($userQuery) use ($q) {
                    $userQuery
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($type !== '', function ($query) use ($type) {
                $query->where('type', $type);
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $users = User::query()
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                $accessType = null;
                $roleLabel = 'Unknown';

                if ($user->hasRole('employer')) {
                    $accessType = Entitlement::TYPE_EMPLOYER_POSTING_ACCESS;
                    $roleLabel = 'Employer';
                } elseif ($user->hasRole('job_seeker')) {
                    $accessType = Entitlement::TYPE_JOB_SEEKER_ACCESS;
                    $roleLabel = 'Job Seeker';
                } elseif ($user->hasRole('admin')) {
                    $roleLabel = 'Admin';
                }

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_label' => $roleLabel,
                    'access_type' => $accessType,
                    'access_type_label' => $accessType ? Entitlement::typeLabelFor($accessType) : null,
                ];
            })
            ->values();

        $data = [
            'entitlements' => $entitlements,
            'users' => $users,
            'filters' => [
                'q' => $q,
                'type' => $type,
                'status' => $status,
            ],
        ];

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->view('admin.entitlements.partials.list', $data);
        }

        return view('admin.entitlements.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'type' => ['required', 'string', 'in:' . implode(',', Entitlement::TYPES)],
            'status' => ['required', 'string', 'in:' . implode(',', Entitlement::STATUSES)],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);

        $expectedType = null;

        if ($user->hasRole('employer')) {
            $expectedType = Entitlement::TYPE_EMPLOYER_POSTING_ACCESS;
        } elseif ($user->hasRole('job_seeker')) {
            $expectedType = Entitlement::TYPE_JOB_SEEKER_ACCESS;
        }

        if ($expectedType !== null && $validated['type'] !== $expectedType) {
            return back()
                ->withErrors([
                    'type' => 'Selected entitlement type does not match this user role.',
                ])
                ->withInput();
        }

        $entitlement = Entitlement::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'type' => $validated['type'],
            ],
            [
                'status' => $validated['status'],
                'starts_at' => $validated['starts_at'] ?? now()->toDateString(),
                'expires_at' => $validated['expires_at'] ?? null,
                'source' => 'admin_manual',
                'notes' => $validated['notes'] ?? null,
            ]
        );

        $entitlement->load('user');

        return back()->with(
            'success',
            'Entitlement saved for ' . ($entitlement->user?->name ?? 'user') . ' (' . Entitlement::typeLabelFor($entitlement->type) . ').'
        );
    }

    public function destroy(Entitlement $entitlement): RedirectResponse
    {
        $entitlement->delete();

        return redirect()
            ->route('admin.entitlements.index')
            ->with('success', 'Entitlement removed successfully.');
    }
}