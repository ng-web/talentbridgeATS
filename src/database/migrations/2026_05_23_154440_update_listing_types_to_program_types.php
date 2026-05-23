<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('jobs')->where('listing_type', 'job')->update(['listing_type' => 'summer_work_travel']);
        DB::table('jobs')->where('listing_type', 'work_study')->update(['listing_type' => 'internship_abroad']);
    }

    public function down(): void
    {
        DB::table('jobs')->where('listing_type', 'summer_work_travel')->update(['listing_type' => 'job']);
        DB::table('jobs')->where('listing_type', 'internship_abroad')->update(['listing_type' => 'work_study']);
    }
};
