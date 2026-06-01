<?php

namespace App\Http\Controllers\JobSeeker;

use App\Http\Controllers\Controller;
use App\Models\JobSeekerDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class JobSeekerDocumentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker, 404);

        $type = $request->input('document_type', '');

        if (!in_array($type, JobSeekerDocument::TYPES, true)) {
            return back()->with('error', 'Invalid document type.');
        }

        $request->validate([
            'document_type' => ['required', 'string', 'in:' . implode(',', JobSeekerDocument::TYPES)],
            'file'          => JobSeekerDocument::validationRulesFor($type),
        ]);

        $file         = $request->file('file');
        $path         = $file->store('jobseekers/documents/' . $type, 'public');
        $originalName = $file->getClientOriginalName();

        if (in_array($type, JobSeekerDocument::MULTI_UPLOAD_TYPES, true)) {
            JobSeekerDocument::create([
                'job_seeker_id' => $jobSeeker->id,
                'document_type' => $type,
                'file_path'     => $path,
                'original_name' => $originalName,
                'uploaded_at'   => now(),
            ]);
        } else {
            JobSeekerDocument::updateOrCreate(
                ['job_seeker_id' => $jobSeeker->id, 'document_type' => $type],
                ['file_path' => $path, 'original_name' => $originalName, 'uploaded_at' => now()]
            );
        }

        return back()->with('success', JobSeekerDocument::labelFor($type) . ' uploaded successfully.');
    }

    public function destroy(JobSeekerDocument $document): RedirectResponse
    {
        $jobSeeker = Auth::user()->jobSeeker;

        abort_unless($jobSeeker && $document->job_seeker_id === $jobSeeker->id, 403);

        $document->delete();

        return back()->with('success', 'Document removed.');
    }
}
