<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\School;
use Spatie\Permission\Models\Role;

class AssignFacultyAdminRole extends Command
{
    protected $signature = 'faculty:assign-admin {email} {school_code}';
    protected $description = 'Assign faculty admin role to a user for a specific school';

    public function handle()
    {
        $email = $this->argument('email');
        $schoolCode = strtoupper($this->argument('school_code'));

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("User with email {$email} not found");
            return 1;
        }

        $school = School::where('code', $schoolCode)->first();
        if (!$school) {
            $this->error("School with code {$schoolCode} not found");
            return 1;
        }

        $roleName = "Faculty Admin - {$schoolCode}";
        $role = Role::where('name', $roleName)->first();
        
        if (!$role) {
            $this->error("Role {$roleName} not found. Please run the seeder first:");
            $this->error("php artisan db:seed --class=RoleAndPermissionSeeder");
            return 1;
        }

        // Remove any existing Faculty Admin roles
        $existingFacultyRoles = $user->roles()->where('name', 'like', 'Faculty Admin%')->get();
        foreach ($existingFacultyRoles as $existingRole) {
            $user->removeRole($existingRole);
            $this->info("Removed existing role: {$existingRole->name}");
        }

        // Assign school to user
        $user->school_id = $school->id;
        $user->save();

        // Assign new role to user
        $user->assignRole($role);

        $this->info("Successfully assigned {$roleName} role to {$user->email}");
        $this->info("User assigned to school: {$school->name}");
        $this->info("User can now access: /" . strtolower($schoolCode) . "/dashboard");
        
        return 0;
    }
}
