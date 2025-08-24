<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up()
    {
        // ✅ STEP 1: Create missing permissions with hyphens
        $newPermissions = [
            'manage-enrollments',
            'manage-programs', 
            'manage-schools',
            'manage-users',
            'manage-classes',
            'manage-units',
            'manage-timetables',
            'manage-semesters',
            'manage-classrooms',
            'view-enrollments',
            'view-programs',
            'view-schools', 
            'view-users',
            'view-classes',
            'view-units',
            'view-dashboard',
            'manage-roles',
            'manage-permissions',
            'manage-settings'
        ];

        // Create all permissions
        foreach ($newPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
            echo "Created/Updated permission: {$permission}\n";
        }

        // ✅ STEP 2: Ensure Admin role exists and has all permissions
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        
        // Give Admin ALL permissions
        $allPermissions = Permission::all();
        $adminRole->syncPermissions($allPermissions);
        
        echo "Admin role now has " . $adminRole->permissions->count() . " permissions\n";

        // ✅ STEP 3: Update any old permission names
        $permissionMappings = [
            'manage all enrollments' => 'manage-enrollments',
            'manage all programs' => 'manage-programs',
            'manage all schools' => 'manage-schools',
            'manage all users' => 'manage-users',
            'manage all classes' => 'manage-classes',
            'manage all units' => 'manage-units',
        ];

        foreach ($permissionMappings as $oldName => $newName) {
            $oldPermission = Permission::where('name', $oldName)->first();
            if ($oldPermission) {
                echo "Removing old permission: {$oldName}\n";
                $oldPermission->delete();
            }
        }
    }

    public function down()
    {
        // Remove the permissions we created
        $permissionsToRemove = [
            'manage-enrollments',
            'manage-programs', 
            'manage-schools',
            'manage-users',
            'manage-classes',
            'manage-units',
        ];

        foreach ($permissionsToRemove as $permission) {
            Permission::where('name', $permission)->delete();
        }
    }
};
