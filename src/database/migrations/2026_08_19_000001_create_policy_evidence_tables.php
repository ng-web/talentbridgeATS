<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 50);
            $table->string('version', 50);
            $table->string('title');
            $table->string('content_reference', 2048);
            $table->char('canonical_reference_hash', 64);
            $table->timestamp('effective_at');
            $table->timestamp('retired_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['type', 'version']);
            $table->index(['type', 'is_active']);
        });

        Schema::create('policy_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('policy_document_id')->constrained()->restrictOnDelete();
            $table->string('acknowledgement_type', 50);
            $table->string('source_context', 100);
            $table->timestamp('acknowledged_at');
            $table->timestamps();

            $table->unique(
                ['user_id', 'policy_document_id', 'acknowledgement_type'],
                'policy_ack_user_doc_type_unique',
            );
        });

        Schema::create('sensitive_processing_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('category', 50);
            $table->string('purpose_code', 100);
            $table->foreignId('policy_document_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('evidence_type', 50);
            $table->timestamp('recorded_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('source_context', 100);
            $table->string('controller_reference', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensitive_processing_evidence');
        Schema::dropIfExists('policy_acknowledgements');
        Schema::dropIfExists('policy_documents');
    }
};
