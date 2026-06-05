<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_assistance_requests', function (Blueprint $table) {
            $table->string('type')->default('payment_assistance')->after('id');
            $table->string('subject')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('payment_assistance_requests', function (Blueprint $table) {
            $table->dropColumn(['type', 'subject']);
        });
    }
};
