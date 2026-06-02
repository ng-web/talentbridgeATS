<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Always run — required for the app to function
        $this->call([
            RolesAndPermissionsSeeder::class,
            ReferenceDataSeeder::class,
            ProgramSeeder::class,
            PlanSeeder::class,
        ]);

        // Local/staging only — never run on production
        if (app()->environment(['local', 'staging'])) {
            $this->call([
                PilotDemoSeeder::class,
            ]);
        }
    }
}
