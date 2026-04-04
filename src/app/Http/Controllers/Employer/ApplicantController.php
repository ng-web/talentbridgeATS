<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class ApplicantController extends Controller
{
    public function index(): View
    {
        $employer = Auth::user()->employer;

        abort_unless($employer, 404);

        $applications = Application::query()
            ->whereHas('job', function ($query) use ($employer) {
                $query->where('employer_id', $employer->id);
            })
            ->with(['job', 'jobSeeker.user'])
            ->latest()
            ->get();

        return view('employer.applicants.index', compact('applications'));
    }

    public function updateStatus(Request $request, Application $application): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', Application::STATUSES)],
        ]);

        $application->update([
            'status' => $request->string('status')->toString(),
        ]);

        return back()->with('status', 'Applicant status updated successfully.');
    }
}