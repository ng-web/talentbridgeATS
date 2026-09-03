<?php

use App\Support\PrivacySecurityPermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (PrivacySecurityPermissions::introducedInPass3() as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', PrivacySecurityPermissions::introducedInPass3())->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
