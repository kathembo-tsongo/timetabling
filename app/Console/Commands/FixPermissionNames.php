<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class FixUserPermissionLoading extends Command
{
    protected $signature = 'fix:user-permissions {email}';
    protected $description = 'Fix user permission loading issue';

    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User not found: {$email}");
            return;
        }

        $this->info("Fixing permissions for: {$user->email}");

        // Clear all caches
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        // Get Admin role
        $adminRole = Role::where('name', 'Admin')->first();
        if (!$adminRole) {
            $this->error("Admin role not found!");
            return;
        }

        // Remove user from all roles first
        $user->roles()->detach();
        $this->info("Removed all existing roles");

        // Assign Admin role
        $user->assignRole($adminRole);
        $this->info("Assigned Admin role");

        // Force refresh the user
        $user->refresh();
        $user->load('roles.permissions');

        // Test the assignment
        $this->info("\n=== VERIFICATION ===");
        $this->info("User has Admin role: " . ($user->hasRole('Admin') ? 'YES' : 'NO'));
        $this->info("User permissions count: " . $user->getAllPermissions()->count());
        $this->info("Can manage users: " . ($user->can('manage-users') ? 'YES' : 'NO'));
        $this->info("Can manage roles: " . ($user->can('manage-roles') ? 'YES' : 'NO'));

        // Clear caches again
        \Artisan::call('cache:clear');
        \Artisan::call('permission:cache-reset');
        
        $this->info("\n✅ Permission fix completed! Please refresh your browser.");
        
        return 0;
    }
}
