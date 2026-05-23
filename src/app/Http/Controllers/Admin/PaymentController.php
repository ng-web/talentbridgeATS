<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\ActivateEntitlementFromPayment;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly ActivateEntitlementFromPayment $activateEntitlement,
    ) {
    }

    public function index(Request $request): View|Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $gateway = trim((string) $request->query('gateway', ''));

        $payments = Payment::query()
            ->with(['user', 'plan'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($subQuery) use ($q) {
                    $subQuery
                        ->where('order_id', 'like', "%{$q}%")
                        ->orWhere('external_ref', 'like', "%{$q}%")
                        ->orWhereHas('user', function ($userQuery) use ($q) {
                            $userQuery
                                ->where('name', 'like', "%{$q}%")
                                ->orWhere('email', 'like', "%{$q}%");
                        });
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($gateway !== '', function ($query) use ($gateway) {
                $query->where('gateway', $gateway);
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $users = User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values();

        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $data = [
            'payments' => $payments,
            'users' => $users,
            'plans' => $plans,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'gateway' => $gateway,
            ],
        ];

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->view('admin.payments.partials.list', $data);
        }

        return view('admin.payments.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'plan_id' => ['required', 'exists:plans,id'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $plan = Plan::query()->findOrFail($validated['plan_id']);

        if (!$plan->is_active) {
            return back()
                ->withErrors(['plan_id' => 'The selected plan is not active.'])
                ->withInput();
        }

        $payment = Payment::create([
            'user_id' => $validated['user_id'],
            'plan_id' => $plan->id,
            'gateway' => 'manual',
            'entitlement_type' => $plan->entitlement_type,
            'order_id' => 'MANUAL-' . Str::upper(Str::random(10)),
            'external_ref' => null,
            'currency' => $plan->currency,
            'amount' => $plan->amount,
            'status' => Payment::STATUS_PAID,
            'raw_payload' => [
                'notes' => $validated['notes'] ?? null,
                'recorded_via' => 'admin_manual',
                'pricing_source' => 'plans_table',
                'plan' => [
                    'id' => $plan->id,
                    'slug' => $plan->slug,
                    'name' => $plan->name,
                ],
            ],
            'paid_at' => $validated['paid_at'] ?? null,
        ]);

        $this->activateEntitlement->handle($payment, 'manual_payment');

        return back()->with('success', 'Payment saved successfully.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        if ($payment->gateway !== 'manual') {
            return back()->with('error', 'Gateway payments cannot be deleted from admin. Remove only manual payments.');
        }

        $payment->delete();

        return back()->with('success', 'Payment removed successfully.');
    }
}