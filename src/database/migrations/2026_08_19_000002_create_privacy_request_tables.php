<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('subject_user_id')->constrained('users')->restrictOnDelete();
            $table->string('request_type', 50);
            $table->string('state', 50);
            $table->string('identity_verification_state', 50);
            $table->string('identity_verification_method', 100)->nullable();
            $table->foreignId('identity_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('decision_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('controller_decision_code', 100)->nullable();
            $table->string('closure_reason_code', 100)->nullable();
            $table->timestamps();

            $table->index(['subject_user_id', 'submitted_at']);
            $table->index(['state', 'submitted_at']);
        });

        Schema::create('privacy_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('privacy_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 100);
            $table->string('from_state', 50)->nullable();
            $table->string('to_state', 50)->nullable();
            $table->string('reason_code', 100)->nullable();
            $table->json('safe_metadata')->nullable();
            $table->timestamps();

            $table->index(['privacy_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_request_events');
        Schema::dropIfExists('privacy_requests');
    }
};
