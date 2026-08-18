<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Services\Documents\ApplicantDocumentLifecycle;
use App\Services\Documents\ApplicantDocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DocumentController extends Controller
{
    public function __construct(
        private readonly ApplicantDocumentStorage $storage,
        private readonly ApplicantDocumentLifecycle $lifecycle,
    ) {}

    public function uploadResume(Request $request): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $request->validate([
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        $oldPath = $jobSeeker->resume_path;
        $path = $this->storage->store($request->file('resume'), $jobSeeker->id, 'profile/resume');

        try {
            DB::transaction(function () use ($jobSeeker, $oldPath, $path): void {
                $jobSeeker->update([
                    'resume_path' => $path,
                    'profile_completeness' => $this->recalculate($jobSeeker, ['resume_path' => $path]),
                ]);

                if ($oldPath && $oldPath !== $path) {
                    $this->lifecycle->deleteAfterCommit($oldPath);
                }
            });
        } catch (Throwable $e) {
            $this->storage->delete($path);
            Log::error('Applicant resume replacement failed', [
                'job_seeker_id' => $jobSeeker->id,
                'exception_class' => $e::class,
            ]);

            return back()->with('error', 'The existing resume could not be replaced safely.');
        }

        return redirect()
            ->route('jobseeker.profile.edit')
            ->with('success', 'Default resume uploaded successfully.');
    }

    public function clearResume(): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        try {
            DB::transaction(function () use ($jobSeeker): void {
                $oldPath = $jobSeeker->resume_path;

                $jobSeeker->update([
                    'resume_path' => null,
                    'profile_completeness' => $this->recalculate($jobSeeker, ['resume_path' => null]),
                ]);

                $this->lifecycle->deleteAfterCommit($oldPath);
            });
        } catch (Throwable $e) {
            Log::error('Applicant resume removal failed', [
                'job_seeker_id' => $jobSeeker->id,
                'exception_class' => $e::class,
            ]);

            return back()->with('error', 'The resume could not be removed safely.');
        }

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
            'date_of_birth' => $jobSeeker->date_of_birth,
            'location' => $jobSeeker->location,
            'phone' => $jobSeeker->phone,
            'education' => $jobSeeker->education,
            'experience_summary' => $jobSeeker->experience_summary,
            'skills' => $jobSeeker->skills,
            'resume_path' => $override['resume_path'] ?? $jobSeeker->resume_path,
        ];

        $completed = collect($data)->filter(fn ($v) => filled($v))->count();

        return (int) round(($completed / count($data)) * 100);
    }
}
