<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DebugUserPermissions extends Command
{
    protected $signature = 'debug:user-permissions {email}';
    protected $description = 'Debug user permissions in detail';

    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found");
            return;
        }

        $this->info("=== USER DEBUG ===");
        $this->info("User ID: {$user->id}");
        $this->info("Name: {$user->first_name} {$user->last_name}");
        $this->info("Email: {$user->email}");

        // Check roles
        $this->info("\n=== ROLES ===");
        $userRoles = $user->roles;
        $this->info("Roles count: " . $userRoles->count());
        foreach ($userRoles as $role) {
            $this->info("- Role: {$role->name} (ID: {$role->id})");
        }

        // Check role names
        $roleNames = $user->getRoleNames();
        $this->info("Role names: " . $roleNames->join(', '));

        // Check direct permissions
        $this->info("\n=== DIRECT PERMISSIONS ===");
        $directPermissions = $user->getDirectPermissions();
        $this->info("Direct permissions count: " . $directPermissions->count());
        foreach ($directPermissions as $permission) {
            $this->info("- {$permission->name}");
        }

        // Check role permissions
        $this->info("\n=== ROLE PERMISSIONS ===");
        $rolePermissions = $user->getPermissionsViaRoles();
        $this->info("Role permissions count: " . $rolePermissions->count());
        foreach ($rolePermissions as $permission) {
            $this->info("- {$permission->name}");
        }

        // Check all permissions
        $this->info("\n=== ALL PERMISSIONS ===");
        $allPermissions = $user->getAllPermissions();
        $this->info("All permissions count: " . $allPermissions->count());
        foreach ($allPermissions as $permission) {
            $this->info("- {$permission->name}");
        }

        // Test specific permissions
        $this->info("\n=== PERMISSION TESTS ===");
        $testPermissions = ['manage users', 'manage roles', 'view admin dashboard'];
        foreach ($testPermissions as $permission) {
            $canDo = $user->can($permission);
            $status = $canDo ? '✓' : '✗';
            $this->info("{$status} {$permission}");
        }

        // Check Admin role permissions
        $this->info("\n=== ADMIN ROLE CHECK ===");
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $adminPermissions = $adminRole->permissions;
            $this->info("Admin role permissions count: " . $adminPermissions->count());
            foreach ($adminPermissions as $permission) {
                $this->info("- {$permission->name}");
            }
        }

        return 0;
    }
}
