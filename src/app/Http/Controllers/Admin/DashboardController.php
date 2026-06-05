<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Entitlement;
use App\Models\Job;
use App\Models\Payment;
use App\Models\PaymentAssistanceRequest;
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

        $jobSeekerCount = User::query()->role('job_seeker')->count();

        $employerCount = User::query()->role('employer')->count();

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

        $expiringEntitlements = Entitlement::query()
            ->with('user')
            ->where('status', Entitlement::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(7)])
            ->orderBy('expires_at')
            ->take(5)
            ->get();

        $unactivatedPaymentsCount = Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->whereNull('entitlement_activated_at')
            ->count();

        $newAssistanceRequestsCount = PaymentAssistanceRequest::query()
            ->where('status', PaymentAssistanceRequest::STATUS_NEW)
            ->count();

        return view('admin.dashboard', [
            'pendingJobsCount' => $pendingJobsCount,
            'publishedJobsCount' => $publishedJobsCount,
            'applicationCount' => $applicationCount,
            'userCount' => $userCount,
            'jobSeekerCount' => $jobSeekerCount,
            'employerCount' => $employerCount,
            'reviewRequiredPaymentsCount' => $reviewRequiredPaymentsCount,
            'activeEntitlementsCount' => $activeEntitlementsCount,
            'expiringEntitlementsCount' => $expiringEntitlementsCount,
            'recentPendingJobs' => $recentPendingJobs,
            'recentReviewPayments' => $recentReviewPayments,
            'expiringEntitlements' => $expiringEntitlements,
            'unactivatedPaymentsCount'      => $unactivatedPaymentsCount,
            'newAssistanceRequestsCount'    => $newAssistanceRequestsCount,
        ]);
    }
}
