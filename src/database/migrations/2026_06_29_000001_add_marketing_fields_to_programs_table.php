<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('age_range')->nullable()->after('slug');
            $table->json('benefits')->nullable()->after('description');
            $table->text('typical_roles')->nullable()->after('benefits');
            $table->text('fields_available')->nullable()->after('typical_roles');
            $table->unsignedInteger('display_order')->default(0)->after('fields_available');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn([
                'age_range',
                'benefits',
                'typical_roles',
                'fields_available',
                'display_order',
            ]);
        });
    }
};
