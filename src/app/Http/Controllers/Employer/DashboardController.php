<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Employer;
use App\Models\Job;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = Auth::user();

        $employer = Employer::query()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $jobCount = Job::query()
            ->where('employer_id', $employer->id)
            ->count();

        $jobStatusCounts = Job::query()
            ->where('employer_id', $employer->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $applicantCount = Application::query()
            ->whereHas('job', fn ($q) => $q->where('employer_id', $employer->id))
            ->whereNotIn('status', [Application::STATUS_WITHDRAWN])
            ->count();

        $applicantStatusCounts = Application::query()
            ->whereHas('job', fn ($q) => $q->where('employer_id', $employer->id))
            ->whereNotIn('status', [Application::STATUS_WITHDRAWN])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentApplicants = Application::query()
            ->with(['jobSeeker.user', 'jobSeeker.documents', 'job'])
            ->whereHas('job', fn ($q) => $q->where('employer_id', $employer->id))
            ->whereNotIn('status', [Application::STATUS_WITHDRAWN])
            ->latest()
            ->take(5)
            ->get();

        $companyCompletion = $this->calculateCompanyCompletion($employer);

        return view('employer.dashboard', compact(
            'employer',
            'jobCount',
            'jobStatusCounts',
            'applicantCount',
            'applicantStatusCounts',
            'recentApplicants',
            'companyCompletion',
        ));
    }

    private function calculateCompanyCompletion(Employer $employer): int
    {
        $fields = [
            $employer->company_name,
            $employer->industry,
            $employer->logo_path,
            $employer->website ?? null,
            $employer->company_description ?? null,
            $employer->contact_person ?? null,
            $employer->contact_email ?? null,
        ];

        $total = count($fields);
        $completed = collect($fields)
            ->filter(fn ($value) => filled($value))
            ->count();

        return (int) round(($completed / max($total, 1)) * 100);
    }
}
