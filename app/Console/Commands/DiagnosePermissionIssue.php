<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DiagnosePermissionIssue extends Command
{
    protected $signature = 'diagnose:permissions {email}';
    protected $description = 'Diagnose permission loading issue';

    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User not found: {$email}");
            return;
        }

        $this->info("=== PERMISSION DIAGNOSIS ===");
        $this->info("User: {$user->first_name} {$user->last_name} ({$user->email})");

        // 1. Check user roles in database
        $this->info("\n=== DATABASE ROLES ===");
        $userRoles = $user->roles()->get();
        $this->info("User roles count: " . $userRoles->count());
        foreach ($userRoles as $role) {
            $this->info("- {$role->name} (ID: {$role->id})");
        }

        // 2. Check Admin role permissions in database
        $this->info("\n=== ADMIN ROLE PERMISSIONS IN DATABASE ===");
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $adminPermissions = $adminRole->permissions()->get();
            $this->info("Admin role permissions count: " . $adminPermissions->count());
            foreach ($adminPermissions->take(10) as $permission) {
                $this->info("- {$permission->name}");
            }
            if ($adminPermissions->count() > 10) {
                $this->info("... and " . ($adminPermissions->count() - 10) . " more");
            }
        }

        // 3. Check user's direct permissions
        $this->info("\n=== USER DIRECT PERMISSIONS ===");
        $directPermissions = $user->getDirectPermissions();
        $this->info("Direct permissions count: " . $directPermissions->count());
        foreach ($directPermissions as $permission) {
            $this->info("- {$permission->name}");
        }

        // 4. Check user's role-based permissions
        $this->info("\n=== USER ROLE-BASED PERMISSIONS ===");
        $rolePermissions = $user->getPermissionsViaRoles();
        $this->info("Role-based permissions count: " . $rolePermissions->count());
        foreach ($rolePermissions->take(10) as $permission) {
            $this->info("- {$permission->name}");
        }
        if ($rolePermissions->count() > 10) {
            $this->info("... and " . ($rolePermissions->count() - 10) . " more");
        }

        // 5. Check user's all permissions
        $this->info("\n=== USER ALL PERMISSIONS ===");
        $allPermissions = $user->getAllPermissions();
        $this->info("All permissions count: " . $allPermissions->count());
        foreach ($allPermissions->take(10) as $permission) {
            $this->info("- {$permission->name}");
        }
        if ($allPermissions->count() > 10) {
            $this->info("... and " . ($allPermissions->count() - 10) . " more");
        }

        // 6. Test specific permission checks
        $this->info("\n=== PERMISSION TESTS ===");
        $testPermissions = ['manage-users', 'manage-roles', 'manage-settings'];
        foreach ($testPermissions as $permission) {
            $canDo = $user->can($permission);
            $status = $canDo ? '✅' : '❌';
            $this->info("{$status} {$permission}");
        }

        // 7. Check model_has_roles table
        $this->info("\n=== MODEL_HAS_ROLES TABLE ===");
        $modelHasRoles = \DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->get();
        
        $this->info("model_has_roles entries: " . $modelHasRoles->count());
        foreach ($modelHasRoles as $entry) {
            $role = Role::find($entry->role_id);
            $this->info("- Role ID: {$entry->role_id} (" . ($role ? $role->name : 'Unknown') . ")");
        }

        // 8. Check role_has_permissions table
        $this->info("\n=== ROLE_HAS_PERMISSIONS TABLE ===");
        if ($adminRole) {
            $roleHasPermissions = \DB::table('role_has_permissions')
                ->where('role_id', $adminRole->id)
                ->count();
            $this->info("Admin role has {$roleHasPermissions} permission entries in role_has_permissions table");
        }

        return 0;
    }
}
