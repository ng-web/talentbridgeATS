<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\Program;
use Illuminate\Http\Request;

class EmployerJobController extends Controller
{
    public function index()
    {
        $jobs = Job::where('employer_id', auth()->user()->employer->id)
            ->latest()
            ->get();

        return view('employer.jobs.index', compact('jobs'));
    }

    public function create()
    {
        $programs = Program::all();

        return view('employer.jobs.create', compact('programs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required',
            'description' => 'required',
            'location' => 'required',
            'employment_type' => 'required',
        ]);

        $validated['employer_id'] = auth()->user()->employer->id;
        $validated['slug'] = str()->slug($validated['title']);
        $validated['status'] = 'published';
        $validated['is_approved'] = true;

        Job::create($validated);

        return redirect()->route('employer.jobs.index')
            ->with('success', 'Job created successfully.');
    }

    public function edit(Job $job)
    {
        return view('employer.jobs.edit', compact('job'));
    }

    public function update(Request $request, Job $job)
    {
        $validated = $request->validate([
            'title' => 'required',
            'description' => 'required',
            'location' => 'required',
            'employment_type' => 'required',
        ]);

        $job->update($validated);

        return redirect()->route('employer.jobs.index')
            ->with('success', 'Job updated.');
    }
}