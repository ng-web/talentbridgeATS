<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employers', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->after('contact_person');
            $table->string('phone_company')->nullable()->after('contact_email');
            $table->string('phone_direct')->nullable()->after('phone_company');
        });
    }

    public function down(): void
    {
        Schema::table('employers', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'phone_company', 'phone_direct']);
        });
    }
};
