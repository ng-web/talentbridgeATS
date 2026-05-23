<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('applications')->where('status', 'approved')->update(['status' => 'shortlisted']);
    }

    public function down(): void
    {
        // No reverse — approved is removed from the domain
    }
};
