<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\JobSeekerDocument;
use App\Models\PolicyDocument;
use App\Models\SensitiveProcessingEvidence;
use App\Services\Documents\ApplicantDocumentLifecycle;
use App\Services\Documents\ApplicantDocumentStorage;
use App\Services\Privacy\LegalHoldService;
use App\Services\Privacy\RetentionDataCategories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class JobSeekerDocumentController extends Controller
{
    public function __construct(
        private readonly ApplicantDocumentStorage $storage,
        private readonly ApplicantDocumentLifecycle $lifecycle,
        private readonly LegalHoldService $holds,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $type = $request->input('document_type', '');

        if (! in_array($type, JobSeekerDocument::TYPES, true)) {
            return back()->with('error', 'Invalid document type.');
        }

        if (config('privacy.sensitive_processing.enforcement_enabled')
            && in_array($type, SensitiveProcessingEvidence::CATEGORIES, true)
            && ! $this->hasCurrentPurposeAuthorization((int) Auth::id(), $type)) {
            return back()->withErrors([
                'file' => 'Kairox-approved processing evidence is required before this high-risk document can be collected.',
            ]);
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
                $this->holds->lockSubject((int) $jobSeeker->user_id);
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
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        if ($this->holds->activeAppliesCurrent((int) $jobSeeker->user_id, RetentionDataCategories::APPLICANT_DOCUMENT, (int) $existing->id)) {
                            throw ValidationException::withMessages(['file' => 'An active hold prevents replacement of this document.']);
                        }
                        $replacedAttributes = $existing->only(['file_path', 'original_name', 'uploaded_at', 'artifact_revision']);
                        $existing->update([
                            'file_path' => $path,
                            'original_name' => $originalName,
                            'uploaded_at' => now(),
                        ]);

                        if ($replacedAttributes['file_path'] !== $path) {
                            $this->lifecycle->deleteAfterCommit((string) $replacedAttributes['file_path'], [
                                'subject_user_id' => (int) $jobSeeker->user_id,
                                'data_category' => RetentionDataCategories::APPLICANT_DOCUMENT,
                                'resource_id' => (int) $existing->id,
                                'plan_item_id' => null,
                                'artifact_revision' => (string) $replacedAttributes['artifact_revision'],
                                'artifact_fingerprint' => hash('sha256', (string) $replacedAttributes['file_path']),
                            ]);
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
            DB::transaction(function () use ($document, $jobSeeker): void {
                $this->holds->lockSubject((int) $jobSeeker->user_id);
                $current = JobSeekerDocument::query()->lockForUpdate()->findOrFail($document->id);
                abort_unless((int) $current->job_seeker_id === (int) $jobSeeker->id, 403);
                if ($this->holds->activeAppliesCurrent((int) $jobSeeker->user_id, RetentionDataCategories::APPLICANT_DOCUMENT, (int) $current->id)) {
                    throw ValidationException::withMessages(['resource' => 'An active hold prevents removal of this document.']);
                }
                $current->delete();
            });
        } catch (Throwable $e) {
            Log::error('Applicant document removal failed', [
                'exception_class' => $e::class,
            ]);

            return back()->with('error', 'The document could not be removed safely.');
        }

        return back()->with('success', 'Document removed.');
    }

    private function hasCurrentPurposeAuthorization(int $userId, string $category): bool
    {
        $purposes = (array) config('privacy.sensitive_processing.purpose_codes', []);
        $evidenceTypes = (array) config('privacy.sensitive_processing.evidence_types', []);
        if ($purposes === [] || $evidenceTypes === []) {
            return false;
        }

        $query = SensitiveProcessingEvidence::query()
            ->where('user_id', $userId)
            ->where('category', $category)
            ->whereIn('purpose_code', $purposes)
            ->whereIn('evidence_type', $evidenceTypes)
            ->whereNull('withdrawn_at');

        if (config('privacy.sensitive_processing.require_current_policy')) {
            $policyType = config('privacy.sensitive_processing.current_policy_type');
            if (! is_string($policyType) || ! in_array($policyType, PolicyDocument::TYPES, true)) {
                return false;
            }
            $current = PolicyDocument::current($policyType);
            if (! $current) {
                return false;
            }
            $query->where('policy_document_id', $current->id);
        }

        return $query->exists();
    }
}
