<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class ProfileController extends Controller
{
    public function edit(): View
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $jobSeeker->load('documents');

        return view('jobseeker.profile.edit', compact('jobSeeker'));
    }

    public function update(Request $request): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $validated = $request->validate([
            'date_of_birth' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'education' => ['nullable', 'string'],
            'experience_summary' => ['nullable', 'string'],
            'skills' => ['nullable', 'string'],
            'work_study_interest_flag' => ['nullable', 'boolean'],
        ]);

        $jobSeeker->update([
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'location' => $validated['location'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'education' => $validated['education'] ?? null,
            'experience_summary' => $validated['experience_summary'] ?? null,
            'skills' => $validated['skills'] ?? null,
            'work_study_interest_flag' => (bool) ($validated['work_study_interest_flag'] ?? false),
            'profile_completeness' => $this->calculateProfileCompleteness([
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'location' => $validated['location'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'education' => $validated['education'] ?? null,
                'experience_summary' => $validated['experience_summary'] ?? null,
                'skills' => $validated['skills'] ?? null,
                'resume_path' => $jobSeeker->resume_path,
            ]),
        ]);

        return redirect()
            ->route('jobseeker.profile.edit')
            ->with('status', 'profile-updated');
    }

    private function calculateProfileCompleteness(array $data): int
    {
        $fields = [
            'date_of_birth',
            'location',
            'phone',
            'education',
            'experience_summary',
            'skills',
            'resume_path',
        ];

        $completed = 0;

        foreach ($fields as $field) {
            if (!empty($data[$field])) {
                $completed++;
            }
        }

        return (int) round(($completed / count($fields)) * 100);
    }
}