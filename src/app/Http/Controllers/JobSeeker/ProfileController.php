<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ProfileController extends Controller
{
    public function edit(): View
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $jobSeeker->load(['documents', 'program']);

        $programs = Program::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('jobseeker.profile.edit', compact('jobSeeker', 'programs'));
    }

    public function update(Request $request): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $programRule = $jobSeeker->program_id
            ? ['nullable', 'integer', Rule::in([$jobSeeker->program_id])]
            : ['required', 'integer', Rule::exists('programs', 'id')->where('is_active', true)];

        $validated = $request->validate([
            'program_id' => $programRule,
            'date_of_birth' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'education' => ['nullable', 'string'],
            'experience_summary' => ['nullable', 'string'],
            'skills' => ['nullable', 'string'],
            'work_study_interest_flag' => ['nullable', 'boolean'],
        ]);

        $jobSeeker->update([
            'program_id' => $jobSeeker->program_id ?: $validated['program_id'],
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
            if (! empty($data[$field])) {
                $completed++;
            }
        }

        return (int) round(($completed / count($fields)) * 100);
    }
}
