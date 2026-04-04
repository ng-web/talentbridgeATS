<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('submitted_resume_path')->nullable()->after('status');
            $table->string('submitted_cover_letter_path')->nullable()->after('submitted_resume_path');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn([
                'submitted_resume_path',
                'submitted_cover_letter_path',
            ]);
        });
    }
};