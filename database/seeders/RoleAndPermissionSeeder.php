<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\PermissionMeta;
use App\Models\RoleMeta;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all schools
        $schools = ['SCES', 'SBS'];

        // Define base permissions with categories
        $permissionData = [
            // Dashboard permissions
            'dashboard' => [
                'view-dashboard',
                'view-admin-dashboard',
                'view-student-dashboard', 
                'view-lecturer-dashboard',
                'view-exam-office-dashboard',
                'view-faculty-admin-dashboard',
            ],
            
            // Administration
            'user_management' => [
                'manage-users', 'view-users', 'create-users', 'edit-users', 'delete-users',
            ],
            
            'role_management' => [
                'manage-roles', 'view-roles', 'create-roles', 'edit-roles', 'delete-roles',
                'manage-permissions', 'view-permissions',
            ],
            
            'system_settings' => [
                'manage-settings', 'view-settings',
            ],

            // Academic Management
            'academic_core' => [
                'manage-schools', 'view-schools', 'create-schools', 'edit-schools', 'delete-schools',
                'manage-programs', 'view-programs', 'create-programs', 'edit-programs', 'delete-programs',
                'manage-units', 'view-units', 'create-units', 'edit-units', 'delete-units',
                'manage-classes', 'view-classes', 'create-classes', 'edit-classes', 'delete-classes',
                'manage-enrollments', 'view-enrollments', 'create-enrollments', 'edit-enrollments', 'delete-enrollments',
                'manage-semesters', 'view-semesters', 'create-semesters', 'edit-semesters', 'delete-semesters',
                'manage-classrooms', 'view-classrooms', 'create-classrooms', 'edit-classrooms', 'delete-classrooms',
            ],

            // Timetable Management
            'timetable_management' => [
                'manage-class-timetables', 'view-class-timetables', 'create-class-timetables', 'edit-class-timetables', 'delete-class-timetables',
                'process-class-timetables', 'solve-class-conflicts', 'download-class-timetables',
                'manage-exam-timetables', 'view-exam-timetables', 'create-exam-timetables', 'edit-exam-timetables', 'delete-exam-timetables',
                'process-exam-timetables', 'solve-exam-conflicts', 'download-exam-timetables',
                'manage-exam-rooms', 'view-exam-rooms', 'create-exam-rooms', 'edit-exam-rooms', 'delete-exam-rooms',
                'manage-time-slots', 'view-time-slots',
            ],
            
            // Personal Access
            'personal_access' => [
                'view-own-class-timetables', 'download-own-class-timetables',
                'view-own-exam-timetables', 'download-own-exam-timetables',
            ],
        ];

        // Flatten permissions for creation
        $allPermissions = [];
        foreach ($permissionData as $category => $permissions) {
            foreach ($permissions as $permission) {
                $allPermissions[$permission] = $category;
            }
        }

        // Add school-specific faculty admin permissions
        foreach ($schools as $school) {
            $schoolLower = strtolower($school);
            
            $schoolPermissions = [
                "view-faculty-dashboard-{$schoolLower}" => 'faculty_dashboard',
                
                // Student management
                "manage-faculty-students-{$schoolLower}" => 'faculty_students',
                "create-faculty-students-{$schoolLower}" => 'faculty_students',
                "view-faculty-students-{$schoolLower}" => 'faculty_students',
                "edit-faculty-students-{$schoolLower}" => 'faculty_students',
                "delete-faculty-students-{$schoolLower}" => 'faculty_students',
                
                // Lecturer management
                "manage-faculty-lecturers-{$schoolLower}" => 'faculty_lecturers',
                "create-faculty-lecturers-{$schoolLower}" => 'faculty_lecturers',
                "view-faculty-lecturers-{$schoolLower}" => 'faculty_lecturers',
                "edit-faculty-lecturers-{$schoolLower}" => 'faculty_lecturers',
                "delete-faculty-lecturers-{$schoolLower}" => 'faculty_lecturers',
                
                // Unit management
                "manage-faculty-units-{$schoolLower}" => 'faculty_units',
                "create-faculty-units-{$schoolLower}" => 'faculty_units',
                "view-faculty-units-{$schoolLower}" => 'faculty_units',
                "edit-faculty-units-{$schoolLower}" => 'faculty_units',
                "delete-faculty-units-{$schoolLower}" => 'faculty_units',
                
                // Enrollment management
                "manage-faculty-enrollments-{$schoolLower}" => 'faculty_enrollments',
                "create-faculty-enrollments-{$schoolLower}" => 'faculty_enrollments',
                "view-faculty-enrollments-{$schoolLower}" => 'faculty_enrollments',
                "edit-faculty-enrollments-{$schoolLower}" => 'faculty_enrollments',
                "delete-faculty-enrollments-{$schoolLower}" => 'faculty_enrollments',
                
                // Timetable management
                "manage-faculty-timetables-{$schoolLower}" => 'faculty_timetables',
                "create-faculty-timetables-{$schoolLower}" => 'faculty_timetables',
                "view-faculty-timetables-{$schoolLower}" => 'faculty_timetables',
                "edit-faculty-timetables-{$schoolLower}" => 'faculty_timetables',
                "delete-faculty-timetables-{$schoolLower}" => 'faculty_timetables',
                
                // Reports
                "view-faculty-reports-{$schoolLower}" => 'faculty_reports',
                "download-faculty-reports-{$schoolLower}" => 'faculty_reports',
                
                // Class management
                "manage-faculty-classes-{$schoolLower}" => 'faculty_classes',
                "create-faculty-classes-{$schoolLower}" => 'faculty_classes',
                "view-faculty-classes-{$schoolLower}" => 'faculty_classes',
                "edit-faculty-classes-{$schoolLower}" => 'faculty_classes',
                "delete-faculty-classes-{$schoolLower}" => 'faculty_classes',
                
                // Program management
                "view-faculty-programs-{$schoolLower}" => 'faculty_programs',
            ];
            
            $allPermissions = array_merge($allPermissions, $schoolPermissions);
        }

        // Add special permissions
        $allPermissions['view-faculty-class-timetables-sces'] = 'faculty_timetables';
        $allPermissions['view-faculty-exam-timetables-sces'] = 'faculty_timetables';

        // Create all permissions
        $this->command->info('Creating permissions...');
        foreach ($allPermissions as $permissionName => $category) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web'
            ]);

            // Create metadata for each permission
            PermissionMeta::firstOrCreate([
                'permission_name' => $permission->name
            ], [
                'description' => $this->generatePermissionDescription($permission->name),
                'category' => $category,
                'is_core' => true, // All seeded permissions are core
                'metadata' => [
                    'seeded' => true,
                    'created_at' => now(),
                    'school' => $this->extractSchoolFromPermission($permission->name)
                ]
            ]);
        }

        // Get all permission names for Admin role
        $allPermissionNames = array_keys($allPermissions);

        // Define roles with their specific permissions and descriptions
        $roleData = [
            'Admin' => [
                'permissions' => $allPermissionNames, // Admin gets ALL permissions
                'description' => 'Full system administrator with access to all features and permissions'
            ],
            
            'Exam Office' => [
                'permissions' => [
                    'view-dashboard',
                    'view-exam-office-dashboard',
                    
                    // Exam management
                    'manage-exam-timetables', 'view-exam-timetables', 'create-exam-timetables', 
                    'edit-exam-timetables', 'delete-exam-timetables',
                    'process-exam-timetables', 'solve-exam-conflicts', 'download-exam-timetables',
                    'manage-exam-rooms', 'view-exam-rooms', 'create-exam-rooms', 'edit-exam-rooms', 'delete-exam-rooms',
                    'manage-time-slots', 'view-time-slots',
                    
                    // View academic data
                    'view-units', 'view-classes', 'view-enrollments', 'view-semesters', 'view-classrooms',
                ],
                'description' => 'Examination office staff responsible for exam scheduling and management'
            ],
            
            'Faculty Admin' => [
                'permissions' => [
                    'view-dashboard',
                    'view-faculty-admin-dashboard',
                    
                    // Academic management
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
                    'view-exam-timetables', 'download-exam-timetables', 
                    'view-faculty-class-timetables-sces', 'view-faculty-exam-timetables-sces',
                    
                    // User management
                    'view-users',
                ],
                'description' => 'Faculty-level administrator managing academic operations within their school'
            ],
            
            'Lecturer' => [
                'permissions' => [
                    'view-dashboard',
                    'view-lecturer-dashboard',
                    
                    // Own timetables only
                    'view-own-class-timetables', 'download-own-class-timetables',
                    'view-own-exam-timetables', 'download-own-exam-timetables',
                    
                    // Limited view access
                    'view-units', 'view-classes', 'view-classrooms',
                ],
                'description' => 'Teaching staff with access to their own schedules and limited academic information'
            ],
            
            'Student' => [
                'permissions' => [
                    'view-dashboard',
                    'view-student-dashboard',
                    
                    // Own timetables only
                    'view-own-class-timetables', 'download-own-class-timetables',
                    'view-own-exam-timetables', 'download-own-exam-timetables',
                    
                    // Limited view access
                    'view-units', 'view-classes', 'view-enrollments',
                ],
                'description' => 'Students with access to their personal academic information and schedules'
            ],
        ];

        // Add school-specific faculty admin roles
        foreach ($schools as $school) {
            $schoolLower = strtolower($school);
            $roleName = "Faculty Admin - {$school}";
            
            // Get all permissions for this specific school
            $schoolSpecificPermissions = array_filter($allPermissionNames, function($permission) use ($schoolLower) {
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
            
            $roleData[$roleName] = [
                'permissions' => array_merge($baseFacultyPermissions, $schoolSpecificPermissions),
                'description' => "Faculty administrator for {$school} with school-specific management permissions"
            ];
        }

        // Create roles and assign permissions
        $this->command->info('Creating roles...');
        foreach ($roleData as $roleName => $data) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web'
            ]);
            
            $role->syncPermissions($data['permissions']);
            
            // Create metadata for each role
            RoleMeta::firstOrCreate([
                'role_name' => $role->name
            ], [
                'description' => $data['description'],
                'is_core' => true, // All seeded roles are core
                'metadata' => [
                    'seeded' => true,
                    'created_at' => now(),
                    'permissions_count' => count($data['permissions'])
                ]
            ]);
            
            $this->command->info("Created role: {$roleName} with " . count($data['permissions']) . " permissions");
        }

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info("Created " . count($schools) . " school-specific faculty admin roles");
        $this->command->info("Total permissions created: " . count($allPermissionNames));
        $this->command->info("Total roles created: " . count($roleData));
    }

    private function generatePermissionDescription($permissionName)
    {
        // Generate human-readable descriptions
        $name = str_replace('-', ' ', $permissionName);
        $name = ucwords($name);
        
        if (str_starts_with($permissionName, 'view-')) {
            return "View {$name}";
        } elseif (str_starts_with($permissionName, 'create-')) {
            return "Create {$name}";
        } elseif (str_starts_with($permissionName, 'edit-')) {
            return "Edit {$name}";
        } elseif (str_starts_with($permissionName, 'delete-')) {
            return "Delete {$name}";
        } elseif (str_starts_with($permissionName, 'manage-')) {
            return "Manage {$name}";
        }
        
        return $name;
    }

    private function extractSchoolFromPermission($permissionName)
    {
        $schools = ['sces', 'sbs'];
        
        foreach ($schools as $school) {
            if (str_contains($permissionName, "-{$school}")) {
                return strtoupper($school);
            }
        }
        
        return null;
    }
}