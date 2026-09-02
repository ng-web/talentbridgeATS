<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_exports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('privacy_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('generation_initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 50);
            $table->json('scope_manifest');
            $table->char('authorized_scope_hash', 64);
            $table->char('artifact_scope_hash', 64)->nullable();
            $table->string('private_path', 1024)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->uuid('generation_token')->nullable()->unique();
            $table->unsignedInteger('generation_attempt')->default(0);
            $table->timestamp('generation_started_at')->nullable();
            $table->timestamp('authorized_at');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['status', 'generation_started_at']);
            $table->index(['subject_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_exports');
    }
};
