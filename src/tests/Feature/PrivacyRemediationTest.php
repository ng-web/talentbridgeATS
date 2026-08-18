<?php

namespace Tests\Feature;

use App\Jobs\DeleteUnreferencedApplicantDocument;
use App\Mail\PaymentAssistanceAdminMail;
use App\Models\Application;
use App\Models\ApplicationFile;
use App\Models\Employer;
use App\Models\Entitlement;
use App\Models\Job;
use App\Models\JobSeeker;
use App\Models\JobSeekerDocument;
use App\Models\Payment;
use App\Models\PaymentAssistanceRequest;
use App\Models\User;
use App\Services\Documents\ApplicantDocumentLifecycle;
use App\Services\Documents\ApplicantDocumentStorage;
use App\Services\Payments\WiPayPaymentService;
use Database\Seeders\PilotDemoSeeder;
use Database\Seeders\ProgramSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\TestCase;

final class PrivacyRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('private');
        Storage::fake('public');
    }

    public function test_applicant_uploads_use_private_storage_and_replacement_and_removal_delete_files(): void
    {
        [$user, $jobSeeker] = $this->applicant('private-owner@example.test');

        $this->actingAs($user)
            ->post(route('jobseeker.profile.resume.upload'), [
                'resume' => UploadedFile::fake()->create('original-name.pdf', 40, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $resumePath = $jobSeeker->refresh()->resume_path;
        $this->assertStringStartsWith('applicants/'.$jobSeeker->id.'/profile/resume/', $resumePath);
        $this->assertStringNotContainsString('original-name', $resumePath);
        Storage::disk('private')->assertExists($resumePath);
        Storage::disk('public')->assertMissing($resumePath);
        $this->get('/storage/'.$resumePath)->assertNotFound();

        $this->actingAs($user)
            ->post(route('jobseeker.profile.resume.upload'), [
                'resume' => UploadedFile::fake()->create('invalid.exe', 10, 'application/octet-stream'),
            ])
            ->assertSessionHasErrors('resume');
        $this->assertSame($resumePath, $jobSeeker->refresh()->resume_path);
        Storage::disk('private')->assertExists($resumePath);

        $this->actingAs($user)
            ->post(route('jobseeker.documents.store'), [
                'document_type' => JobSeekerDocument::TYPE_PASSPORT,
                'file' => UploadedFile::fake()->create('passport.pdf', 50, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $document = JobSeekerDocument::query()->where('job_seeker_id', $jobSeeker->id)->firstOrFail();
        $firstPath = $document->file_path;
        Storage::disk('private')->assertExists($firstPath);

        $this->actingAs($user)
            ->post(route('jobseeker.documents.store'), [
                'document_type' => JobSeekerDocument::TYPE_PASSPORT,
                'file' => UploadedFile::fake()->create('replacement.pdf', 60, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $replacementPath = $document->refresh()->file_path;
        $this->assertNotSame($firstPath, $replacementPath);
        Storage::disk('private')->assertMissing($firstPath);
        Storage::disk('private')->assertExists($replacementPath);

        $this->actingAs($user)
            ->delete(route('jobseeker.documents.destroy', $document))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($document);
        Storage::disk('private')->assertMissing($replacementPath);
    }

    public function test_document_downloads_enforce_owner_relationship_type_and_admin_auditing(): void
    {
        [$owner, $jobSeeker] = $this->applicant('document-owner@example.test');
        [$other] = $this->applicant('other-applicant@example.test');
        [$relatedEmployer, $relatedEmployerModel] = $this->employer('related-employer@example.test');
        [$unrelatedEmployer] = $this->employer('unrelated-employer@example.test');
        $admin = $this->roleUser('admin', 'privacy-admin@example.test');
        $job = $this->job($relatedEmployerModel);
        $application = Application::create([
            'job_id' => $job->id,
            'job_seeker_id' => $jobSeeker->id,
            'status' => Application::STATUS_APPLIED,
            'applied_at' => now(),
            'submitted_resume_path' => 'applicants/'.$jobSeeker->id.'/applications/1/resume/resume.pdf',
            'submitted_cover_letter_path' => 'applicants/'.$jobSeeker->id.'/applications/1/cover-letter/letter.pdf',
        ]);
        Storage::disk('private')->put($application->submitted_resume_path, 'synthetic resume');
        Storage::disk('private')->put($application->submitted_cover_letter_path, 'synthetic letter');

        $certificate = $this->document($jobSeeker, JobSeekerDocument::TYPE_CERTIFICATE, 'certificate.pdf');
        $passport = $this->document($jobSeeker, JobSeekerDocument::TYPE_PASSPORT, 'passport.pdf');
        $policeRecord = $this->document($jobSeeker, JobSeekerDocument::TYPE_POLICE_RECORD, 'police.pdf');
        $medicalRecord = $this->document($jobSeeker, JobSeekerDocument::TYPE_MEDICAL_RECORD, 'medical.pdf');
        $driversLicense = $this->document($jobSeeker, JobSeekerDocument::TYPE_DRIVERS_LICENSE, 'license.pdf');

        $this->get(route('documents.job-seeker', $passport))->assertRedirect(route('login'));
        $this->actingAs($owner)
            ->get(route('documents.job-seeker', $passport))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->actingAs($other)->get(route('documents.job-seeker', $passport))->assertForbidden();

        $this->actingAs($relatedEmployer)->get(route('documents.job-seeker', $certificate))->assertOk();
        $this->actingAs($relatedEmployer)->get(route('documents.application', [$application, 'resume']))->assertOk();
        $this->actingAs($relatedEmployer)->get(route('documents.job-seeker', $passport))->assertForbidden();
        $this->actingAs($relatedEmployer)->get(route('documents.job-seeker', $policeRecord))->assertForbidden();
        $this->actingAs($relatedEmployer)->get(route('documents.job-seeker', $medicalRecord))->assertForbidden();
        $this->actingAs($relatedEmployer)->get(route('documents.job-seeker', $driversLicense))->assertForbidden();
        $this->actingAs($unrelatedEmployer)->get(route('documents.job-seeker', $certificate))->assertForbidden();
        $this->actingAs($unrelatedEmployer)->get(route('documents.application', [$application, 'resume']))->assertForbidden();

        $this->actingAs($admin)->get(route('documents.job-seeker', $passport))->assertOk();
        $audit = \App\Models\AuditLog::query()->latest('id')->firstOrFail();
        $this->assertSame('sensitive_document_downloaded', $audit->action);
        $this->assertSame(JobSeekerDocument::TYPE_PASSPORT, $audit->meta['document_type']);
        $this->assertArrayNotHasKey('file_path', $audit->meta);
    }

    public function test_direct_document_and_application_row_deletion_remove_unshared_private_files(): void
    {
        [, $jobSeeker] = $this->applicant('row-delete-owner@example.test');
        [, $employer] = $this->employer('row-delete-employer@example.test');
        $document = $this->document($jobSeeker, JobSeekerDocument::TYPE_CERTIFICATE, 'row-delete.pdf');
        $documentPath = $document->file_path;

        $document->delete();
        Storage::disk('private')->assertMissing($documentPath);

        $application = Application::create([
            'job_id' => $this->job($employer)->id,
            'job_seeker_id' => $jobSeeker->id,
            'status' => Application::STATUS_APPLIED,
            'applied_at' => now(),
            'submitted_resume_path' => 'applicants/'.$jobSeeker->id.'/applications/row-delete/resume.pdf',
            'submitted_cover_letter_path' => 'applicants/'.$jobSeeker->id.'/applications/row-delete/cover-letter.pdf',
        ]);
        Storage::disk('private')->put($application->submitted_resume_path, 'synthetic resume');
        Storage::disk('private')->put($application->submitted_cover_letter_path, 'synthetic letter');

        $resumePath = $application->submitted_resume_path;
        $coverLetterPath = $application->submitted_cover_letter_path;
        $application->delete();

        Storage::disk('private')->assertMissing($resumePath);
        Storage::disk('private')->assertMissing($coverLetterPath);

        $application = Application::create([
            'job_id' => $this->job($employer)->id,
            'job_seeker_id' => $jobSeeker->id,
            'status' => Application::STATUS_APPLIED,
            'applied_at' => now(),
        ]);
        $applicationFile = ApplicationFile::create([
            'application_id' => $application->id,
            'document_type' => 'supporting_document',
            'file_path' => 'applicants/'.$jobSeeker->id.'/applications/'.$application->id.'/files/supporting.pdf',
            'original_name' => 'supporting.pdf',
        ]);
        Storage::disk('private')->put($applicationFile->file_path, 'synthetic supporting document', 'private');
        $applicationFilePath = $applicationFile->file_path;

        $applicationFile->delete();
        Storage::disk('private')->assertMissing($applicationFilePath);
    }

    public function test_employer_never_sees_uploaded_only_documents_as_verified_or_cleared(): void
    {
        [, $jobSeeker] = $this->applicant('wording-owner@example.test');
        [$employerUser, $employer] = $this->employer('wording-employer@example.test');
        $this->grantAccess($employerUser, Entitlement::TYPE_EMPLOYER_POSTING_ACCESS);
        $application = Application::create([
            'job_id' => $this->job($employer)->id,
            'job_seeker_id' => $jobSeeker->id,
            'status' => Application::STATUS_APPLIED,
            'applied_at' => now(),
        ]);
        foreach ([JobSeekerDocument::TYPE_PASSPORT, JobSeekerDocument::TYPE_POLICE_RECORD, JobSeekerDocument::TYPE_MEDICAL_RECORD] as $type) {
            $this->document($jobSeeker, $type, $type.'.pdf');
        }

        $this->actingAs($employerUser)
            ->get(route('employer.applicants.show', $application))
            ->assertOk()
            ->assertDontSeeText($jobSeeker->date_of_birth?->format('M d, Y') ?? 'Date of Birth')
            ->assertDontSeeText('Identity Document Uploaded')
            ->assertDontSeeText('Police Record Uploaded')
            ->assertDontSeeText('Medical Document Uploaded')
            ->assertDontSeeText('Document Upload Status')
            ->assertDontSeeText('Identity Verified')
            ->assertDontSeeText('Background Check Complete')
            ->assertDontSeeText('Medical Clearance Complete')
            ->assertDontSeeText('Verified by Kairox Administration');
    }

    public function test_migration_dry_run_execute_shared_source_and_idempotent_rerun(): void
    {
        [, $jobSeeker] = $this->applicant('migration-owner@example.test');
        [, $employer] = $this->employer('migration-employer@example.test');
        $legacyPath = 'jobseekers/resumes/shared-synthetic.pdf';
        $jobSeeker->update(['resume_path' => $legacyPath]);
        $application = Application::create([
            'job_id' => $this->job($employer)->id,
            'job_seeker_id' => $jobSeeker->id,
            'status' => Application::STATUS_APPLIED,
            'applied_at' => now(),
            'submitted_resume_path' => $legacyPath,
        ]);
        Storage::disk('public')->put($legacyPath, 'synthetic shared resume');

        $this->artisan('kairox:migrate-private-documents', ['--dry-run' => true])
            ->assertSuccessful();
        $this->assertSame($legacyPath, $jobSeeker->refresh()->resume_path);
        Storage::disk('public')->assertExists($legacyPath);

        $this->artisan('kairox:migrate-private-documents', ['--execute' => true])
            ->assertSuccessful();

        $profilePath = $jobSeeker->refresh()->resume_path;
        $applicationPath = $application->refresh()->submitted_resume_path;
        $this->assertStringStartsWith('applicants/', $profilePath);
        $this->assertStringStartsWith('applicants/', $applicationPath);
        $this->assertNotSame($profilePath, $applicationPath);
        Storage::disk('private')->assertExists($profilePath);
        Storage::disk('private')->assertExists($applicationPath);
        Storage::disk('public')->assertMissing($legacyPath);
        $this->get('/storage/'.$legacyPath)->assertNotFound();

        $this->artisan('kairox:migrate-private-documents', ['--execute' => true])
            ->assertSuccessful();
        $this->assertSame($profilePath, $jobSeeker->refresh()->resume_path);
        $this->assertSame($applicationPath, $application->refresh()->submitted_resume_path);
    }

    public function test_migration_missing_source_is_reported_without_changing_the_reference(): void
    {
        [, $jobSeeker] = $this->applicant('missing-source@example.test');
        $jobSeeker->update(['resume_path' => 'jobseekers/resumes/missing-synthetic.pdf']);

        $this->artisan('kairox:migrate-private-documents', ['--dry-run' => true])
            ->expectsOutputToContain('missing_source')
            ->assertFailed();

        $this->assertSame('jobseekers/resumes/missing-synthetic.pdf', $jobSeeker->refresh()->resume_path);
        Storage::disk('private')->assertMissing('jobseekers/resumes/missing-synthetic.pdf');
    }

    public function test_application_database_failure_cleans_partial_files_and_logs_only_safe_context(): void
    {
        [$user, $jobSeeker] = $this->applicant('application-failure@example.test');
        [, $employer] = $this->employer('application-failure-employer@example.test');
        $this->grantAccess($user, Entitlement::TYPE_JOB_SEEKER_ACCESS);
        $job = $this->job($employer);
        $existingPath = 'applicants/'.$jobSeeker->id.'/profile/resume/existing.pdf';
        Storage::disk('private')->put($existingPath, 'existing valid resume', 'private');
        $jobSeeker->update(['resume_path' => $existingPath]);
        DB::unprepared("CREATE TRIGGER fail_application_document_update BEFORE UPDATE OF submitted_resume_path ON applications BEGIN SELECT RAISE(ABORT, 'synthetic database failure'); END");
        $handler = new TestHandler;
        Log::swap(new Logger('application-failure-test', [$handler]));

        $this->actingAs($user)
            ->post(route('jobseeker.jobs.apply.store', $job), [
                'cover_letter' => UploadedFile::fake()->create('new-letter.pdf', 20, 'application/pdf'),
            ])
            ->assertSessionHas('error', 'The application documents could not be stored securely. Please try again.');

        $this->assertDatabaseCount('applications', 0);
        $this->assertSame([$existingPath], Storage::disk('private')->allFiles());
        Storage::disk('private')->assertExists($existingPath);
        $logged = json_encode($handler->getRecords(), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('Secure application document storage failed', $logged);
        $this->assertStringNotContainsString('application-failure@example.test', $logged);
        $this->assertStringNotContainsString($existingPath, $logged);
        $this->assertStringNotContainsString('existing valid resume', $logged);
    }

    public function test_resume_replacement_database_rollback_preserves_old_file_and_removes_new_file(): void
    {
        [$user, $jobSeeker] = $this->applicant('resume-rollback@example.test');
        $oldPath = 'applicants/'.$jobSeeker->id.'/profile/resume/original.pdf';
        Storage::disk('private')->put($oldPath, 'original resume', 'private');
        $jobSeeker->update(['resume_path' => $oldPath]);
        DB::unprepared("CREATE TRIGGER fail_job_seeker_resume_update BEFORE UPDATE OF resume_path ON job_seekers BEGIN SELECT RAISE(ABORT, 'synthetic database failure'); END");

        $this->actingAs($user)
            ->post(route('jobseeker.profile.resume.upload'), [
                'resume' => UploadedFile::fake()->create('replacement.pdf', 20, 'application/pdf'),
            ])
            ->assertSessionHas('error', 'The existing resume could not be replaced safely.');

        $this->assertSame($oldPath, $jobSeeker->refresh()->resume_path);
        $this->assertSame([$oldPath], Storage::disk('private')->allFiles());
        Storage::disk('private')->assertExists($oldPath);
    }

    public function test_document_row_delete_database_rollback_preserves_row_and_file(): void
    {
        [, $jobSeeker] = $this->applicant('delete-rollback@example.test');
        $document = $this->document($jobSeeker, JobSeekerDocument::TYPE_PASSPORT, 'rollback.pdf');
        $path = $document->file_path;

        DB::beginTransaction();
        try {
            $document->delete();
            DB::statement('INSERT INTO missing_privacy_table (id) VALUES (1)');
            DB::commit();
            $this->fail('The synthetic database failure did not occur.');
        } catch (\Throwable) {
            DB::rollBack();
        }

        $this->assertDatabaseHas('job_seeker_documents', ['id' => $document->id]);
        Storage::disk('private')->assertExists($path);
    }

    public function test_cleanup_job_is_idempotent_and_preserves_shared_references(): void
    {
        [, $firstJobSeeker] = $this->applicant('shared-cleanup-one@example.test');
        [, $secondJobSeeker] = $this->applicant('shared-cleanup-two@example.test');
        $sharedPath = 'applicants/shared/legacy-resume.pdf';
        Storage::disk('private')->put($sharedPath, 'shared resume', 'private');
        $firstJobSeeker->update(['resume_path' => $sharedPath]);
        $secondJobSeeker->update(['resume_path' => $sharedPath]);
        $job = new DeleteUnreferencedApplicantDocument($sharedPath);
        $lifecycle = app(ApplicantDocumentLifecycle::class);

        $job->handle($lifecycle);
        Storage::disk('private')->assertExists($sharedPath);
        $firstJobSeeker->update(['resume_path' => null]);
        $job->handle($lifecycle);
        Storage::disk('private')->assertExists($sharedPath);
        $secondJobSeeker->update(['resume_path' => null]);
        $job->handle($lifecycle);
        $job->handle($lifecycle);
        Storage::disk('private')->assertMissing($sharedPath);
    }

    public function test_withdrawn_application_denies_employer_detail_and_download_but_preserves_applicant_and_admin_access(): void
    {
        [$owner, $jobSeeker] = $this->applicant('withdrawn-owner@example.test');
        [$employerUser, $employer] = $this->employer('withdrawn-employer@example.test');
        [$unrelatedEmployer] = $this->employer('withdrawn-unrelated@example.test');
        $admin = $this->roleUser('admin', 'withdrawn-admin@example.test');
        $this->grantAccess($employerUser, Entitlement::TYPE_EMPLOYER_POSTING_ACCESS);
        $this->grantAccess($unrelatedEmployer, Entitlement::TYPE_EMPLOYER_POSTING_ACCESS);
        $application = Application::create([
            'job_id' => $this->job($employer)->id,
            'job_seeker_id' => $jobSeeker->id,
            'status' => Application::STATUS_APPLIED,
            'applied_at' => now(),
            'submitted_resume_path' => 'applicants/'.$jobSeeker->id.'/applications/withdrawn/resume.pdf',
        ]);
        Storage::disk('private')->put($application->submitted_resume_path, 'synthetic resume', 'private');
        $certificate = $this->document($jobSeeker, JobSeekerDocument::TYPE_CERTIFICATE, 'certificate.pdf');

        $this->actingAs($employerUser)->get(route('employer.applicants.show', $application))->assertOk();
        $this->actingAs($unrelatedEmployer)->get(route('employer.applicants.show', $application))->assertForbidden();

        $application->update(['status' => Application::STATUS_WITHDRAWN]);

        $this->actingAs($employerUser)->get(route('employer.applicants.show', $application))->assertForbidden();
        $this->actingAs($employerUser)->get(route('documents.application', [$application, 'resume']))->assertForbidden();
        $this->actingAs($employerUser)->get(route('documents.job-seeker', $certificate))->assertForbidden();
        $this->actingAs($owner)->get(route('documents.application', [$application, 'resume']))->assertOk();
        $this->actingAs($admin)->get(route('documents.application', [$application, 'resume']))->assertOk();
    }

    public function test_private_writes_have_private_visibility_and_restrictive_local_modes(): void
    {
        $storage = app(ApplicantDocumentStorage::class);
        $path = $storage->store(
            UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf'),
            42,
            'profile/resume',
        );

        $this->assertSame('private', Storage::disk('private')->getVisibility($path));
        $absolutePath = Storage::disk('private')->path($path);
        $this->assertSame(0600, fileperms($absolutePath) & 0777);
        $this->assertSame(0700, fileperms(dirname($absolutePath)) & 0777);
        $this->assertSame(0700, fileperms(dirname(dirname($absolutePath))) & 0777);
        $this->assertSame(0700, fileperms(dirname(dirname(dirname($absolutePath)))) & 0777);
        $this->assertSame(0700, fileperms(dirname(dirname(dirname(dirname($absolutePath))))) & 0777);
        $this->assertSame(0700, fileperms(Storage::disk('private')->path('')) & 0777);
    }

    public function test_profile_photo_requires_real_image_and_download_name_uses_verified_type(): void
    {
        [$user, $jobSeeker] = $this->applicant('photo-security@example.test');

        $this->actingAs($user)
            ->post(route('jobseeker.documents.store'), [
                'document_type' => JobSeekerDocument::TYPE_PROFILE_PHOTO,
                'file' => UploadedFile::fake()->create('not-an-image.jpg', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('file');

        $this->actingAs($user)
            ->post(route('jobseeker.documents.store'), [
                'document_type' => JobSeekerDocument::TYPE_PROFILE_PHOTO,
                'file' => UploadedFile::fake()
                    ->createWithContent(
                        "avatar\r\nX-Injected: yes.exe",
                        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
                    )
                    ->mimeType('image/png'),
            ])
            ->assertSessionHasNoErrors();

        $photo = JobSeekerDocument::query()
            ->where('job_seeker_id', $jobSeeker->id)
            ->where('document_type', JobSeekerDocument::TYPE_PROFILE_PHOTO)
            ->firstOrFail();
        $this->assertStringEndsWith('.png', $photo->file_path);
        $response = $this->actingAs($user)->get(route('documents.job-seeker', $photo))->assertOk();
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
        $this->assertStringNotContainsString('.exe', $disposition);
        $this->assertStringContainsString('.png', $disposition);
    }

    public function test_pilot_demo_seeder_keeps_all_applicant_files_private(): void
    {
        $this->seed(ProgramSeeder::class);
        $this->seed(PilotDemoSeeder::class);

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertCount(4, Storage::disk('private')->allFiles());
        JobSeeker::query()->each(function (JobSeeker $jobSeeker): void {
            foreach ([$jobSeeker->resume_path, $jobSeeker->cover_letter_path] as $path) {
                $this->assertStringStartsWith('applicants/', $path);
                Storage::disk('private')->assertExists($path);
                Storage::disk('public')->assertMissing($path);
            }
        });
    }

    public function test_application_file_migration_honors_user_filter_and_private_visibility(): void
    {
        [$selectedUser, $selectedJobSeeker] = $this->applicant('migration-selected@example.test');
        [, $otherJobSeeker] = $this->applicant('migration-other@example.test');
        [, $employer] = $this->employer('migration-files-employer@example.test');
        $job = $this->job($employer);
        $selectedApplication = Application::create([
            'job_id' => $job->id,
            'job_seeker_id' => $selectedJobSeeker->id,
            'status' => Application::STATUS_APPLIED,
            'applied_at' => now(),
        ]);
        $otherApplication = Application::create([
            'job_id' => $job->id,
            'job_seeker_id' => $otherJobSeeker->id,
            'status' => Application::STATUS_APPLIED,
            'applied_at' => now(),
        ]);
        $selectedFile = ApplicationFile::create([
            'application_id' => $selectedApplication->id,
            'document_type' => 'supporting_document',
            'file_path' => 'applications/files/selected.pdf',
            'original_name' => 'selected.pdf',
        ]);
        $otherFile = ApplicationFile::create([
            'application_id' => $otherApplication->id,
            'document_type' => 'supporting_document',
            'file_path' => 'applications/files/other.pdf',
            'original_name' => 'other.pdf',
        ]);
        Storage::disk('public')->put($selectedFile->file_path, 'selected file');
        Storage::disk('public')->put($otherFile->file_path, 'other file');

        $this->artisan('kairox:migrate-private-documents', [
            '--execute' => true,
            '--user' => $selectedUser->id,
        ])->assertSuccessful();

        $selectedPath = $selectedFile->refresh()->file_path;
        $this->assertStringStartsWith('applicants/', $selectedPath);
        $this->assertSame('private', Storage::disk('private')->getVisibility($selectedPath));
        Storage::disk('public')->assertMissing('applications/files/selected.pdf');
        $this->assertSame('applications/files/other.pdf', $otherFile->refresh()->file_path);
        Storage::disk('public')->assertExists('applications/files/other.pdf');
    }

    public function test_migration_wrong_size_destination_is_replaced_safely(): void
    {
        [, $jobSeeker] = $this->applicant('migration-conflict@example.test');
        $source = 'jobseekers/resumes/conflict.pdf';
        $jobSeeker->update(['resume_path' => $source]);
        Storage::disk('public')->put($source, 'correct source');
        $destination = $this->migrationDestination(
            $jobSeeker,
            'resume_path',
            $source,
            'profile/resume',
            $jobSeeker->id,
        );
        Storage::disk('private')->put($destination, 'wrong-size-private-copy', 'private');

        $this->artisan('kairox:migrate-private-documents', ['--execute' => true])
            ->assertSuccessful();
        $this->assertSame($destination, $jobSeeker->refresh()->resume_path);
        $this->assertSame('correct source', Storage::disk('private')->get($destination));
        Storage::disk('private')->assertExists($destination);
        Storage::disk('public')->assertMissing($source);
    }

    public function test_migration_limit_processes_only_the_requested_number_of_references(): void
    {
        [, $jobSeeker] = $this->applicant('migration-limit@example.test');
        $resume = 'jobseekers/resumes/limited.pdf';
        $coverLetter = 'jobseekers/cover-letters/not-yet-migrated.pdf';
        $jobSeeker->update([
            'resume_path' => $resume,
            'cover_letter_path' => $coverLetter,
        ]);
        Storage::disk('public')->put($resume, 'resume');
        Storage::disk('public')->put($coverLetter, 'cover letter');

        $this->artisan('kairox:migrate-private-documents', [
            '--execute' => true,
            '--limit' => 1,
        ])->assertSuccessful();

        $this->assertStringStartsWith('applicants/', $jobSeeker->refresh()->resume_path);
        $this->assertSame($coverLetter, $jobSeeker->cover_letter_path);
        Storage::disk('public')->assertExists($coverLetter);
    }

    public function test_migration_audit_failure_preserves_public_source_and_database_reference(): void
    {
        [, $jobSeeker] = $this->applicant('migration-audit-failure@example.test');
        $source = 'jobseekers/resumes/audit-failure.pdf';
        $jobSeeker->update(['resume_path' => $source]);
        Storage::disk('public')->put($source, 'audit failure source');
        $destination = $this->migrationDestination(
            $jobSeeker,
            'resume_path',
            $source,
            'profile/resume',
            $jobSeeker->id,
        );
        DB::unprepared("CREATE TRIGGER fail_migration_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'synthetic audit failure'); END");

        $this->artisan('kairox:migrate-private-documents', ['--execute' => true])
            ->expectsOutputToContain('failed')
            ->assertFailed();

        $this->assertSame($source, $jobSeeker->refresh()->resume_path);
        Storage::disk('public')->assertExists($source);
        Storage::disk('private')->assertMissing($destination);
    }

    public function test_migration_commit_keeps_public_copy_until_retryable_cleanup_runs(): void
    {
        Queue::fake();
        [, $jobSeeker] = $this->applicant('migration-delayed-cleanup@example.test');
        $source = 'jobseekers/resumes/delayed-cleanup.pdf';
        $contents = 'delayed cleanup source';
        $jobSeeker->update(['resume_path' => $source]);
        Storage::disk('public')->put($source, $contents);

        $this->artisan('kairox:migrate-private-documents', ['--execute' => true])
            ->assertSuccessful();

        $destination = $jobSeeker->refresh()->resume_path;
        $this->assertStringStartsWith('applicants/', $destination);
        $this->assertSame($contents, Storage::disk('private')->get($destination));
        Storage::disk('public')->assertExists($source);
        Queue::assertPushed(
            DeleteUnreferencedApplicantDocument::class,
            fn (DeleteUnreferencedApplicantDocument $job) => $job->path === $source,
        );

        $publicDisk = Storage::disk('public');
        $failingPublicDisk = \Mockery::mock($publicDisk)->makePartial();
        $failingPublicDisk->shouldReceive('delete')->once()->with($source)->andReturn(false);
        Storage::set('public', $failingPublicDisk);

        try {
            (new DeleteUnreferencedApplicantDocument($source))->handle(app(ApplicantDocumentLifecycle::class));
            $this->fail('The synthetic public cleanup failure did not occur.');
        } catch (\RuntimeException $e) {
            $this->assertSame('An unreferenced applicant document could not be physically removed.', $e->getMessage());
        } finally {
            Storage::set('public', $publicDisk);
        }

        $this->assertSame($destination, $jobSeeker->refresh()->resume_path);
        Storage::disk('private')->assertExists($destination);
        Storage::disk('public')->assertExists($source);

        (new DeleteUnreferencedApplicantDocument($source))->handle(app(ApplicantDocumentLifecycle::class));
        Storage::disk('public')->assertMissing($source);
        Storage::disk('private')->assertExists($destination);
    }

    public function test_migration_replaces_same_size_corrupt_destination_after_sha256_mismatch(): void
    {
        [, $jobSeeker] = $this->applicant('migration-same-size-corrupt@example.test');
        $source = 'jobseekers/resumes/same-size.pdf';
        $sourceContents = 'correct-A';
        $corruptContents = 'corrupt-B';
        $this->assertSame(strlen($sourceContents), strlen($corruptContents));
        $jobSeeker->update(['resume_path' => $source]);
        Storage::disk('public')->put($source, $sourceContents);
        $destination = $this->migrationDestination(
            $jobSeeker,
            'resume_path',
            $source,
            'profile/resume',
            $jobSeeker->id,
        );
        Storage::disk('private')->put($destination, $corruptContents, 'private');

        $this->artisan('kairox:migrate-private-documents', ['--execute' => true])
            ->assertSuccessful();

        $this->assertSame($destination, $jobSeeker->refresh()->resume_path);
        $this->assertSame($sourceContents, Storage::disk('private')->get($destination));
        Storage::disk('public')->assertMissing($source);
    }

    public function test_migration_reuses_verified_interrupted_copy_and_accepts_completed_private_reference(): void
    {
        [, $jobSeeker] = $this->applicant('migration-interrupted-copy@example.test');
        $source = 'jobseekers/resumes/interrupted.pdf';
        $contents = 'verified interrupted copy';
        $jobSeeker->update(['resume_path' => $source]);
        Storage::disk('public')->put($source, $contents);
        $destination = $this->migrationDestination(
            $jobSeeker,
            'resume_path',
            $source,
            'profile/resume',
            $jobSeeker->id,
        );
        Storage::disk('private')->put($destination, $contents, 'private');

        $this->artisan('kairox:migrate-private-documents', ['--execute' => true])
            ->assertSuccessful();
        $this->assertSame($destination, $jobSeeker->refresh()->resume_path);
        Storage::disk('public')->assertMissing($source);

        $this->artisan('kairox:migrate-private-documents', ['--execute' => true])
            ->assertSuccessful();
        $this->assertSame($contents, Storage::disk('private')->get($destination));
    }

    public function test_migration_hash_read_failure_preserves_source_and_reference(): void
    {
        [, $jobSeeker] = $this->applicant('migration-read-failure@example.test');
        $source = 'jobseekers/resumes/unreadable.pdf';
        $jobSeeker->update(['resume_path' => $source]);
        Storage::disk('public')->makeDirectory($source);

        $this->artisan('kairox:migrate-private-documents', ['--execute' => true])
            ->expectsOutputToContain('failed')
            ->assertFailed();

        $this->assertSame($source, $jobSeeker->refresh()->resume_path);
        Storage::disk('public')->assertExists($source);
    }

    public function test_unauthenticated_wipay_post_callback_requires_verification_and_is_idempotent(): void
    {
        config()->set('services.wipay.api_key', 'callback-test-key');
        config()->set('services.wipay.account_number', 'callback-account');
        $user = User::factory()->create();
        $payment = Payment::create([
            'user_id' => $user->id,
            'gateway' => Payment::GATEWAY_WIPAY,
            'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'order_id' => 'KX-VALID-CALLBACK',
            'external_ref' => 'TX-VALID-100',
            'currency' => 'JMD',
            'amount' => 1000,
            'status' => Payment::STATUS_PENDING,
        ]);
        $transactionId = 'TX-VALID-100';
        $total = '1000.00';
        $hash = md5($transactionId.$total.'callback-test-key');
        $payload = [
            'order_id' => $payment->order_id,
            'transaction_id' => $transactionId,
            'total' => $total,
            'status' => 'success',
            'hash' => $hash,
        ];

        $this->post(route('payments.wipay.callback'), $payload)->assertOk();
        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->entitlement_activated_at);
        $activatedAt = $payment->entitlement_activated_at;
        $this->assertDatabaseCount('entitlements', 1);

        $this->post(route('payments.wipay.callback'), $payload)->assertOk();
        $this->assertDatabaseCount('entitlements', 1);
        $this->assertTrue($activatedAt->equalTo($payment->refresh()->entitlement_activated_at));

        $invalidPayment = Payment::create([
            'user_id' => $user->id,
            'gateway' => Payment::GATEWAY_WIPAY,
            'entitlement_type' => Entitlement::TYPE_EMPLOYER_POSTING_ACCESS,
            'order_id' => 'KX-INVALID-CALLBACK',
            'external_ref' => 'TX-INVALID-200',
            'currency' => 'JMD',
            'amount' => 2000,
            'status' => Payment::STATUS_PENDING,
        ]);
        $this->post(route('payments.wipay.callback'), [
            'order_id' => $invalidPayment->order_id,
            'transaction_id' => 'TX-INVALID-200',
            'total' => '2000.00',
            'status' => 'success',
            'hash' => 'invalid-hash',
        ])->assertUnprocessable();
        $this->assertSame(Payment::STATUS_PENDING, $invalidPayment->refresh()->status);
        $this->assertNull($invalidPayment->entitlement_activated_at);
        $this->assertNull($invalidPayment->raw_payload);
        $this->assertDatabaseCount('entitlements', 1);

        $this->get(route('payments.wipay.callback'))->assertMethodNotAllowed();
    }

    public function test_wipay_callback_cannot_be_rebound_or_mismatch_transaction_or_amount(): void
    {
        config()->set('services.wipay.api_key', 'binding-test-key');
        $user = User::factory()->create();
        $sourcePayment = Payment::create([
            'user_id' => $user->id,
            'gateway' => Payment::GATEWAY_WIPAY,
            'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'order_id' => 'KX-BINDING-SOURCE',
            'external_ref' => 'TX-BINDING-SOURCE',
            'currency' => 'JMD',
            'amount' => 1000,
            'status' => Payment::STATUS_PENDING,
        ]);
        $targetPayment = Payment::create([
            'user_id' => $user->id,
            'gateway' => Payment::GATEWAY_WIPAY,
            'entitlement_type' => Entitlement::TYPE_EMPLOYER_POSTING_ACCESS,
            'order_id' => 'KX-BINDING-TARGET',
            'external_ref' => 'TX-BINDING-TARGET',
            'currency' => 'JMD',
            'amount' => 1000,
            'status' => Payment::STATUS_PENDING,
        ]);
        $sourceHash = md5('TX-BINDING-SOURCE'.'1000.00'.'binding-test-key');

        $this->post(route('payments.wipay.callback'), [
            'order_id' => $targetPayment->order_id,
            'transaction_id' => $sourcePayment->external_ref,
            'total' => '1000.00',
            'status' => 'success',
            'hash' => $sourceHash,
        ])->assertUnprocessable();
        $this->assertSame(Payment::STATUS_PENDING, $targetPayment->refresh()->status);

        $otherTransaction = 'TX-BINDING-OTHER';
        $this->post(route('payments.wipay.callback'), [
            'order_id' => $targetPayment->order_id,
            'transaction_id' => $otherTransaction,
            'total' => '1000.00',
            'status' => 'success',
            'hash' => md5($otherTransaction.'1000.00'.'binding-test-key'),
        ])->assertUnprocessable();
        $this->assertSame(Payment::STATUS_PENDING, $targetPayment->refresh()->status);

        $this->post(route('payments.wipay.callback'), [
            'order_id' => $targetPayment->order_id,
            'transaction_id' => $targetPayment->external_ref,
            'total' => '999.00',
            'status' => 'success',
            'hash' => md5($targetPayment->external_ref.'999.00'.'binding-test-key'),
        ])->assertUnprocessable();
        $this->assertSame(Payment::STATUS_PENDING, $targetPayment->refresh()->status);

        $this->post(route('payments.wipay.callback'), [
            'order_id' => 'KX-UNKNOWN-ORDER',
            'transaction_id' => 'TX-UNKNOWN',
            'total' => '1000.00',
            'status' => 'success',
            'hash' => md5('TX-UNKNOWN'.'1000.00'.'binding-test-key'),
        ])->assertNotFound();

        $wrongGatewayPayment = Payment::create([
            'user_id' => $user->id,
            'gateway' => Payment::GATEWAY_STRIPE,
            'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'order_id' => 'KX-WRONG-GATEWAY',
            'external_ref' => 'TX-WRONG-GATEWAY',
            'currency' => 'JMD',
            'amount' => 1000,
            'status' => Payment::STATUS_PENDING,
        ]);
        $this->assertFalse(app(WiPayPaymentService::class)->verifyRedirect($wrongGatewayPayment, [
            'order_id' => $wrongGatewayPayment->order_id,
            'transaction_id' => $wrongGatewayPayment->external_ref,
            'total' => '1000.00',
            'status' => 'success',
            'hash' => md5($wrongGatewayPayment->external_ref.'1000.00'.'binding-test-key'),
        ]));
    }

    public function test_wipay_payment_transitions_are_monotonic_and_failed_payment_can_become_paid(): void
    {
        config()->set('services.wipay.api_key', 'transition-test-key');
        $user = User::factory()->create();
        $payment = Payment::create([
            'user_id' => $user->id,
            'gateway' => Payment::GATEWAY_WIPAY,
            'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'order_id' => 'KX-TRANSITION',
            'external_ref' => 'TX-TRANSITION',
            'currency' => 'JMD',
            'amount' => 1000,
            'status' => Payment::STATUS_FAILED,
        ]);
        $successPayload = [
            'order_id' => $payment->order_id,
            'transaction_id' => $payment->external_ref,
            'total' => '1000.00',
            'status' => 'success',
            'hash' => md5($payment->external_ref.'1000.00'.'transition-test-key'),
        ];

        $this->post(route('payments.wipay.callback'), $successPayload)->assertOk();
        $this->assertSame(Payment::STATUS_PAID, $payment->refresh()->status);
        $this->assertFalse($payment->canTransitionTo(Payment::STATUS_FAILED));
        $this->assertFalse($payment->canTransitionTo(Payment::STATUS_PENDING));

        $failurePayload = $successPayload;
        $failurePayload['status'] = 'failed';
        $this->post(route('payments.wipay.callback'), $failurePayload)->assertOk();
        $this->assertSame(Payment::STATUS_PAID, $payment->refresh()->status);

        $this->post(route('payments.wipay.callback'), $successPayload)->assertOk();
        $this->assertDatabaseCount('entitlements', 1);
    }

    public function test_paid_payment_retries_entitlement_activation_after_failure(): void
    {
        config()->set('services.wipay.api_key', 'activation-retry-key');
        $user = User::factory()->create();
        $payment = Payment::create([
            'user_id' => $user->id,
            'gateway' => Payment::GATEWAY_WIPAY,
            'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'order_id' => 'KX-ACTIVATION-RETRY',
            'external_ref' => 'TX-ACTIVATION-RETRY',
            'currency' => 'JMD',
            'amount' => 1000,
            'status' => Payment::STATUS_PENDING,
        ]);
        $payload = [
            'order_id' => $payment->order_id,
            'transaction_id' => $payment->external_ref,
            'total' => '1000.00',
            'status' => 'success',
            'hash' => md5($payment->external_ref.'1000.00'.'activation-retry-key'),
        ];
        DB::unprepared("CREATE TRIGGER fail_entitlement_activation BEFORE INSERT ON entitlements BEGIN SELECT RAISE(ABORT, 'synthetic entitlement failure'); END");

        $this->post(route('payments.wipay.callback'), $payload)->assertServerError();
        $this->assertSame(Payment::STATUS_PAID, $payment->refresh()->status);
        $this->assertNull($payment->entitlement_activated_at);
        $this->assertDatabaseCount('entitlements', 0);

        DB::unprepared('DROP TRIGGER fail_entitlement_activation');
        $this->post(route('payments.wipay.callback'), $payload)->assertOk();
        $this->assertNotNull($payment->refresh()->entitlement_activated_at);
        $this->assertDatabaseCount('entitlements', 1);

        $this->post(route('payments.wipay.callback'), $payload)->assertOk();
        $this->assertDatabaseCount('entitlements', 1);
    }

    public function test_wipay_logs_and_new_callback_persistence_exclude_sensitive_payload_values(): void
    {
        $user = User::factory()->create(['name' => 'Sensitive Customer', 'email' => 'sensitive@example.test']);
        $payment = Payment::create([
            'user_id' => $user->id,
            'gateway' => Payment::GATEWAY_WIPAY,
            'entitlement_type' => Entitlement::TYPE_JOB_SEEKER_ACCESS,
            'order_id' => 'KX-PRIVACY-TEST',
            'external_ref' => 'TX-PRIVACY-FAILED',
            'currency' => 'JMD',
            'amount' => 1000,
            'status' => Payment::STATUS_PENDING,
        ]);
        config()->set('services.wipay.base_url', 'https://wipay.example.test');
        config()->set('services.wipay.account_number', 'secret-account');
        config()->set('services.wipay.api_key', 'secret-api-key');
        Http::fake([
            '*' => Http::response([
                'url' => 'https://checkout.example.test/secret-token',
                'transaction_id' => 'TX-100',
                'message' => 'sensitive@example.test',
                'secret_echo' => 'secret-api-key',
            ]),
        ]);
        $handler = new TestHandler;
        Log::swap(new Logger('privacy-test', [$handler]));

        app(WiPayPaymentService::class)->createCheckoutSession($payment, $user, [
            'response_url' => 'https://portal.example.test/callback',
            'phone' => '+1 876 555 0199',
        ]);

        $logged = json_encode($handler->getRecords(), JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('KX-PRIVACY-TEST', $logged);
        foreach (['sensitive@example.test', 'Sensitive Customer', '+1 876 555 0199', 'secret-api-key', 'secret-account', 'secret-token'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $logged);
        }

        $callbackTransactionId = 'TX-PRIVACY-FAILED';
        $callbackTotal = '1000.00';
        $callbackHash = md5($callbackTransactionId.$callbackTotal.'secret-api-key');
        $this->post(route('payments.wipay.callback'), [
            'order_id' => $payment->order_id,
            'transaction_id' => $callbackTransactionId,
            'total' => $callbackTotal,
            'status' => 'failed',
            'email' => 'callback-sensitive@example.test',
            'phone' => '+1 876 555 0188',
            'hash' => $callbackHash,
            'debug_hash' => 'raw-secret-hash',
        ])->assertOk();

        $persisted = json_encode($payment->refresh()->raw_payload, JSON_THROW_ON_ERROR);
        foreach (['callback-sensitive@example.test', '+1 876 555 0188', 'raw-secret-hash', $callbackHash] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $persisted);
        }
        $this->assertStringContainsString('failed', $persisted);

        $callbackLogs = json_encode($handler->getRecords(), JSON_THROW_ON_ERROR);
        foreach (['callback-sensitive@example.test', '+1 876 555 0188', 'raw-secret-hash', $callbackHash] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $callbackLogs);
        }
    }

    public function test_payment_assistance_admin_email_uses_secure_portal_instead_of_contact_details(): void
    {
        $request = new PaymentAssistanceRequest([
            'full_name' => 'Synthetic Applicant',
            'email' => 'private@example.test',
            'phone' => '+1 876 555 0177',
            'whatsapp' => '+1 876 555 0166',
            'program_name' => 'Synthetic Programme',
            'currency' => 'JMD',
            'amount' => 5000,
            'message' => 'Sensitive free-form message',
        ]);
        $request->created_at = now();
        $mail = new PaymentAssistanceAdminMail($request);
        $html = $mail->render();

        $this->assertStringContainsString('Review securely in Admin', $html);
        $this->assertStringContainsString('Synthetic Applicant', $html);
        $this->assertStringContainsString('Synthetic Programme', $html);
        foreach (['private@example.test', '+1 876 555 0177', '+1 876 555 0166', 'Sensitive free-form message'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $html);
        }
        $this->assertSame([], $mail->attachments);
        $this->assertSame([], $mail->rawAttachments);
        $this->assertSame([], $mail->diskAttachments);
    }

    /** @return array{User, JobSeeker} */
    private function applicant(string $email): array
    {
        $user = $this->roleUser('job_seeker', $email);

        return [$user, JobSeeker::create(['user_id' => $user->id])];
    }

    /** @return array{User, Employer} */
    private function employer(string $email): array
    {
        $user = $this->roleUser('employer', $email);

        return [$user, Employer::create(['user_id' => $user->id, 'company_name' => 'Synthetic Sponsor'])];
    }

    private function roleUser(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email, 'must_change_password' => false]);
        $user->assignRole($role);

        return $user;
    }

    private function job(Employer $employer): Job
    {
        return Job::create([
            'employer_id' => $employer->id,
            'title' => 'Synthetic Role '.$employer->id,
            'description' => 'Synthetic test opportunity.',
            'status' => Job::STATUS_PUBLISHED,
            'is_approved' => true,
            'application_deadline' => now()->addMonth(),
        ]);
    }

    private function document(JobSeeker $jobSeeker, string $type, string $filename): JobSeekerDocument
    {
        $path = 'applicants/'.$jobSeeker->id.'/documents/'.$type.'/'.$filename;
        Storage::disk('private')->put($path, 'synthetic document');

        return JobSeekerDocument::create([
            'job_seeker_id' => $jobSeeker->id,
            'document_type' => $type,
            'file_path' => $path,
            'original_name' => $filename,
            'uploaded_at' => now(),
        ]);
    }

    private function migrationDestination(
        object $model,
        string $field,
        string $source,
        string $directory,
        int $jobSeekerId,
    ): string {
        $extension = strtolower((string) pathinfo($source, PATHINFO_EXTENSION));
        $fingerprint = hash('sha256', $model::class.'|'.$model->getKey().'|'.$field.'|'.$source);

        return sprintf(
            'applicants/%d/%s/%s%s',
            $jobSeekerId,
            trim($directory, '/'),
            $fingerprint,
            $extension !== '' ? '.'.$extension : '',
        );
    }

    private function grantAccess(User $user, string $type): void
    {
        Entitlement::create([
            'user_id' => $user->id,
            'type' => $type,
            'status' => Entitlement::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'source' => 'privacy-test',
        ]);
    }
}
