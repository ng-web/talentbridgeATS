<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Services\Documents\ApplicantDocumentStorage;
use App\Services\Documents\DocumentAccessPolicy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentDownloadController extends Controller
{
    public function __construct(
        private readonly ApplicantDocumentStorage $storage,
        private readonly DocumentAccessPolicy $access,
    ) {}

    public function jobSeekerDocument(Request $request, JobSeekerDocument $document): StreamedResponse
    {
        $document->loadMissing('jobSeeker.user');
        $this->authorizeAccess($request, $document->jobSeeker, $document->document_type);

        $filename = $this->safeDownloadName(
            $document->original_name,
            JobSeekerDocument::labelFor($document->document_type),
            $document->file_path,
        );

        $response = $this->respond(
            $document->file_path,
            $filename,
            $document->document_type === JobSeekerDocument::TYPE_PROFILE_PHOTO,
        );
        $this->auditIfSensitive($request, $document->jobSeeker, $document->document_type, $document);

        return $response;
    }

    public function profile(Request $request, JobSeeker $jobSeeker, string $type): StreamedResponse
    {
        $definition = match ($type) {
            'resume' => [$jobSeeker->resume_path, DocumentAccessPolicy::PROFILE_RESUME, 'resume'],
            'cover-letter' => [$jobSeeker->cover_letter_path, DocumentAccessPolicy::PROFILE_COVER_LETTER, 'cover-letter'],
            default => abort(404),
        };

        [$path, $documentType, $filename] = $definition;
        abort_unless(filled($path), 404);
        $this->authorizeAccess($request, $jobSeeker, $documentType);

        return $this->respond($path, $this->safeDownloadName(null, $filename, $path));
    }

    public function application(Request $request, Application $application, string $type): StreamedResponse
    {
        $application->loadMissing(['job', 'jobSeeker.user']);
        $definition = match ($type) {
            'resume' => [$application->submitted_resume_path, DocumentAccessPolicy::APPLICATION_RESUME, 'resume'],
            'cover-letter' => [$application->submitted_cover_letter_path, DocumentAccessPolicy::APPLICATION_COVER_LETTER, 'cover-letter'],
            default => abort(404),
        };

        [$path, $documentType, $filename] = $definition;
        abort_unless(filled($path), 404);
        $this->authorizeAccess($request, $application->jobSeeker, $documentType, $application);

        return $this->respond($path, $this->safeDownloadName(null, $filename, $path));
    }

    private function authorizeAccess(
        Request $request,
        JobSeeker $jobSeeker,
        string $documentType,
        ?Application $application = null,
    ): void {
        abort_unless(
            $request->user() && $this->access->canAccess($request->user(), $jobSeeker, $documentType, $application),
            403,
        );
    }

    private function respond(string $path, string $filename, bool $inline = false): StreamedResponse
    {
        $disk = $this->storage->diskContaining($path);
        abort_if($disk === null, 404);
        $headers = [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($inline) {
            return \Illuminate\Support\Facades\Storage::disk($disk)->response(
                $path,
                $filename,
                [...$headers, 'Content-Disposition' => 'inline; filename="'.$filename.'"'],
            );
        }

        return \Illuminate\Support\Facades\Storage::disk($disk)->download($path, $filename, $headers);
    }

    private function auditIfSensitive(
        Request $request,
        JobSeeker $jobSeeker,
        string $documentType,
        JobSeekerDocument $document,
    ): void {
        if (! $this->access->isHighRisk($documentType)) {
            return;
        }

        AuditLog::create([
            'actor_user_id' => $request->user()->id,
            'action' => 'sensitive_document_downloaded',
            'entity_type' => JobSeekerDocument::class,
            'entity_id' => $document->id,
            'meta' => [
                'actor_role' => $request->user()->getRoleNames()->first(),
                'document_type' => $documentType,
                'applicant_user_id' => $jobSeeker->user_id,
            ],
        ]);
    }

    private function safeDownloadName(?string $originalName, string $fallback, string $path): string
    {
        $extension = $this->storage->verifiedExtension($path);
        $name = $originalName ? basename(str_replace('\\', '/', $originalName)) : $fallback;
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: $fallback;
        $baseName = pathinfo($name, PATHINFO_FILENAME);
        $baseName = trim($baseName, '. ');
        $baseName = $baseName !== '' ? $baseName : $fallback;

        return $extension !== '' ? $baseName.'.'.$extension : $baseName;
    }
}
