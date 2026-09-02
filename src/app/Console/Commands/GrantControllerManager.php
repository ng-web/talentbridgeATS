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

final class GrantControllerManager extends Command
{
    protected $signature = 'privacy:grant-controller-manager {user_id : Numeric ID of the specifically approved Kairox administrator}';

    protected $description = 'Atomically grant and audit the Kairox controller-manager privacy permission bundle';

    public function __construct(private readonly PrivacyAuditService $audit)
    {
        parent::__construct();
    }

    public function handle(PermissionRegistrar $registrar): int
    {
        $userId = filter_var($this->argument('user_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($userId === false) {
            $this->error('A single positive numeric administrator ID is required.');

            return self::FAILURE;
        }

        $user = User::query()->find($userId);

        if (! $user || ! $user->hasRole('admin')) {
            $this->error('The approved active user is not an administrator.');

            return self::FAILURE;
        }

        $bundle = PrivacySecurityPermissions::controllerManager();
        $available = Permission::query()->where('guard_name', 'web')->whereIn('name', $bundle)->pluck('name')->all();

        if (array_diff($bundle, $available) !== []) {
            $this->error('The controller-manager permission bundle is unavailable. Run migrations first.');

            return self::FAILURE;
        }

        $missing = array_values(array_filter($bundle, fn (string $permission): bool => ! $user->hasDirectPermission($permission)));

        if ($missing === []) {
            $registrar->forgetCachedPermissions();
            $this->info('Controller-manager permissions are already granted.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($user, $missing): void {
                foreach ($missing as $permission) {
                    $user->givePermissionTo($permission);
                    $this->audit->record(
                        event: 'admin_permission_changed',
                        resource: $user,
                        subjectUserId: $user->id,
                        reasonCode: 'approved_controller_manager_bootstrap',
                        metadata: ['permission' => $permission, 'change' => 'granted'],
                    );
                }
            });
        } catch (Throwable) {
            $this->error('Controller-manager permissions could not be granted atomically.');

            return self::FAILURE;
        }

        $registrar->forgetCachedPermissions();
        $fresh = $user->fresh();

        foreach ($bundle as $permission) {
            if (! $fresh->hasDirectPermission($permission)) {
                $this->error('Controller-manager permission verification failed.');

                return self::FAILURE;
            }
        }

        $this->info('Controller-manager permissions granted and audited.');

        return self::SUCCESS;
    }
}
