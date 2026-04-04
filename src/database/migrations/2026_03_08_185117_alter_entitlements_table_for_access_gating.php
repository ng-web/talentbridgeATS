<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entitlements', function (Blueprint $table): void {
            if (!Schema::hasColumn('entitlements', 'starts_at')) {
                $table->timestamp('starts_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('entitlements', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('starts_at');
            }

            if (!Schema::hasColumn('entitlements', 'source')) {
                $table->string('source')->nullable()->after('expires_at');
            }

            if (!Schema::hasColumn('entitlements', 'notes')) {
                $table->text('notes')->nullable()->after('source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entitlements', function (Blueprint $table): void {
            $columnsToDrop = [];

            foreach (['starts_at', 'expires_at', 'source', 'notes'] as $column) {
                if (Schema::hasColumn('entitlements', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};