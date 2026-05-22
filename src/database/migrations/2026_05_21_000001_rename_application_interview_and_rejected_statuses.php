<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('applications')->where('status', 'interview')->update(['status' => 'shortlisted']);
        DB::table('applications')->where('status', 'rejected')->update(['status' => 'not_selected']);
    }

    public function down(): void
    {
        DB::table('applications')->where('status', 'shortlisted')->update(['status' => 'interview']);
        DB::table('applications')->where('status', 'not_selected')->update(['status' => 'rejected']);
    }
};
