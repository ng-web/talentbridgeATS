<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Entitlement;
use App\Models\Job;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $pendingJobsCount = Job::query()
            ->where('status', Job::STATUS_PENDING_REVIEW)
            ->count();

        $publishedJobsCount = Job::query()
            ->where('status', Job::STATUS_PUBLISHED)
            ->count();

        $applicationCount = Application::query()->count();

        $userCount = User::query()->count();

        $reviewRequiredPaymentsCount = Payment::query()
            ->where('status', Payment::STATUS_REVIEW_REQUIRED)
            ->count();

        $activeEntitlementsCount = Entitlement::query()
            ->where('status', Entitlement::STATUS_ACTIVE)
            ->count();

        $expiringEntitlementsCount = Entitlement::query()
            ->where('status', Entitlement::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(7)])
            ->count();

        $recentPendingJobs = Job::query()
            ->with(['employer.user'])
            ->where('status', Job::STATUS_PENDING_REVIEW)
            ->latest()
            ->take(5)
            ->get();

        $recentReviewPayments = Payment::query()
            ->with(['user', 'plan'])
            ->where('status', Payment::STATUS_REVIEW_REQUIRED)
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', [
            'pendingJobsCount' => $pendingJobsCount,
            'publishedJobsCount' => $publishedJobsCount,
            'applicationCount' => $applicationCount,
            'userCount' => $userCount,
            'reviewRequiredPaymentsCount' => $reviewRequiredPaymentsCount,
            'activeEntitlementsCount' => $activeEntitlementsCount,
            'expiringEntitlementsCount' => $expiringEntitlementsCount,
            'recentPendingJobs' => $recentPendingJobs,
            'recentReviewPayments' => $recentReviewPayments,
        ]);
    }
}