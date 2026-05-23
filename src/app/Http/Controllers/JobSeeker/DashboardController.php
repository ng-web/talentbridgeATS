<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Entitlement;
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

        $applicationStatusCounts = Application::query()
            ->where('job_seeker_id', $jobSeeker->id)
            ->whereNotIn('status', [Application::STATUS_WITHDRAWN])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentApplications = Application::query()
            ->with(['job.employer.user'])
            ->where('job_seeker_id', $jobSeeker->id)
            ->latest()
            ->take(5)
            ->get();

        $entitlement = Auth::user()->entitlements()
            ->where('type', Entitlement::TYPE_JOB_SEEKER_ACCESS)
            ->latest()
            ->first();

        return view('jobseeker.dashboard', compact(
            'applicationCount',
            'applicationStatusCounts',
            'recentApplications',
            'entitlement',
            'jobSeeker',
        ));
    }
}
