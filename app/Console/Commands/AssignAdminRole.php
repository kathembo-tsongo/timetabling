<?php

namespace App\Console\Commands;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Console\Command;

class AssignAdminRole extends Command
{
    protected $signature = 'user:assign-admin {--code= : User code like ADM1} {--email= : User email}';
    protected $description = 'Assign Admin role to a user using Spatie Laravel Permission';

    public function handle()
    {
        $code = $this->option('code');
        $email = $this->option('email');
        
        // If no options provided, use defaults for ADM1
        if (!$code && !$email) {
            $code = 'ADM1';
            $email = 'admin@strathmore.edu';
        }
        
        // Find user by code or email
        $user = null;
        if ($code) {
            $user = User::where('code', $code)->first();
        } elseif ($email) {
            $user = User::where('email', $email)->first();
        }
        
        if (!$user) {
            $this->error("User not found with " . ($code ? "code '{$code}'" : "email '{$email}'"));
            
            // Show available users
            $this->info("Available users:");
            $users = User::select('id', 'code', 'first_name', 'last_name', 'email')->limit(10)->get();
            $this->table(['ID', 'Code', 'First Name', 'Last Name', 'Email'], $users->toArray());
            
            return Command::FAILURE;
        }

        // Check if Admin role exists
        $adminRole = Role::where('name', 'Admin')->first();
        if (!$adminRole) {
            $this->error("Admin role not found. Please create it first.");
            
            // Show existing roles
            $this->info("Existing roles:");
            $roles = Role::select('id', 'name')->get();
            $this->table(['ID', 'Name'], $roles->toArray());
            
            return Command::FAILURE;
        }

        // Check if user already has admin role
        if ($user->hasRole('Admin')) {
            $this->warn("User '{$user->first_name} {$user->last_name}' (Code: {$user->code}) already has Admin role.");
            
            // Show user's current roles
            $this->info("Current roles for {$user->first_name} {$user->last_name}:");
            foreach ($user->roles as $role) {
                $this->line("- {$role->name}");
            }
            
            return Command::SUCCESS;
        }

        // Assign role using Spatie method
        $user->assignRole('Admin');
        
        $this->info("Admin role successfully assigned to '{$user->first_name} {$user->last_name}' (Code: {$user->code}, Email: {$user->email})");
        
        // Show user's current roles after assignment
        $this->info("User's roles after assignment:");
        foreach ($user->fresh()->roles as $role) {
            $this->line("- {$role->name}");
        }

        // Show user's permissions through roles
        $permissions = $user->getAllPermissions();
        if ($permissions->count() > 0) {
            $this->info("User now has {$permissions->count()} permissions through assigned roles.");
        }

        return Command::SUCCESS;
    }
}