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
            'profile_completeness' => $this->recalculate($jobSeeker, ['resume_path' => $path]),
        ]);

        return redirect()
            ->route('jobseeker.profile.edit')
            ->with('success', 'Default resume uploaded successfully.');
    }

    public function clearResume(): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $jobSeeker->update([
            'resume_path' => null,
            'profile_completeness' => $this->recalculate($jobSeeker, ['resume_path' => null]),
        ]);

        return redirect()
            ->route('jobseeker.profile.edit')
            ->with('success', 'Default resume removed.');
    }

    public function uploadCoverLetter(Request $request): RedirectResponse
    {
        return redirect()
            ->route('jobseeker.profile.edit')
            ->with('error', 'Profile cover letters are no longer used. Upload a cover letter during each job application instead.');
    }

    private function recalculate($jobSeeker, array $override = []): int
    {
        $data = [
            'date_of_birth'      => $jobSeeker->date_of_birth,
            'location'           => $jobSeeker->location,
            'phone'              => $jobSeeker->phone,
            'education'          => $jobSeeker->education,
            'experience_summary' => $jobSeeker->experience_summary,
            'skills'             => $jobSeeker->skills,
            'resume_path'        => $override['resume_path'] ?? $jobSeeker->resume_path,
        ];

        $completed = collect($data)->filter(fn ($v) => filled($v))->count();

        return (int) round(($completed / count($data)) * 100);
    }
}
