<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Security\PrivacyAuditService;
use App\Support\PrivacySecurityPermissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

final class GrantSecurityManager extends Command
{
    protected $signature = 'security:grant-manager {user_id : Numeric ID of the specifically approved administrator}';

    protected $description = 'Atomically grant and audit the initial administrator security-manager permission';

    public function __construct(
        private readonly PrivacyAuditService $audit,
    ) {
        parent::__construct();
    }

    public function handle(PermissionRegistrar $permissions): int
    {
        $userId = filter_var($this->argument('user_id'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($userId === false) {
            $this->error('A single positive numeric administrator ID is required.');

            return self::FAILURE;
        }

        $user = User::query()->find($userId);

        if (! $user || ! $user->hasRole('admin')) {
            $this->error('The approved active user is not an administrator.');

            return self::FAILURE;
        }

        if (! Permission::query()
            ->where('name', PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE)
            ->where('guard_name', 'web')
            ->exists()) {
            $this->error('The security-manager permission is unavailable. Run migrations first.');

            return self::FAILURE;
        }

        if ($user->hasDirectPermission(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE)) {
            $permissions->forgetCachedPermissions();
            $this->info('Security-manager permission is already granted.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($user): void {
                $user->givePermissionTo(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE);

                $this->audit->record(
                    event: 'admin_permission_changed',
                    resource: $user,
                    subjectUserId: $user->id,
                    reasonCode: 'approved_initial_bootstrap',
                    metadata: [
                        'permission' => PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE,
                        'change' => 'granted',
                    ],
                );
            });
        } catch (Throwable) {
            $this->error('Security-manager permission could not be granted atomically.');

            return self::FAILURE;
        }

        $permissions->forgetCachedPermissions();

        if (! $user->fresh()->hasDirectPermission(PrivacySecurityPermissions::ADMIN_SECURITY_MANAGE)) {
            $this->error('Security-manager permission verification failed.');

            return self::FAILURE;
        }

        $this->info('Security-manager permission granted and audited.');

        return self::SUCCESS;
    }
}
