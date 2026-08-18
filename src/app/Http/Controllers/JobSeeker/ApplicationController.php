<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Mail\AdminNewApplicationMail;
use App\Mail\EmployerNewApplicantMail;
use App\Mail\JobSeekerApplicationSubmittedMail;
use App\Models\Application;
use App\Models\Job;
use App\Notifications\ApplicationSubmittedNotification;
use App\Services\Documents\ApplicantDocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

final class ApplicationController extends Controller
{
    public function __construct(private readonly ApplicantDocumentStorage $storage) {}

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
                    $jobQuery->where('title', 'like', '%'.$q.'%');
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
            'filters' => compact('q', 'status'),
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
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'cover_letter' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        if (! isset($validated['resume']) && ! $jobSeeker->resume_path) {
            return back()->withErrors(['resume' => 'Please upload a resume or add one to your profile first.']);
        }

        $storedPaths = [];

        try {
            $application = DB::transaction(function () use ($validated, $job, $jobSeeker, &$storedPaths): Application {
                $application = Application::create([
                    'job_id' => $job->id,
                    'job_seeker_id' => $jobSeeker->id,
                    'status' => Application::STATUS_APPLIED,
                    'applied_at' => now(),
                ]);

                $directory = 'applications/'.$application->id;
                $resumePath = isset($validated['resume'])
                    ? $this->storage->store($validated['resume'], $jobSeeker->id, $directory.'/resume')
                    : $this->storage->copyPrivate($jobSeeker->resume_path, $jobSeeker->id, $directory.'/resume');
                $storedPaths[] = $resumePath;

                $coverLetterPath = $this->storage->store(
                    $validated['cover_letter'],
                    $jobSeeker->id,
                    $directory.'/cover-letter',
                );
                $storedPaths[] = $coverLetterPath;

                $application->update([
                    'submitted_resume_path' => $resumePath,
                    'submitted_cover_letter_path' => $coverLetterPath,
                ]);

                return $application;
            });
        } catch (Throwable $e) {
            foreach ($storedPaths as $storedPath) {
                $this->storage->delete($storedPath);
            }

            Log::error('Secure application document storage failed', [
                'job_seeker_id' => $jobSeeker->id,
                'job_id' => $job->id,
                'exception_class' => $e::class,
            ]);

            return back()->withInput()->with('error', 'The application documents could not be stored securely. Please try again.');
        }

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

        if (! in_array($application->status, $withdrawableStatuses, true)) {
            return back()->with('error', 'This application cannot be withdrawn at its current stage.');
        }

        $application->update(['status' => Application::STATUS_WITHDRAWN]);

        return back()->with('success', 'Application withdrawn.');
    }

    private function dispatchApplicationNotifications(Application $application): void
    {
        $application->loadMissing([
            'jobSeeker.user',
            'jobSeeker.program',
            'job.employer.user',
        ]);

        $jobSeekerUser = $application->jobSeeker?->user;
        $employer = $application->job?->employer;
        $employerUser = $employer?->user;

        if ($employerUser) {
            $this->attemptApplicationNotification(
                $application,
                'employer_database_notification',
                $employerUser->email,
                function () use ($employerUser, $application): void {
                    $employerUser->notify(new ApplicationSubmittedNotification($application));
                },
            );
        }

        if ($jobSeekerUser?->email) {
            $this->attemptApplicationNotification(
                $application,
                'applicant_confirmation_email',
                $jobSeekerUser->email,
                function () use ($jobSeekerUser, $application): void {
                    Mail::to($jobSeekerUser->email)->send(new JobSeekerApplicationSubmittedMail($application));
                },
            );
        }

        if ($employer?->notificationEmail()) {
            $this->attemptApplicationNotification(
                $application,
                'employer_new_applicant_email',
                $employer->notificationEmail(),
                function () use ($employer, $application): void {
                    Mail::to($employer->notificationEmail())->send(new EmployerNewApplicantMail($application));
                },
            );
        }

        $adminRecipient = trim((string) config('mail.admin_address'));

        $this->attemptApplicationNotification(
            $application,
            'admin_new_application_email',
            $adminRecipient,
            function () use ($adminRecipient, $application): void {
                Mail::to($adminRecipient)->send(new AdminNewApplicationMail($application));
            },
        );
    }

    private function attemptApplicationNotification(
        Application $application,
        string $type,
        ?string $recipient,
        callable $dispatch,
    ): void {
        if (! $recipient) {
            Log::warning('Application notification skipped', [
                'application_id' => $application->id,
                'user_id' => $application->jobSeeker?->user_id,
                'notification_type' => $type,
                'reason' => 'recipient_not_configured',
            ]);

            return;
        }

        try {
            $dispatch();

            Log::info('Application notification dispatched', [
                'application_id' => $application->id,
                'user_id' => $application->jobSeeker?->user_id,
                'notification_type' => $type,
            ]);
        } catch (\Throwable $e) {
            Log::error('Application notification failed', [
                'application_id' => $application->id,
                'user_id' => $application->jobSeeker?->user_id,
                'notification_type' => $type,
                'exception_class' => $e::class,
            ]);
        }
    }
}
