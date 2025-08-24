<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all schools
        $schools = ['SCES', 'SBS', 'SLS', 'TOURISM', 'SHS', 'SHM'];

        // Define base permissions
        $permissions = [
            // Dashboard
            'view-dashboard',
            'view admin dashboard',
            'view student dashboard', 
            'view lecturer dashboard',
            'view exam office dashboard',
            'view faculty admin dashboard',

            // Administration
            'manage-users', 'view-users', 'create-users', 'edit-users', 'delete-users',
            'manage-roles', 'view-roles', 'create-roles', 'edit-roles', 'delete-roles',
            'manage-permissions', 'view-permissions',
            'manage-settings', 'view-settings',

            // Academic Management
            'manage-schools', 'view-schools', 'create-schools', 'edit-schools', 'delete-schools',
            'manage-programs', 'view-programs', 'create-programs', 'edit-programs', 'delete-programs',
            'manage-units', 'view-units', 'create-units', 'edit-units', 'delete-units',
            'manage-classes', 'view-classes', 'create-classes', 'edit-classes', 'delete-classes',
            'manage-enrollments', 'view-enrollments', 'create-enrollments', 'edit-enrollments', 'delete-enrollments',
            'manage-semesters', 'view-semesters', 'create-semesters', 'edit-semesters', 'delete-semesters',
            'manage-classrooms', 'view-classrooms', 'create-classrooms', 'edit-classrooms', 'delete-classrooms',

            // Timetables
            'manage-class-timetables', 'view-class-timetables', 'create-class-timetables', 'edit-class-timetables', 'delete-class-timetables',
            'process-class-timetables', 'solve-class-conflicts', 'download-class-timetables',
            'view-own-class-timetables', 'download-own-class-timetables',
            'manage-exam-timetables', 'view-exam-timetables', 'create-exam-timetables', 'edit-exam-timetables', 'delete-exam-timetables',
            'process-exam-timetables', 'solve-exam-conflicts', 'download-exam-timetables',
            'view-own-exam-timetables', 'download-own-exam-timetables',
            'manage-exam-rooms', 'view-exam-rooms', 'create-exam-rooms', 'edit-exam-rooms', 'delete-exam-rooms', 'view-faculty-class-timetables-sces', 'view-faculty-exam-timetables-sces',

            // Time Slots
            'manage-time-slots', 'view-time-slots',
        ];

        // Add school-specific faculty admin permissions
        foreach ($schools as $school) {
            $schoolLower = strtolower($school);
            
            $schoolPermissions = [
                // Dashboard access
                "view-faculty-dashboard-{$schoolLower}",
                
                // Student management
                "manage-faculty-students-{$schoolLower}",
                "create-faculty-students-{$schoolLower}",
                "view-faculty-students-{$schoolLower}",
                "edit-faculty-students-{$schoolLower}",
                "delete-faculty-students-{$schoolLower}",
                
                // Lecturer management
                "manage-faculty-lecturers-{$schoolLower}",
                "create-faculty-lecturers-{$schoolLower}",
                "view-faculty-lecturers-{$schoolLower}",
                "edit-faculty-lecturers-{$schoolLower}",
                "delete-faculty-lecturers-{$schoolLower}",
                
                // Unit management
                "manage-faculty-units-{$schoolLower}",
                "create-faculty-units-{$schoolLower}",
                "view-faculty-units-{$schoolLower}",
                "edit-faculty-units-{$schoolLower}",
                "delete-faculty-units-{$schoolLower}",
                
                // Enrollment management
                "manage-faculty-enrollments-{$schoolLower}",
                "create-faculty-enrollments-{$schoolLower}",
                "view-faculty-enrollments-{$schoolLower}",
                "edit-faculty-enrollments-{$schoolLower}",
                "delete-faculty-enrollments-{$schoolLower}",
                
                // Timetable management
                "manage-faculty-timetables-{$schoolLower}",
                "create-faculty-timetables-{$schoolLower}",
                "view-faculty-timetables-{$schoolLower}",
                "edit-faculty-timetables-{$schoolLower}",
                "delete-faculty-timetables-{$schoolLower}",
                
                // Reports
                "view-faculty-reports-{$schoolLower}",
                "download-faculty-reports-{$schoolLower}",
                
                // Class management
                "manage-faculty-classes-{$schoolLower}",
                "create-faculty-classes-{$schoolLower}",
                "view-faculty-classes-{$schoolLower}",
                "edit-faculty-classes-{$schoolLower}",
                "delete-faculty-classes-{$schoolLower}",
                
                // Program management (view only for faculty admins)
                "view-faculty-programs-{$schoolLower}",
            ];
            
            $permissions = array_merge($permissions, $schoolPermissions);
        }

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Get all permission names for Admin role
        $allPermissions = Permission::pluck('name')->toArray();

        // Define roles with their specific permissions
        $rolePermissions = [
            'Admin' => $allPermissions, // Admin gets ALL permissions
            
            'Exam Office' => [
                'view-dashboard',
                'view exam office dashboard',
                
                // Exam management
                'manage-exam-timetables', 'view-exam-timetables', 'create-exam-timetables', 'edit-exam-timetables', 'delete-exam-timetables',
                'process-exam-timetables', 'solve-exam-conflicts', 'download-exam-timetables',
                'manage-exam-rooms', 'view-exam-rooms', 'create-exam-rooms', 'edit-exam-rooms', 'delete-exam-rooms',
                'manage-time-slots', 'view-time-slots',
                
                // View academic data
                'view-units', 'view-classes', 'view-enrollments', 'view-semesters', 'view-classrooms',
            ],
            
            'Faculty Admin' => [
                'view-dashboard',
                'view faculty admin dashboard',
                
                // Academic management (school-specific)
                'view-schools', 'view-programs',
                'manage-units', 'view-units', 'create-units', 'edit-units', 'delete-units',
                'manage-classes', 'view-classes', 'create-classes', 'edit-classes', 'delete-classes',
                'manage-enrollments', 'view-enrollments', 'create-enrollments', 'edit-enrollments', 'delete-enrollments',
                'view-semesters',
                'manage-classrooms', 'view-classrooms', 'create-classrooms', 'edit-classrooms', 'delete-classrooms',
                
                // Class timetables
                'manage-class-timetables', 'view-class-timetables', 'create-class-timetables',
                'edit-class-timetables', 'delete-class-timetables',
                'process-class-timetables', 'solve-class-conflicts', 'download-class-timetables',
                
                // Limited exam access
                'view-exam-timetables', 'download-exam-timetables', 'view-faculty-class-timetables-sces', 'view-faculty-exam-timetables-sces',
                
                // User management (school-specific)
                'view-users',
            ],
            
            'Lecturer' => [
                'view-dashboard',
                'view lecturer dashboard',
                
                // Own timetables only
                'view-own-class-timetables', 'download-own-class-timetables',
                'view-own-exam-timetables', 'download-own-exam-timetables',
                
                // Limited view access
                'view-units', 'view-classes', 'view-classrooms',
            ],
            
            'Student' => [
                'view-dashboard',
                'view student dashboard',
                
                // Own timetables only
                'view-own-class-timetables', 'download-own-class-timetables',
                'view-own-exam-timetables', 'download-own-exam-timetables',
                
                // Limited view access
                'view-units', 'view-classes', 'view-enrollments',
            ],
        ];

        // Add school-specific faculty admin roles
        foreach ($schools as $school) {
            $schoolLower = strtolower($school);
            $roleName = "Faculty Admin - {$school}";
            
            // Get all permissions for this specific school
            $schoolSpecificPermissions = array_filter($allPermissions, function($permission) use ($schoolLower) {
                return str_contains($permission, "faculty-") && str_contains($permission, "-{$schoolLower}");
            });
            
            // Add base faculty admin permissions
            $baseFacultyPermissions = [
                'view-dashboard',
                "view-faculty-dashboard-{$schoolLower}",
                
                // General view permissions
                'view-schools', 'view-programs', 'view-semesters', 'view-classrooms',
                'view-units', 'view-classes', 'view-enrollments', 'view-users',
                
                // Limited exam access
                'view-exam-timetables', 'download-exam-timetables',
                
                // Class timetables (general)
                'view-class-timetables', 'download-class-timetables',
            ];
            
            $rolePermissions[$roleName] = array_merge($baseFacultyPermissions, $schoolSpecificPermissions);
        }

        // Create roles and assign permissions
        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($permissions);
            $this->command->info("Created role: {$roleName} with " . count($permissions) . " permissions");
        }

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info("Created " . count($schools) . " school-specific faculty admin roles");
        $this->command->info("Total permissions created: " . count($allPermissions));
        $this->command->info("Total roles created: " . count($rolePermissions));
    }
}
