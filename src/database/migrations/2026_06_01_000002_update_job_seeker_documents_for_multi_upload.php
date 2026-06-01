<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add a plain index on job_seeker_id first so MySQL keeps FK coverage
        // when we drop the composite unique index
        Schema::table('job_seeker_documents', function (Blueprint $table) {
            $table->index('job_seeker_id');
        });

        Schema::table('job_seeker_documents', function (Blueprint $table) {
            $table->dropUnique(['job_seeker_id', 'document_type']);
            $table->string('original_name')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('job_seeker_documents', function (Blueprint $table) {
            $table->dropColumn('original_name');
            $table->unique(['job_seeker_id', 'document_type']);
            $table->dropIndex(['job_seeker_id']);
        });
    }
};
