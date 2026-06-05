<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\PaymentAssistanceAdminMail;
use App\Mail\PaymentAssistanceApplicantMail;
use App\Models\PaymentAssistanceRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

final class PaymentAssistanceController extends Controller
{
    public function create(Plan $plan): View
    {
        $user      = Auth::user();
        $jobSeeker = $user?->jobSeeker;

        return view('public.payment-assistance', compact('plan', 'user', 'jobSeeker'));
    }

    public function store(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'email'     => ['required', 'email', 'max:255'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'whatsapp'  => ['nullable', 'string', 'max:30'],
            'message'   => ['nullable', 'string', 'max:2000'],
        ]);

        $assistanceRequest = PaymentAssistanceRequest::create([
            'user_id'      => Auth::id(),
            'plan_id'      => $plan->id,
            'full_name'    => $validated['full_name'],
            'email'        => $validated['email'],
            'phone'        => $validated['phone'] ?? null,
            'whatsapp'     => $validated['whatsapp'] ?? null,
            'program_name' => $plan->name,
            'amount'       => $plan->amount,
            'currency'     => $plan->currency,
            'message'      => $validated['message'] ?? null,
            'status'       => PaymentAssistanceRequest::STATUS_NEW,
        ]);

        try {
            Mail::to(config('mail.admin_address', config('mail.from.address')))
                ->send(new PaymentAssistanceAdminMail($assistanceRequest));
        } catch (\Throwable $e) {
            Log::error('PaymentAssistance: admin notification failed', ['error' => $e->getMessage()]);
        }

        try {
            Mail::to($assistanceRequest->email)
                ->send(new PaymentAssistanceApplicantMail($assistanceRequest));
        } catch (\Throwable $e) {
            Log::error('PaymentAssistance: applicant confirmation failed', ['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('payment-assistance.thankyou')
            ->with('program_name', $plan->name)
            ->with('amount', $plan->currency . ' ' . number_format((float) $plan->amount, 0));
    }

    public function thankyou(): View
    {
        return view('public.payment-assistance-thankyou');
    }
}
