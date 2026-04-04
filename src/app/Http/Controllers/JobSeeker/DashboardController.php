<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Job;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $applicationCount = Application::query()
            ->where('job_seeker_id', $jobSeeker->id)
            ->count();

        $availableJobsCount = Job::query()
            ->where('is_approved', true)
            ->where('status', 'published')
            ->count();

        return view('jobseeker.dashboard', compact('applicationCount', 'availableJobsCount', 'jobSeeker'));
    }
}