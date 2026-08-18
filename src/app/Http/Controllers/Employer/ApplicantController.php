<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Job;
use App\Models\JobSeekerDocument;
use App\Notifications\ApplicationStatusChangedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class ApplicantController extends Controller
{
    public function index(Request $request): View|Response
    {
        $employer = Auth::user()->employer;

        abort_unless($employer, 404);

        $q = trim((string) $request->query('q', ''));
        $jobId = (int) $request->query('job_id', 0);
        $status = trim((string) $request->query('status', ''));

        $applications = Application::query()
            ->whereHas('job', fn ($q) => $q->where('employer_id', $employer->id))
            ->whereNotIn('status', [Application::STATUS_WITHDRAWN])
            ->with([
                'job',
                'jobSeeker.user',
                'jobSeeker.documents' => fn ($query) => $query->where(
                    'document_type',
                    JobSeekerDocument::TYPE_PROFILE_PHOTO,
                ),
            ])
            ->when($q !== '', fn ($query) => $query->whereHas('jobSeeker.user', fn ($u) => $u->where('name', 'like', "%{$q}%")
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

        $data = compact('applications', 'jobs', 'q', 'jobId', 'status');

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->view('employer.applicants.partials.list', $data);
        }

        return view('employer.applicants.index', $data);
    }

    public function show(Application $application): View
    {
        $this->authorizeActiveOwnedApplication($application);

        $application->load([
            'job',
            'jobSeeker.user',
            'jobSeeker.documents' => fn ($query) => $query->whereIn('document_type', [
                JobSeekerDocument::TYPE_PROFILE_PHOTO,
                JobSeekerDocument::TYPE_CERTIFICATE,
            ]),
        ]);

        $docsByType = $application->jobSeeker->documents->groupBy('document_type');

        return view('employer.applicants.show', compact('application', 'docsByType'));
    }

    public function updateStatus(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeActiveOwnedApplication($application);

        $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', Application::EMPLOYER_STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->update([
            'status' => $request->string('status')->toString(),
            'notes' => $request->string('notes')->toString() ?: null,
        ]);

        $jobSeekerUser = $application->jobSeeker?->user;

        if ($jobSeekerUser) {
            $jobSeekerUser->notify(new ApplicationStatusChangedNotification($application, $application->status));
        }

        return back()->with('status', 'Applicant status updated.');
    }

    private function authorizeActiveOwnedApplication(Application $application): void
    {
        $employer = Auth::user()->employer;

        abort_unless(
            $employer
            && $application->status !== Application::STATUS_WITHDRAWN
            && $application->job?->employer_id === $employer->id,
            403,
        );
    }
}
