<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Job;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class JobController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $baseQuery = Job::query()
            ->with(['employer.user'])
            ->where('is_approved', true)
            ->where('status', Job::STATUS_PUBLISHED);

        $query = clone $baseQuery;

        if ($request->filled('keyword')) {
            $keyword = trim($request->string('keyword')->toString());

            $query->where(function ($subQuery) use ($keyword) {
                $subQuery
                    ->where('title', 'like', '%' . $keyword . '%')
                    ->orWhere('description', 'like', '%' . $keyword . '%')
                    ->orWhere('category', 'like', '%' . $keyword . '%')
                    ->orWhereHas('employer', function ($employerQuery) use ($keyword) {
                        $employerQuery
                            ->where('company_name', 'like', '%' . $keyword . '%')
                            ->orWhere('industry', 'like', '%' . $keyword . '%');
                    });
            });
        }

        if ($request->filled('listing_type')) {
            $query->where('listing_type', $request->string('listing_type')->toString());
        }

        if ($request->filled('location')) {
            $query->where('location', 'like', '%' . trim($request->string('location')->toString()) . '%');
        }

        if ($request->boolean('remote_only')) {
            $query->where('remote_flag', true);
        }

        $jobs = $query->latest()->get();

        $availableLocations = (clone $baseQuery)
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->orderBy('location')
            ->pluck('location')
            ->values();

        $availableTypes = collect(Job::LISTING_TYPES);

        $viewData = [
            'jobs' => $jobs,
            'availableLocations' => $availableLocations,
            'availableTypes' => $availableTypes,
            'filters' => [
                'keyword' => $request->string('keyword')->toString(),
                'listing_type' => $request->string('listing_type')->toString(),
                'location' => $request->string('location')->toString(),
                'remote_only' => $request->boolean('remote_only'),
            ],
        ];

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'html' => view('jobseeker.jobs.partials.results', ['jobs' => $jobs])->render(),
            ]);
        }

        return view('jobseeker.jobs.index', $viewData);
    }

    public function show(Job $job): View
    {
        abort_unless($job->is_approved && $job->status === Job::STATUS_PUBLISHED, 404);

        $job->loadMissing(['employer.user']);

        $jobSeeker = Auth::user()?->jobSeeker;

        $existingApplication = $jobSeeker
            ? Application::query()
                ->where('job_id', $job->id)
                ->where('job_seeker_id', $jobSeeker->id)
                ->first()
            : null;

        $deadlinePassed = $job->application_deadline && now()->startOfDay()->isAfter($job->application_deadline);

        return view('jobseeker.jobs.show', compact('job', 'existingApplication', 'deadlinePassed'));
    }
}