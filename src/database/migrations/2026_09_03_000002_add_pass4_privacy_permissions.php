<?php

use App\Support\PrivacySecurityPermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (PrivacySecurityPermissions::introducedInPass4() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $newPermissions = array_values(array_diff(
            PrivacySecurityPermissions::introducedInPass4(),
            [PrivacySecurityPermissions::INCIDENTS_MANAGE], // Pass 1 placeholder; this migration does not own it.
        ));
        Permission::query()->where('guard_name', 'web')->whereIn('name', $newPermissions)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
