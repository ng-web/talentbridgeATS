<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Services\Documents\ApplicantDocumentLifecycle;
use App\Services\Documents\ApplicantDocumentStorage;
use App\Services\Privacy\LegalHoldService;
use App\Services\Privacy\RetentionDataCategories;
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
        private readonly LegalHoldService $holds,
    ) {}

    public function uploadResume(Request $request): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $request->validate([
            'resume' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        $path = $this->storage->store($request->file('resume'), $jobSeeker->id, 'profile/resume');

        try {
            DB::transaction(function () use ($jobSeeker, $path): void {
                $this->holds->lockSubject((int) $jobSeeker->user_id);
                $current = $jobSeeker->newQuery()->lockForUpdate()->findOrFail($jobSeeker->id);
                $oldPath = $current->resume_path;
                if ($oldPath && $this->holds->activeAppliesCurrent((int) $current->user_id, RetentionDataCategories::APPLICANT_PROFILE, (int) $current->id)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['resume' => 'An active hold prevents replacement of this resume.']);
                }
                $current->update([
                    'resume_path' => $path,
                    'profile_completeness' => $this->recalculate($current, ['resume_path' => $path]),
                ]);

                if ($oldPath && $oldPath !== $path) {
                    $this->lifecycle->deleteAfterCommit($oldPath, [
                        'subject_user_id' => (int) $current->user_id,
                        'data_category' => RetentionDataCategories::APPLICANT_PROFILE,
                        'resource_id' => (int) $current->id,
                        'plan_item_id' => null,
                        'artifact_revision' => null,
                        'artifact_fingerprint' => hash('sha256', $oldPath),
                    ]);
                }
            });
        } catch (Throwable $e) {
            $this->storage->delete($path);
            Log::error('Applicant resume replacement failed', [
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
                $this->holds->lockSubject((int) $jobSeeker->user_id);
                $current = $jobSeeker->newQuery()->lockForUpdate()->findOrFail($jobSeeker->id);
                $oldPath = $current->resume_path;
                if ($oldPath && $this->holds->activeAppliesCurrent((int) $current->user_id, RetentionDataCategories::APPLICANT_PROFILE, (int) $current->id)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['resume' => 'An active hold prevents removal of this resume.']);
                }

                $current->update([
                    'resume_path' => null,
                    'profile_completeness' => $this->recalculate($current, ['resume_path' => null]),
                ]);

                $this->lifecycle->deleteAfterCommit($oldPath, [
                    'subject_user_id' => (int) $current->user_id,
                    'data_category' => RetentionDataCategories::APPLICANT_PROFILE,
                    'resource_id' => (int) $current->id,
                    'plan_item_id' => null,
                    'artifact_revision' => null,
                    'artifact_fingerprint' => $oldPath ? hash('sha256', $oldPath) : null,
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Applicant resume removal failed', [
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
