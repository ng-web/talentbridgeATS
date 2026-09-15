<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_incidents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('creation_key')->unique();
            $table->string('status', 24);
            $table->string('technical_severity', 16);
            $table->string('classification_code', 64);
            $table->text('technical_summary');
            $table->timestamp('detected_at');
            $table->timestamp('opened_at');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('escalation_status', 24)->default('not_escalated');
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('escalated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('contained_at')->nullable();
            $table->timestamp('recovery_started_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamp('review_required_at')->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['status', 'technical_severity'], 'inc_status_severity_idx');
            $table->index(['owner_user_id', 'status'], 'inc_owner_status_idx');
            $table->index('detected_at', 'inc_detected_idx');
            $table->index('escalation_status', 'inc_escalation_idx');
        });

        Schema::create('privacy_incident_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('privacy_incident_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('event_type', 64);
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('actor_security_version');
            $table->uuid('idempotency_key');
            $table->timestamp('occurred_at');
            $table->json('safe_metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['privacy_incident_id', 'sequence'], 'inc_event_sequence_uq');
            $table->unique(['privacy_incident_id', 'idempotency_key'], 'inc_event_idempotency_uq');
        });

        Schema::create('privacy_incident_scope_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('privacy_incident_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('data_categories');
            $table->json('system_codes');
            $table->unsignedBigInteger('potentially_affected_subject_count')->nullable();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('actor_security_version');
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->nullable();
            $table->unique(['privacy_incident_id', 'version'], 'inc_scope_version_uq');
        });

        Schema::create('privacy_incident_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('privacy_incident_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('breach_assessment', 40);
            $table->string('authority_notification_decision', 24);
            $table->string('subject_notification_decision', 24);
            $table->string('reason_code', 64);
            $table->text('rationale')->nullable();
            $table->foreignId('decided_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('actor_security_version');
            $table->timestamp('decided_at');
            $table->timestamp('created_at')->nullable();
            $table->unique(['privacy_incident_id', 'version'], 'inc_decision_version_uq');
        });

        Schema::create('privacy_incident_holds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('privacy_incident_id')->constrained()->restrictOnDelete();
            $table->foreignId('legal_hold_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->nullable();
            $table->unique(['privacy_incident_id', 'legal_hold_id'], 'inc_hold_link_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_incident_holds');
        Schema::dropIfExists('privacy_incident_decisions');
        Schema::dropIfExists('privacy_incident_scope_versions');
        Schema::dropIfExists('privacy_incident_events');
        Schema::dropIfExists('privacy_incidents');
    }
};
