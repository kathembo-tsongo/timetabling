<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions grouped by category
        $permissions = [
            // Dashboard
            'view-dashboard',
            
            // Users Management
            'view-users', 'create-users', 'edit-users', 'delete-users',
            
            // Roles & Permissions
            'view-roles', 'create-roles', 'edit-roles', 'delete-roles',
            'view-permissions', 'create-permissions', 'edit-permissions', 'delete-permissions',
            
            // Schools Management
            'view-schools', 'create-schools', 'edit-schools', 'delete-schools',
            
            // Programs Management
            'view-programs', 'create-programs', 'edit-programs', 'delete-programs',
            
            // Units Management
            'view-units', 'create-units', 'edit-units', 'delete-units', 'assign-units',
            
            // Classes Management
            'view-classes', 'create-classes', 'edit-classes', 'delete-classes',
            
            // Students Management
            'view-students', 'create-students', 'edit-students', 'delete-students',
            
            // Enrollments Management
            'view-enrollments', 'create-enrollments', 'edit-enrollments', 'delete-enrollments',
            
            // Semesters Management
            'view-semesters', 'create-semesters', 'edit-semesters', 'delete-semesters', 'activate-semesters',
            
            // Timetables Management
            'view-class-timetables', 'create-class-timetables', 'edit-class-timetables', 'delete-class-timetables',
            'view-exam-timetables', 'create-exam-timetables', 'edit-exam-timetables', 'delete-exam-timetables',
            
            // Lecturer Assignments
            'view-lecturer-assignments', 'create-lecturer-assignments', 'edit-lecturer-assignments', 'delete-lecturer-assignments',
            
            // Infrastructure
            'view-classrooms', 'create-classrooms', 'edit-classrooms', 'delete-classrooms',
            'view-buildings', 'create-buildings', 'edit-buildings', 'delete-buildings',
            'view-groups', 'create-groups', 'edit-groups', 'delete-groups',
            
            // Reports
            'view-reports', 'generate-reports', 'export-reports',
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create Admin Role with all permissions
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $adminRole->givePermissionTo(Permission::all());

        // Faculty Admin Permissions (school-specific)
        $facultyAdminPermissions = [
            'view-dashboard',
            'view-schools',
            'view-programs', 'create-programs', 'edit-programs', 'delete-programs',
            'view-units', 'create-units', 'edit-units', 'delete-units', 'assign-units',
            'view-classes', 'create-classes', 'edit-classes', 'delete-classes',
            'view-students', 'create-students', 'edit-students', 'delete-students',
            'view-enrollments', 'create-enrollments', 'edit-enrollments', 'delete-enrollments',
            'view-semesters', 'create-semesters', 'edit-semesters', 'delete-semesters', 'activate-semesters',
            'view-class-timetables', 'create-class-timetables', 'edit-class-timetables', 'delete-class-timetables',
            'view-exam-timetables', 'create-exam-timetables', 'edit-exam-timetables', 'delete-exam-timetables',
            'view-lecturer-assignments', 'create-lecturer-assignments', 'edit-lecturer-assignments', 'delete-lecturer-assignments',
            'view-classrooms', 'view-buildings', 'view-groups', 'create-groups', 'edit-groups', 'delete-groups',
            'view-reports', 'generate-reports', 'export-reports',
        ];

        // Create Faculty Admin Roles for each school
        $schools = ['SCES', 'SBS', 'SLS'];
        foreach ($schools as $school) {
            $facultyRole = Role::firstOrCreate(['name' => "Faculty Admin - {$school}"]);
            $facultyRole->syncPermissions($facultyAdminPermissions);
        }

        // Student Role
        $studentRole = Role::firstOrCreate(['name' => 'Student']);
        $studentRole->givePermissionTo(['view-dashboard', 'view-enrollments', 'view-class-timetables', 'view-exam-timetables']);

        // Lecturer Role
        $lecturerRole = Role::firstOrCreate(['name' => 'Lecturer']);
        $lecturerRole->givePermissionTo(['view-dashboard', 'view-classes', 'view-students', 'view-class-timetables']);

        // Class Office Role
        $classOfficeRole = Role::firstOrCreate(['name' => 'Class Office']);
        $classOfficeRole->givePermissionTo([
            'view-dashboard', 'view-class-timetables', 'create-class-timetables', 
            'edit-class-timetables', 'delete-class-timetables', 'view-classrooms'
        ]);

        // Exam Office Role
        $examOfficeRole = Role::firstOrCreate(['name' => 'Exam Office']);
        $examOfficeRole->givePermissionTo([
            'view-dashboard', 'view-exam-timetables', 'create-exam-timetables',
            'edit-exam-timetables', 'delete-exam-timetables', 'view-classrooms'
        ]);

        $this->command->info('Roles and permissions created successfully!');
    }
}