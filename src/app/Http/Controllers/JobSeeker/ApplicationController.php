<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Job;
use App\Mail\EmployerNewApplicantMail;
use App\Mail\JobSeekerApplicationSubmittedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class ApplicationController extends Controller
{
    public function index(): View
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $applications = Application::query()
            ->where('job_seeker_id', $jobSeeker->id)
            ->with('job')
            ->latest()
            ->get();

        return view('jobseeker.applications.index', compact('applications'));
    }

    public function store(Job $job): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);
        abort_unless($job->is_approved && $job->status === 'published', 404);

        if (!$jobSeeker->resume_path) {
            return redirect()
                ->route('jobseeker.profile.edit')
                ->with('status', 'Please upload your resume before applying.');
        }

        $existingApplication = Application::query()
            ->where('job_id', $job->id)
            ->where('job_seeker_id', $jobSeeker->id)
            ->first();

        if ($existingApplication) {
            return redirect()
                ->route('jobseeker.applications.index')
                ->with('status', 'You have already applied to this opportunity.');
        }

        $application = Application::create([
            'job_id' => $job->id,
            'job_seeker_id' => $jobSeeker->id,
            'status' => 'applied',
            'applied_at' => now(),
            'submitted_resume_path' => $jobSeeker->resume_path,
            'submitted_cover_letter_path' => $jobSeeker->cover_letter_path,
        ]);

        Mail::to($jobSeeker->user->email)->send(new JobSeekerApplicationSubmittedMail($application));

        if ($job->employer?->user?->email) {
            Mail::to($job->employer->user->email)->send(new EmployerNewApplicantMail($application));
        }

        return redirect()
            ->route('jobseeker.applications.index')
            ->with('status', 'Application submitted successfully.');
    }
}