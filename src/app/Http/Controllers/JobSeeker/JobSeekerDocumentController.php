<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\JobSeekerDocument;
use App\Services\Documents\ApplicantDocumentLifecycle;
use App\Services\Documents\ApplicantDocumentStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class JobSeekerDocumentController extends Controller
{
    public function __construct(
        private readonly ApplicantDocumentStorage $storage,
        private readonly ApplicantDocumentLifecycle $lifecycle,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $type = $request->input('document_type', '');

        if (! in_array($type, JobSeekerDocument::TYPES, true)) {
            return back()->with('error', 'Invalid document type.');
        }

        $request->validate([
            'document_type' => ['required', 'string', 'in:'.implode(',', JobSeekerDocument::TYPES)],
            'file' => JobSeekerDocument::validationRulesFor($type),
        ]);

        $file = $request->file('file');
        $path = $this->storage->store($file, $jobSeeker->id, 'documents/'.$type);
        $originalName = $file->getClientOriginalName();
        $existing = null;
        $replacedAttributes = null;

        try {
            DB::transaction(function () use (
                $jobSeeker,
                $originalName,
                $path,
                $type,
                &$existing,
                &$replacedAttributes,
            ): void {
                if (in_array($type, JobSeekerDocument::MULTI_UPLOAD_TYPES, true)) {
                    JobSeekerDocument::create([
                        'job_seeker_id' => $jobSeeker->id,
                        'document_type' => $type,
                        'file_path' => $path,
                        'original_name' => $originalName,
                        'uploaded_at' => now(),
                    ]);
                } else {
                    $existing = JobSeekerDocument::query()
                        ->where('job_seeker_id', $jobSeeker->id)
                        ->where('document_type', $type)
                        ->first();

                    if ($existing) {
                        $replacedAttributes = $existing->only(['file_path', 'original_name', 'uploaded_at']);
                        $existing->update([
                            'file_path' => $path,
                            'original_name' => $originalName,
                            'uploaded_at' => now(),
                        ]);

                        if ($replacedAttributes['file_path'] !== $path) {
                            $this->lifecycle->deleteAfterCommit((string) $replacedAttributes['file_path']);
                        }
                    } else {
                        JobSeekerDocument::create([
                            'job_seeker_id' => $jobSeeker->id,
                            'document_type' => $type,
                            'file_path' => $path,
                            'original_name' => $originalName,
                            'uploaded_at' => now(),
                        ]);
                    }
                }
            });
        } catch (Throwable $e) {
            $this->storage->delete($path);
            Log::error('Applicant document replacement failed', [
                'job_seeker_id' => $jobSeeker->id,
                'document_id' => $existing?->id,
                'document_type' => $type,
                'exception_class' => $e::class,
            ]);

            return back()->with('error', 'The existing document could not be replaced safely.');
        }

        return back()->with('success', JobSeekerDocument::labelFor($type).' uploaded successfully.');
    }

    public function destroy(JobSeekerDocument $document): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker && $document->job_seeker_id === $jobSeeker->id, 403);

        try {
            DB::transaction(fn () => $document->delete());
        } catch (Throwable $e) {
            Log::error('Applicant document removal failed', [
                'job_seeker_id' => $jobSeeker->id,
                'document_id' => $document->id,
                'document_type' => $document->document_type,
                'exception_class' => $e::class,
            ]);

            return back()->with('error', 'The document could not be removed safely.');
        }

        return back()->with('success', 'Document removed.');
    }
}
