<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_seeker_documents', function (Blueprint $table): void {
            $table->uuid('artifact_revision')->nullable()->unique()->after('file_path');
        });

        DB::table('job_seeker_documents')->whereNull('artifact_revision')->orderBy('id')->chunkById(200, function ($documents): void {
            foreach ($documents as $document) {
                DB::table('job_seeker_documents')->where('id', $document->id)->update(['artifact_revision' => (string) Str::uuid()]);
            }
        });

        Schema::create('retention_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('data_category', 80);
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft');
            $table->string('trigger_type', 50);
            $table->unsignedInteger('retention_value');
            $table->string('retention_unit', 20);
            $table->string('disposition_method', 50);
            $table->timestamp('effective_at');
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->char('canonical_configuration_hash', 64);
            $table->timestamps();

            $table->unique(['data_category', 'version']);
            $table->index(['data_category', 'status', 'effective_at', 'retired_at'], 'retention_rules_governing_idx');
        });

        Schema::create('retention_registry_locks', function (Blueprint $table): void {
            $table->string('data_category', 80)->primary();
            $table->timestamps();
        });

        DB::table('retention_registry_locks')->insert(array_map(
            fn (string $category): array => ['data_category' => $category, 'created_at' => now(), 'updated_at' => now()],
            [
                'applicant_account', 'applicant_profile', 'applicant_document', 'application',
                'application_file', 'payment', 'entitlement', 'assistance_request',
                'notification', 'privacy_request', 'privacy_export', 'policy_acknowledgement',
                'sensitive_processing_evidence', 'security_audit',
            ],
        ));

        Schema::create('retention_subject_locks', function (Blueprint $table): void {
            $table->foreignId('subject_user_id')->primary()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('legal_holds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('subject_user_id')->constrained('users')->restrictOnDelete();
            $table->string('scope_type', 20);
            $table->string('data_category', 80)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('hold_code', 50);
            $table->string('status', 20)->default('active');
            $table->timestamp('effective_at');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->uuid('correlation_id');
            $table->timestamps();

            $table->index(['subject_user_id', 'status', 'effective_at', 'released_at'], 'legal_holds_subject_active_idx');
            $table->index(['data_category', 'resource_id', 'status'], 'legal_holds_resource_idx');
        });

        Schema::create('disposition_plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('retention_rule_id')->constrained()->restrictOnDelete();
            $table->string('data_category', 80);
            $table->unsignedInteger('retention_rule_version');
            $table->char('retention_rule_hash', 64);
            $table->string('status', 30)->default('planned');
            $table->timestamp('planned_at');
            $table->timestamp('expires_at');
            $table->foreignId('planned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('authorized_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->unsignedInteger('authorizer_security_version')->nullable();
            $table->uuid('execution_token')->nullable();
            $table->timestamp('execution_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('disposition_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('disposition_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('resource_id');
            $table->string('status', 30)->default('planned');
            $table->string('trigger_type', 50);
            $table->timestamp('triggered_at');
            $table->timestamp('eligible_at');
            $table->string('disposition_method', 50);
            $table->uuid('artifact_revision');
            $table->char('artifact_fingerprint', 64);
            $table->char('eligibility_snapshot_hash', 64);
            $table->string('outcome_code', 50)->nullable();
            $table->string('file_cleanup_status', 20)->nullable();
            $table->text('cleanup_path')->nullable();
            $table->timestamp('disposed_at')->nullable();
            $table->timestamps();

            $table->unique(['disposition_plan_id', 'resource_id']);
            $table->index(['status', 'subject_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposition_plan_items');
        Schema::dropIfExists('disposition_plans');
        Schema::dropIfExists('legal_holds');
        Schema::dropIfExists('retention_subject_locks');
        Schema::dropIfExists('retention_registry_locks');
        Schema::dropIfExists('retention_rules');

        Schema::table('job_seeker_documents', function (Blueprint $table): void {
            $table->dropUnique(['artifact_revision']);
            $table->dropColumn('artifact_revision');
        });
    }
};
