<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Payments\ActivateEntitlementFromPayment;
use App\Http\Controllers\Controller;
use App\Mail\EmployerProvisionedMail;
use App\Models\Employer;
use App\Models\Entitlement;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

final class EmployerProvisioningController extends Controller
{
    public function __construct(
        private readonly ActivateEntitlementFromPayment $activateEntitlement,
    ) {
    }

    public function create(): View
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->where('entitlement_type', Entitlement::TYPE_EMPLOYER_POSTING_ACCESS)
            ->orderBy('name')
            ->get();

        return view('admin.employers.create', [
            'plans' => $plans,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'notification_email' => ['nullable', 'email', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'company_description' => ['nullable', 'string'],
            'grant_access_now' => ['nullable', 'boolean'],
            'plan_id' => ['nullable', 'exists:plans,id'],
        ]);

        if (!empty($validated['website']) && !str_starts_with($validated['website'], 'http://') && !str_starts_with($validated['website'], 'https://')) {
            $validated['website'] = 'https://' . ltrim($validated['website'], '/');
        }

        $temporaryPassword = Str::password(12);
        $user = null;

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $temporaryPassword,
                'must_change_password' => true,
            ]);

            $user->assignRole('employer');

            Employer::create([
                'user_id' => $user->id,
                'company_name' => $validated['company_name'],
                'company_description' => $validated['company_description'] ?? null,
                'industry' => $validated['industry'] ?? null,
                'website' => $validated['website'] ?? null,
                'contact_person' => $validated['contact_person'] ?? $validated['name'],
                'notification_email' => $validated['notification_email'] ?? null,
                'billing_status' => 'pending',
            ]);

            if (($validated['grant_access_now'] ?? false) && !empty($validated['plan_id'])) {
                $plan = Plan::query()->findOrFail($validated['plan_id']);

                $payment = Payment::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'gateway' => 'manual',
                    'entitlement_type' => $plan->entitlement_type,
                    'order_id' => 'MANUAL-' . Str::upper(Str::random(10)),
                    'external_ref' => null,
                    'currency' => $plan->currency,
                    'amount' => $plan->amount,
                    'status' => Payment::STATUS_PAID,
                    'raw_payload' => [
                        'recorded_via' => 'admin_employer_provisioning',
                        'notes' => 'Access granted during employer/sponsor provisioning.',
                        'pricing_source' => 'plans_table',
                        'plan' => [
                            'id' => $plan->id,
                            'slug' => $plan->slug,
                            'name' => $plan->name,
                        ],
                    ],
                    'paid_at' => now(),
                ]);

                $this->activateEntitlement->handle($payment, 'admin_provisioning');
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Employer provisioning failed during database transaction', [
                'email' => $validated['email'] ?? null,
                'message' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Unable to create employer/sponsor account right now.');
        }

        try {
            Mail::to($user->email)->send(
                new EmployerProvisionedMail(
                    user: $user,
                    temporaryPassword: $temporaryPassword,
                    loginUrl: route('login'),
                )
            );

            return redirect()
                ->route('admin.entitlements.index')
                ->with('success', 'Employer/sponsor account created and login details emailed successfully.');
        } catch (Throwable $e) {
            Log::error('Employer provisioning email failed after account creation', [
                'user_id' => $user->id,
                'email' => $user->email,
                'message' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.entitlements.index')
                ->with('error', 'Employer/sponsor account was created, but the login email could not be sent. You may need to reset the password or resend credentials manually.');
        }
    }
}
