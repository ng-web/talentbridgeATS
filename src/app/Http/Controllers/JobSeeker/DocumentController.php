<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class DocumentController extends Controller
{
    public function uploadResume(Request $request): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $request->validate([
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        $path = $request->file('resume')->store('jobseekers/resumes', 'public');

        $jobSeeker->update([
            'resume_path' => $path,
            'profile_completeness' => $this->recalculate($jobSeeker, [
                'resume_path' => $path,
            ]),
        ]);

        return redirect()
            ->route('jobseeker.profile.edit')
            ->with('status', 'resume-uploaded');
    }

    public function uploadCoverLetter(Request $request): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $request->validate([
            'cover_letter' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        $path = $request->file('cover_letter')->store('jobseekers/cover-letters', 'public');

        $jobSeeker->update([
            'cover_letter_path' => $path,
            'profile_completeness' => $this->recalculate($jobSeeker, [
                'cover_letter_path' => $path,
            ]),
        ]);

        return redirect()
            ->route('jobseeker.profile.edit')
            ->with('status', 'cover-letter-uploaded');
    }

    private function recalculate($jobSeeker, array $override = []): int
    {
        $data = [
            'date_of_birth' => $jobSeeker->date_of_birth,
            'location' => $jobSeeker->location,
            'education' => $jobSeeker->education,
            'experience_summary' => $jobSeeker->experience_summary,
            'skills' => $jobSeeker->skills,
            'resume_path' => $override['resume_path'] ?? $jobSeeker->resume_path,
            'cover_letter_path' => $override['cover_letter_path'] ?? $jobSeeker->cover_letter_path,
        ];

        $fields = [
            'date_of_birth',
            'location',
            'education',
            'experience_summary',
            'skills',
            'resume_path',
            'cover_letter_path',
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