<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Job;
use App\Models\JobSeekerDocument;
use App\Notifications\ApplicationStatusChangedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class ApplicantController extends Controller
{
    public function index(Request $request): View
    {
        $employer = Auth::user()->employer;

        abort_unless($employer, 404);

        $q      = trim((string) $request->query('q', ''));
        $jobId  = (int) $request->query('job_id', 0);
        $status = trim((string) $request->query('status', ''));

        $applications = Application::query()
            ->whereHas('job', fn ($q) => $q->where('employer_id', $employer->id))
            ->whereNotIn('status', [Application::STATUS_WITHDRAWN])
            ->with(['job', 'jobSeeker.user', 'jobSeeker.documents'])
            ->when($q !== '', fn ($query) => $query->whereHas('jobSeeker.user', fn ($u) =>
                $u->where('name', 'like', "%{$q}%")
            ))
            ->when($jobId > 0, fn ($query) => $query->where('job_id', $jobId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $jobs = Job::query()
            ->where('employer_id', $employer->id)
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('employer.applicants.index', compact('applications', 'jobs', 'q', 'jobId', 'status'));
    }

    public function show(Application $application): View
    {
        $employer = Auth::user()->employer;

        abort_unless($employer && $application->job?->employer_id === $employer->id, 403);

        $application->load(['job', 'jobSeeker.user', 'jobSeeker.documents']);

        $docsByType    = $application->jobSeeker->documents->groupBy('document_type');
        $categories    = JobSeekerDocument::CATEGORIES;

        return view('employer.applicants.show', compact('application', 'docsByType', 'categories'));
    }

    public function updateStatus(Request $request, Application $application): RedirectResponse
    {
        $employer = Auth::user()->employer;

        abort_unless($employer, 403);
        abort_unless($application->job?->employer_id === $employer->id, 403);

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
