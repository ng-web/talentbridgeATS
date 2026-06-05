<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Employer;
use App\Models\EmploymentType;
use App\Models\Job;
use App\Models\JobCategory;
use App\Models\Location;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class JobController extends Controller
{
    public function index(Request $request): View|Response
    {
        $employer = Auth::user()->employer;

        abort_unless($employer, 404);

        $status      = trim((string) $request->query('status', ''));
        $listingType = trim((string) $request->query('listing_type', ''));

        $jobs = Job::query()
            ->where('employer_id', $employer->id)
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($listingType !== '', fn ($q) => $q->where('listing_type', $listingType))
            ->latest()
            ->get();

        // Scoped filter options — only what this employer has posted
        $availableStatuses = Job::query()
            ->where('employer_id', $employer->id)
            ->distinct()->pluck('status');

        $availableTypes = Job::query()
            ->where('employer_id', $employer->id)
            ->distinct()->pluck('listing_type');

        $data = compact('jobs', 'availableStatuses', 'availableTypes', 'status', 'listingType');

        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->view('employer.jobs.partials.list', $data);
        }

        return view('employer.jobs.index', $data);
    }

    public function create(): View
    {
        return view('employer.jobs.create', [
            'programs'        => Program::query()->orderBy('name')->get(),
            'countries'       => Country::where('is_active', true)->orderBy('name')->get(),
            'locations'       => Location::where('is_active', true)->with('country')->orderBy('name')->get()->groupBy('country.name')->map(fn($g) => $g->pluck('name')),
            'categories'      => JobCategory::where('is_active', true)->orderBy('name')->pluck('name'),
            'employmentTypes' => EmploymentType::where('is_active', true)->orderBy('name')->pluck('name'),
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
            'category'             => ['nullable', Rule::exists('job_categories', 'name')->where('is_active', true)],
            'employment_type'      => ['nullable', Rule::exists('employment_types', 'name')->where('is_active', true)],
            'country'              => ['required', Rule::exists('countries', 'name')->where('is_active', true)],
            'location'             => ['nullable', 'string', 'max:255'],
            'remote_flag'          => ['nullable', 'boolean'],
            'duration'             => ['nullable', 'string', 'max:255'],
            'salary_min'           => ['nullable', 'integer', 'min:0'],
            'salary_max'           => ['nullable', 'integer', 'min:0', 'gte:salary_min'],
            'fees'                 => ['nullable', 'integer', 'min:0'],
            'application_deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'eligibility'          => ['nullable', 'string'],
            'responsibilities'     => ['nullable', 'string'],
            'benefits'             => ['nullable', 'string'],
        ]);

        if (!empty($validated['location'])) {
            $countryId = Country::where('name', $validated['country'])->value('id');
            $locationValid = Location::where('country_id', $countryId)
                ->where('name', $validated['location'])
                ->where('is_active', true)
                ->exists();
            if (!$locationValid) {
                return back()->withErrors(['location' => 'The selected location is not valid for the chosen country.'])->withInput();
            }
        }

        Job::create([
            'employer_id'          => $employer->id,
            'program_id'           => $validated['program_id'] ?? null,
            'title'                => $validated['title'],
            'slug'                 => Str::slug($validated['title']) . '-' . Str::lower(Str::random(6)),
            'description'          => $validated['description'],
            'responsibilities'     => $validated['responsibilities'] ?? null,
            'eligibility'          => $validated['eligibility'] ?? null,
            'benefits'             => $validated['benefits'] ?? null,
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
        ]);

        return redirect()
            ->route('employer.jobs.index')
            ->with('status', 'Job submitted for review successfully.');
    }

    public function edit(Job $job): View
    {
        $employer = Auth::user()->employer;

        abort_unless($employer && $job->employer_id === $employer->id, 403);

        return view('employer.jobs.edit', [
            'job'             => $job,
            'programs'        => Program::query()->orderBy('name')->get(),
            'countries'       => Country::where('is_active', true)->orderBy('name')->get(),
            'locations'       => Location::where('is_active', true)->with('country')->orderBy('name')->get()->groupBy('country.name')->map(fn($g) => $g->pluck('name')),
            'categories'      => JobCategory::where('is_active', true)->orderBy('name')->pluck('name'),
            'employmentTypes' => EmploymentType::where('is_active', true)->orderBy('name')->pluck('name'),
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
            'category'             => ['nullable', Rule::exists('job_categories', 'name')->where('is_active', true)],
            'employment_type'      => ['nullable', Rule::exists('employment_types', 'name')->where('is_active', true)],
            'country'              => ['required', Rule::exists('countries', 'name')->where('is_active', true)],
            'location'             => ['nullable', 'string', 'max:255'],
            'remote_flag'          => ['nullable', 'boolean'],
            'duration'             => ['nullable', 'string', 'max:255'],
            'salary_min'           => ['nullable', 'integer', 'min:0'],
            'salary_max'           => ['nullable', 'integer', 'min:0', 'gte:salary_min'],
            'fees'                 => ['nullable', 'integer', 'min:0'],
            'application_deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'eligibility'          => ['nullable', 'string'],
            'responsibilities'     => ['nullable', 'string'],
            'benefits'             => ['nullable', 'string'],
        ]);

        if (!empty($validated['location'])) {
            $countryId = Country::where('name', $validated['country'])->value('id');
            $locationValid = Location::where('country_id', $countryId)
                ->where('name', $validated['location'])
                ->where('is_active', true)
                ->exists();
            if (!$locationValid) {
                return back()->withErrors(['location' => 'The selected location is not valid for the chosen country.'])->withInput();
            }
        }

        $job->update([
            'program_id'           => $validated['program_id'] ?? null,
            'title'                => $validated['title'],
            'description'          => $validated['description'],
            'responsibilities'     => $validated['responsibilities'] ?? null,
            'eligibility'          => $validated['eligibility'] ?? null,
            'benefits'             => $validated['benefits'] ?? null,
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
            'status'               => Job::STATUS_PENDING_REVIEW,
            'is_approved'          => false,
        ]);

        return redirect()
            ->route('employer.jobs.index')
            ->with('status', 'Job updated and returned for review.');
    }
}