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

        $applicantCount = Application::query()
            ->whereHas('job', fn ($query) => $query->where('employer_id', $employer->id))
            ->count();

        $companyCompletion = $this->calculateCompanyCompletion($employer);

        return view('employer.dashboard', compact(
            'employer',
            'jobCount',
            'applicantCount',
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
            $employer->description ?? null,
            $employer->contact_email ?? null,
        ];

        $total = count($fields);
        $completed = collect($fields)
            ->filter(fn ($value) => filled($value))
            ->count();

        return (int) round(($completed / max($total, 1)) * 100);
    }
}