<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentAssistanceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PaymentAssistanceController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $q      = trim((string) $request->query('q', ''));

        $requests = PaymentAssistanceRequest::query()
            ->with(['user', 'plan'])
            ->when($q !== '', fn ($query) => $query
                ->where('full_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
            )
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.payment-assistance.index', compact('requests', 'status', 'q'));
    }

    public function updateStatus(Request $request, PaymentAssistanceRequest $assistanceRequest): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'in:' . implode(',', PaymentAssistanceRequest::STATUSES)],
        ]);

        $assistanceRequest->update(['status' => $request->input('status')]);

        return back()->with('success', 'Status updated.');
    }
}
