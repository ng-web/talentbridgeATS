<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Notifications\ApplicationStatusChangedNotification;
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
            ->whereNotIn('status', [Application::STATUS_WITHDRAWN])
            ->with(['job', 'jobSeeker.user', 'jobSeeker.documents'])
            ->latest()
            ->get();

        return view('employer.applicants.index', compact('applications'));
    }

    public function updateStatus(Request $request, Application $application): RedirectResponse
    {
        $employer = Auth::user()->employer;

        abort_unless($employer, 403);
        abort_unless(
            $application->job?->employer_id === $employer->id,
            403
        );

        $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', Application::EMPLOYER_STATUSES)],
            'notes'  => ['nullable', 'string', 'max:2000'],
        ]);

        $application->update([
            'status' => $request->string('status')->toString(),
            'notes'  => $request->string('notes')->toString() ?: null,
        ]);

        $jobSeekerUser = $application->jobSeeker?->user;

        if ($jobSeekerUser) {
            $jobSeekerUser->notify(new ApplicationStatusChangedNotification($application, $application->status));
        }

        return back()->with('status', 'Applicant status updated.');
    }
}
