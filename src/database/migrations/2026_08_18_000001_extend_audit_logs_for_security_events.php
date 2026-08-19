<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreignId('subject_user_id')->nullable()->after('actor_user_id')->constrained('users')->nullOnDelete();
            $table->uuid('correlation_id')->nullable()->after('entity_id')->index();
            $table->string('outcome', 32)->nullable()->after('correlation_id')->index();
            $table->string('reason_code', 100)->nullable()->after('outcome');
            $table->timestamp('occurred_at')->nullable()->after('reason_code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subject_user_id');
            $table->dropIndex(['correlation_id']);
            $table->dropIndex(['outcome']);
            $table->dropIndex(['occurred_at']);
            $table->dropColumn(['correlation_id', 'outcome', 'reason_code', 'occurred_at']);
        });
    }
};
