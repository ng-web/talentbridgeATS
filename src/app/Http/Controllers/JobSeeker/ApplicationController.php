<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Mail\EmployerNewApplicantMail;
use App\Mail\JobSeekerApplicationSubmittedMail;
use App\Models\Application;
use App\Models\Job;
use App\Notifications\ApplicationSubmittedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

final class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        $applications = Application::query()
            ->where('job_seeker_id', $jobSeeker->id)
            ->with(['job.employer.user'])
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('job', function ($jobQuery) use ($q) {
                    $jobQuery->where('title', 'like', '%' . $q . '%');
                });
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $data = [
            'applications' => $applications,
            'filters'      => compact('q', 'status'),
        ];

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->view('jobseeker.applications.partials.list', $data);
        }

        return view('jobseeker.applications.index', $data);
    }

    public function create(Job $job): View|RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);
        abort_unless($job->is_approved && $job->status === Job::STATUS_PUBLISHED, 404);

        if ($job->application_deadline && now()->startOfDay()->isAfter($job->application_deadline)) {
            return redirect()->route('jobseeker.jobs.show', $job)
                ->with('error', 'The application deadline for this role has passed.');
        }

        $existingApplication = Application::query()
            ->where('job_id', $job->id)
            ->where('job_seeker_id', $jobSeeker->id)
            ->first();

        if ($existingApplication) {
            return redirect()->route('jobseeker.applications.index')
                ->with('error', 'You have already applied to this opportunity.');
        }

        return view('jobseeker.applications.apply', [
            'job' => $job,
            'jobSeeker' => $jobSeeker,
        ]);
    }

    public function store(Request $request, Job $job): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);
        abort_unless($job->is_approved && $job->status === Job::STATUS_PUBLISHED, 404);

        if ($job->application_deadline && now()->startOfDay()->isAfter($job->application_deadline)) {
            return redirect()->route('jobseeker.jobs.show', $job)
                ->with('error', 'The application deadline for this role has passed.');
        }

        $existingApplication = Application::query()
            ->where('job_id', $job->id)
            ->where('job_seeker_id', $jobSeeker->id)
            ->first();

        if ($existingApplication) {
            return redirect()->route('jobseeker.applications.index')
                ->with('error', 'You have already applied to this opportunity.');
        }

        $validated = $request->validate([
            'resume'       => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'cover_letter' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        if (isset($validated['resume'])) {
            $resumePath = $validated['resume']->store('applications/resumes', 'public');
        } elseif ($jobSeeker->resume_path) {
            $resumePath = $jobSeeker->resume_path;
        } else {
            return back()->withErrors(['resume' => 'Please upload a resume or add one to your profile first.']);
        }

        $coverLetterPath = $validated['cover_letter']->store('applications/cover-letters', 'public');

        $application = Application::create([
            'job_id'                       => $job->id,
            'job_seeker_id'                => $jobSeeker->id,
            'status'                       => Application::STATUS_APPLIED,
            'applied_at'                   => now(),
            'submitted_resume_path'        => $resumePath,
            'submitted_cover_letter_path'  => $coverLetterPath,
        ]);

        $this->dispatchApplicationNotifications($application);

        return redirect()
            ->route('jobseeker.applications.index')
            ->with('success', 'Application submitted successfully.');
    }

    public function withdraw(Application $application): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker && $application->job_seeker_id === $jobSeeker->id, 403);

        $withdrawableStatuses = [Application::STATUS_APPLIED, Application::STATUS_REVIEWING];

        if (!in_array($application->status, $withdrawableStatuses, true)) {
            return back()->with('error', 'This application cannot be withdrawn at its current stage.');
        }

        $application->update(['status' => Application::STATUS_WITHDRAWN]);

        return back()->with('success', 'Application withdrawn.');
    }

    private function dispatchApplicationNotifications(Application $application): void
    {
        $jobSeekerUser = $application->jobSeeker?->user;
        $employer = $application->job?->employer;
        $employerUser = $employer?->user;

        try {
            if ($employerUser) {
                $employerUser->notify(new ApplicationSubmittedNotification($application));
            }

            if ($jobSeekerUser?->email) {
                Mail::to($jobSeekerUser->email)->send(new JobSeekerApplicationSubmittedMail($application));
            }

            if ($employer?->notificationEmail()) {
                Mail::to($employer->notificationEmail())->send(new EmployerNewApplicantMail($application));
            }
        } catch (\Throwable $e) {
            Log::error('Application notification failed', [
                'application_id' => $application->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
