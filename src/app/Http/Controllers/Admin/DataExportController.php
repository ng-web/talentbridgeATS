<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateApplicantExport;
use App\Models\DataExport;
use App\Models\PrivacyRequest;
use App\Services\Privacy\ApplicantExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DataExportController extends Controller
{
    public function authorizeExport(Request $request, PrivacyRequest $privacyRequest, ApplicantExportService $service): RedirectResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'array', 'min:1'],
            'scope.*' => ['required', 'string', Rule::in(DataExport::ALLOWED_SCOPES)],
        ]);
        $export = $service->authorize($request->user(), $privacyRequest, $validated['scope']);

        return back()->with('success', "Export {$export->uuid} authorized. Generation remains a separate privileged action.");
    }

    public function generate(Request $request, PrivacyRequest $privacyRequest, DataExport $dataExport, ApplicantExportService $service): RedirectResponse
    {
        $this->assertBound($privacyRequest, $dataExport);
        $queued = $service->recordGenerationInitiated($request->user(), $dataExport);
        GenerateApplicantExport::dispatch($queued->id)->afterCommit();

        return back()->with('success', 'Private export generation queued.');
    }

    public function retry(Request $request, PrivacyRequest $privacyRequest, DataExport $dataExport, ApplicantExportService $service): RedirectResponse
    {
        $this->assertBound($privacyRequest, $dataExport);
        $queued = $service->retryFailedGeneration($request->user(), $dataExport);
        GenerateApplicantExport::dispatch($queued->id)->afterCommit();

        return back()->with('success', 'Failed private export explicitly authorized for retry.');
    }

    public function reauthorize(Request $request, PrivacyRequest $privacyRequest, DataExport $dataExport, ApplicantExportService $service): RedirectResponse
    {
        $this->assertBound($privacyRequest, $dataExport);
        $service->reauthorize($request->user(), $dataExport);

        return back()->with('success', 'Private export authorization renewed by the current controller administrator.');
    }

    public function download(Request $request, PrivacyRequest $privacyRequest, DataExport $dataExport, ApplicantExportService $service): StreamedResponse
    {
        $this->assertBound($privacyRequest, $dataExport);
        $export = $service->recordDownload($request->user(), $dataExport);

        return Storage::disk((string) config('privacy.exports.disk', 'private'))->download(
            $export->private_path,
            'kairox-applicant-data-'.$export->uuid.'.zip',
            [
                'Cache-Control' => 'private, no-store, max-age=0',
                'Pragma' => 'no-cache',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function assertBound(PrivacyRequest $request, DataExport $export): void
    {
        abort_unless($export->privacy_request_id === $request->id && $export->subject_user_id === $request->subject_user_id, 404);
    }
}
