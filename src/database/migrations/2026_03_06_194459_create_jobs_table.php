<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('description');

            $table->string('listing_type')->default('job');
            $table->string('category')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('location')->nullable();
            $table->string('country')->nullable();

            $table->unsignedBigInteger('salary_min')->nullable();
            $table->unsignedBigInteger('salary_max')->nullable();
            $table->boolean('remote_flag')->default(false);

            $table->string('duration')->nullable();
            $table->text('eligibility')->nullable();
            $table->unsignedBigInteger('fees')->nullable();

            $table->date('application_deadline')->nullable();
            $table->string('status')->default('draft');
            $table->date('expiry_date')->nullable();

            $table->boolean('featured_flag')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
