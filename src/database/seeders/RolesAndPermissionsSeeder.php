<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $employer = Role::firstOrCreate(['name' => 'employer']);
        $jobSeeker = Role::firstOrCreate(['name' => 'job_seeker']);

        $permissions = [
            'admin.panel',

            'programs.view',

            'jobs.view',
            'jobs.create',
            'jobs.update_own',
            'jobs.view_own',

            'applications.create',
            'applications.view_own',
            'applications.manage_own_jobs',

            'profile.update_own',
            'files.upload_own',

            'payments.view_own',
            'entitlements.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin->syncPermissions(Permission::all());

        $employer->syncPermissions([
            'jobs.create',
            'jobs.update_own',
            'jobs.view_own',
            'applications.manage_own_jobs',
            'profile.update_own',
            'files.upload_own',
            'payments.view_own',
        ]);

        $jobSeeker->syncPermissions([
            'programs.view',
            'jobs.view',
            'applications.create',
            'applications.view_own',
            'profile.update_own',
            'files.upload_own',
            'payments.view_own',
        ]);
    }
}