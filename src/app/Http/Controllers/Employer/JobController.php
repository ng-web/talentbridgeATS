<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Models\Job;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class JobController extends Controller
{
    public function index(): View
    {
        $employer = Auth::user()->employer;

        abort_unless($employer, 404);

        $jobs = Job::query()
            ->where('employer_id', $employer->id)
            ->latest()
            ->get();

        return view('employer.jobs.index', compact('jobs'));
    }

    public function create(): View
    {
        $programs = Program::query()->orderBy('name')->get();

        return view('employer.jobs.create', [
            'programs' => $programs,
            'listingTypes' => Job::LISTING_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employer = Employer::query()
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'program_id'           => ['nullable', 'exists:programs,id'],
            'title'                => ['required', 'string', 'max:255'],
            'description'          => ['required', 'string'],
            'listing_type'         => ['required', 'string', 'in:' . implode(',', Job::LISTING_TYPES)],
            'category'             => ['nullable', 'string', 'max:255'],
            'employment_type'      => ['nullable', 'string', 'max:255'],
            'location'             => ['nullable', 'string', 'max:255'],
            'country'              => ['nullable', 'string', 'max:255'],
            'remote_flag'          => ['nullable', 'boolean'],
            'duration'             => ['nullable', 'string', 'max:255'],
            'salary_min'           => ['nullable', 'integer', 'min:0'],
            'salary_max'           => ['nullable', 'integer', 'min:0', 'gte:salary_min'],
            'fees'                 => ['nullable', 'integer', 'min:0'],
            'application_deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'eligibility'          => ['nullable', 'string'],
        ]);

        Job::create([
            'employer_id'          => $employer->id,
            'program_id'           => $validated['program_id'] ?? null,
            'title'                => $validated['title'],
            'slug'                 => Str::slug($validated['title']) . '-' . Str::lower(Str::random(6)),
            'description'          => $validated['description'],
            'listing_type'         => $validated['listing_type'],
            'category'             => $validated['category'] ?? null,
            'employment_type'      => $validated['employment_type'] ?? null,
            'location'             => $validated['location'] ?? null,
            'country'              => $validated['country'] ?? null,
            'status'               => Job::STATUS_PENDING_REVIEW,
            'is_approved'          => false,
            'remote_flag'          => (bool) ($validated['remote_flag'] ?? false),
            'duration'             => $validated['duration'] ?? null,
            'salary_min'           => $validated['salary_min'] ?? null,
            'salary_max'           => $validated['salary_max'] ?? null,
            'fees'                 => $validated['fees'] ?? null,
            'application_deadline' => $validated['application_deadline'] ?? null,
            'eligibility'          => $validated['eligibility'] ?? null,
        ]);

        return redirect()
            ->route('employer.jobs.index')
            ->with('status', 'Job submitted for review successfully.');
    }

    public function edit(Job $job): View
    {
        $employer = Auth::user()->employer;

        abort_unless($employer && $job->employer_id === $employer->id, 403);

        $programs = Program::query()->orderBy('name')->get();

        return view('employer.jobs.edit', [
            'job' => $job,
            'programs' => $programs,
            'listingTypes' => Job::LISTING_TYPES,
        ]);
    }

    public function update(Request $request, Job $job): RedirectResponse
    {
        $employer = Auth::user()->employer;

        abort_unless($employer && $job->employer_id === $employer->id, 403);

        $validated = $request->validate([
            'program_id'           => ['nullable', 'exists:programs,id'],
            'title'                => ['required', 'string', 'max:255'],
            'description'          => ['required', 'string'],
            'listing_type'         => ['required', 'string', 'in:' . implode(',', Job::LISTING_TYPES)],
            'category'             => ['nullable', 'string', 'max:255'],
            'employment_type'      => ['nullable', 'string', 'max:255'],
            'location'             => ['nullable', 'string', 'max:255'],
            'country'              => ['nullable', 'string', 'max:255'],
            'remote_flag'          => ['nullable', 'boolean'],
            'duration'             => ['nullable', 'string', 'max:255'],
            'salary_min'           => ['nullable', 'integer', 'min:0'],
            'salary_max'           => ['nullable', 'integer', 'min:0', 'gte:salary_min'],
            'fees'                 => ['nullable', 'integer', 'min:0'],
            'application_deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'eligibility'          => ['nullable', 'string'],
        ]);

        $job->update([
            'program_id'           => $validated['program_id'] ?? null,
            'title'                => $validated['title'],
            'description'          => $validated['description'],
            'listing_type'         => $validated['listing_type'],
            'category'             => $validated['category'] ?? null,
            'employment_type'      => $validated['employment_type'] ?? null,
            'location'             => $validated['location'] ?? null,
            'country'              => $validated['country'] ?? null,
            'remote_flag'          => (bool) ($validated['remote_flag'] ?? false),
            'duration'             => $validated['duration'] ?? null,
            'salary_min'           => $validated['salary_min'] ?? null,
            'salary_max'           => $validated['salary_max'] ?? null,
            'fees'                 => $validated['fees'] ?? null,
            'application_deadline' => $validated['application_deadline'] ?? null,
            'eligibility'          => $validated['eligibility'] ?? null,
            'status'               => Job::STATUS_PENDING_REVIEW,
            'is_approved'          => false,
        ]);

        return redirect()
            ->route('employer.jobs.index')
            ->with('status', 'Job updated and returned for review.');
    }
}