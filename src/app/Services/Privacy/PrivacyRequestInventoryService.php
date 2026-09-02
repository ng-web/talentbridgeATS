<?php

namespace App\Services\Privacy;

use App\Models\AuditLog;
use App\Models\PaymentAssistanceRequest;
use App\Models\PrivacyRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PrivacyRequestInventoryService
{
    /** @return list<array{category:string,record_count:int,export_eligible:bool,retention_flag:string}> */
    public function inventory(User $subject): array
    {
        $jobSeeker = $subject->jobSeeker;

        return [
            $this->row('account', 1, true),
            $this->row('job_seeker_profile', $jobSeeker ? 1 : 0, true),
            $this->row('applications', $jobSeeker?->applications()->count() ?? 0, true),
            $this->row('application_files', $jobSeeker ? DB::table('application_files')->join('applications', 'applications.id', '=', 'application_files.application_id')->where('applications.job_seeker_id', $jobSeeker->id)->count() : 0, false, 'scope_approval_required'),
            $this->row('sensitive_documents', $jobSeeker?->documents()->count() ?? 0, false, 'excluded_by_default'),
            $this->row('payments', $subject->payments()->count(), true),
            $this->row('entitlements', $subject->entitlements()->count(), true),
            $this->row('notifications', $subject->notifications()->count(), false, 'manual_review_required'),
            $this->row('policy_evidence', $subject->policyAcknowledgements()->count(), true),
            $this->row('privacy_requests', $subject->privacyRequests()->count(), true),
            $this->row('audit_references', AuditLog::query()->where('subject_user_id', $subject->id)->count(), false, 'internal_review_required'),
            $this->row('support_payment_assistance', PaymentAssistanceRequest::query()->where('email', $subject->email)->count(), false, 'manual_review_required'),
        ];
    }

    public function forRequest(PrivacyRequest $request): array
    {
        return $this->inventory($request->subject);
    }

    /** @return array{category:string,record_count:int,export_eligible:bool,retention_flag:string} */
    private function row(string $category, int $count, bool $eligible, string $flag = 'not_assessed'): array
    {
        return ['category' => $category, 'record_count' => $count, 'export_eligible' => $eligible, 'retention_flag' => $flag];
    }
}
