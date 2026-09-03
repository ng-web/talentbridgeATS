<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Security\PrivacyAuditService;
use App\Support\PrivacySecurityPermissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class GrantRetentionManager extends Command
{
    protected $signature = 'privacy:grant-retention-manager {user_id : Numeric ID of the specifically approved Kairox administrator}';

    protected $description = 'Atomically grant and audit the retention/hold/disposition permission bundle';

    public function __construct(private readonly PrivacyAuditService $audit)
    {
        parent::__construct();
    }

    public function handle(PermissionRegistrar $registrar): int
    {
        $id = filter_var($this->argument('user_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $user = $id ? User::query()->find($id) : null;
        if (! $user || ! $user->hasRole('admin')) {
            $this->error('The approved active user is not an administrator.');

            return self::FAILURE;
        }
        $bundle = PrivacySecurityPermissions::retentionManager();
        if (array_diff($bundle, Permission::query()->where('guard_name', 'web')->whereIn('name', $bundle)->pluck('name')->all()) !== []) {
            $this->error('The retention permission bundle is unavailable. Run migrations first.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($user, $bundle): void {
            foreach ($bundle as $permission) {
                if (! $user->hasDirectPermission($permission)) {
                    $user->givePermissionTo($permission);
                    $this->audit->record('admin_permission_changed', resource: $user, subjectUserId: $user->id, reasonCode: 'approved_retention_manager_bootstrap', metadata: ['permission' => $permission, 'change' => 'granted']);
                }
            }
        });
        $registrar->forgetCachedPermissions();
        $this->info('Retention-manager permissions granted and audited.');

        return self::SUCCESS;
    }
}
