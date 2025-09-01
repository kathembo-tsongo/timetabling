<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use App\Models\PermissionMeta;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DynamicPermissionController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all'); // all, core, dynamic
        $category = $request->get('category', 'all');
        $search = $request->get('search', '');
        $perPage = $request->get('per_page', 20);

        $permissionsQuery = Permission::query()
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withCount('roles')
            ->orderBy('name');

        $permissions = $permissionsQuery->paginate($perPage);

        // Add metadata to each permission
        $permissions->through(function ($permission) {
            $meta = PermissionMeta::where('permission_name', $permission->name)->first();
            $permission->meta = $meta;
            $permission->is_core = $meta ? $meta->is_core : $this->isCore($permission->name);
            $permission->category = $meta ? $meta->category : $this->getCategory($permission->name);
            $permission->description = $meta ? $meta->description : $this->generatePermissionDescription($permission->name);
            return $permission;
        });

        // Filter by core/dynamic and category after loading metadata
        if ($filter === 'core') {
            $permissions->setCollection($permissions->getCollection()->filter(function ($permission) {
                return $permission->is_core;
            }));
        } elseif ($filter === 'dynamic') {
            $permissions->setCollection($permissions->getCollection()->filter(function ($permission) {
                return !$permission->is_core;
            }));
        }

        if ($category !== 'all') {
            $permissions->setCollection($permissions->getCollection()->filter(function ($permission) use ($category) {
                return $permission->category === $category;
            }));
        }

        // Get statistics and categories
     
        $allPermissions = Permission::all();
        $totalPermissions = $allPermissions->count();
        $coreCount = 0;
        $dynamicCount = 0;

        foreach ($allPermissions as $permission) {
             $meta = PermissionMeta::where('permission_name', $permission->name)->first();
             $isCore = $meta ? $meta->is_core : $this->isCore($permission->name);
    
                if ($isCore) {
                    $coreCount++;
                } else {
                    $dynamicCount++;
                }
        }

        $stats = [
            'total' => $totalPermissions,
            'core' => $coreCount,
            'dynamic' => $dynamicCount,
        ];       

        $categories = PermissionMeta::distinct()
            ->whereNotNull('category')
            ->pluck('category')
            ->merge($this->getDefaultCategories())
            ->unique()
            ->sort()
            ->values();

        return Inertia::render('Admin/Permissions/DynamicPermissions', [
            'permissions' => $permissions,
            'stats' => $stats,
            'categories' => $categories,
            'categoryLabels' => $this->getPermissionCategories(),
            'filter' => $filter,
            'category' => $category,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:permissions,name',
                'description' => 'nullable|string|max:1000',
                'category' => 'required|string|max:100',
            ]);

            DB::transaction(function () use ($validated, $request) {
                // Create permission using Spatie's method
                $permission = Permission::create([
                    'name' => $validated['name'],
                    'guard_name' => 'web'
                ]);

                // Store metadata separately
                PermissionMeta::create([
                    'permission_name' => $permission->name,
                    'description' => $validated['description'],
                    'category' => $validated['category'],
                    'is_core' => false,
                    'created_by_user_id' => $request->user()->id,
                    'metadata' => [
                        'created_via' => 'dynamic_interface',
                        'created_at' => now(),
                    ]
                ]);

                Log::info('Dynamic permission created:', [
                    'permission_name' => $permission->name,
                    'category' => $validated['category'],
                    'created_by' => $request->user()->id,
                ]);
            });

            return back()->with('success', 'Permission created successfully!');

        } catch (\Exception $e) {
            Log::error('Error creating dynamic permission:', [
                'message' => $e->getMessage(),
                'data' => $request->all(),
            ]);
            return back()->withErrors(['error' => 'Failed to create permission.']);
        }
    }

    public function update(Request $request, Permission $permission)
    {
        try {
            $meta = PermissionMeta::where('permission_name', $permission->name)->first();
            
            // Prevent editing core permissions
            if ($meta && $meta->is_core) {
                return back()->withErrors(['error' => 'Core permissions cannot be modified.']);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:permissions,name,' . $permission->id,
                'description' => 'nullable|string|max:1000',
                'category' => 'required|string|max:100',
            ]);

            DB::transaction(function () use ($validated, $request, $permission) {
                $oldName = $permission->name;
                
                // Update permission name if changed (Spatie way)
                if ($permission->name !== $validated['name']) {
                    $permission->update(['name' => $validated['name']]);
                    
                    // Update meta table reference
                    PermissionMeta::where('permission_name', $oldName)
                        ->update(['permission_name' => $validated['name']]);
                }

                // Update or create metadata
                PermissionMeta::updateOrCreate(
                    ['permission_name' => $permission->name],
                    [
                        'description' => $validated['description'],
                        'category' => $validated['category'],
                        'is_core' => false,
                        'last_modified_by' => $request->user()->id,
                        'metadata' => [
                            'last_updated' => now(),
                            'updated_via' => 'dynamic_interface',
                        ]
                    ]
                );

                Log::info('Dynamic permission updated:', [
                    'permission_id' => $permission->id,
                    'permission_name' => $permission->name,
                    'old_name' => $oldName,
                    'updated_by' => $request->user()->id,
                ]);
            });

            return back()->with('success', 'Permission updated successfully!');

        } catch (\Exception $e) {
            Log::error('Error updating dynamic permission:', [
                'permission_id' => $permission->id,
                'message' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Failed to update permission.']);
        }
    }

    public function destroy(Permission $permission)
    {
        try {
            $meta = PermissionMeta::where('permission_name', $permission->name)->first();
            
            // Prevent deleting core permissions
            if ($meta && $meta->is_core) {
                return back()->withErrors(['error' => 'Core permissions cannot be deleted.']);
            }

            // Check if permission is assigned to roles
            $roleCount = $permission->roles()->count();
            if ($roleCount > 0) {
                return back()->withErrors(['error' => "Cannot delete permission. It is assigned to {$roleCount} roles."]);
            }

            DB::transaction(function () use ($permission) {
                // Delete metadata first
                PermissionMeta::where('permission_name', $permission->name)->delete();
                
                // Delete permission using Spatie method
                $permission->delete();
            });

            Log::info('Dynamic permission deleted:', [
                'permission_id' => $permission->id,
                'permission_name' => $permission->name,
            ]);

            return back()->with('success', 'Permission deleted successfully!');

        } catch (\Exception $e) {
            Log::error('Error deleting dynamic permission:', [
                'permission_id' => $permission->id,
                'message' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Failed to delete permission.']);
        }
    }

    public function bulkCreate(Request $request)
{
    $validated = $request->validate([
        'permissions' => 'required|array',
        'permissions.*.name' => 'required|string|unique:permissions,name|max:255',
        'permissions.*.description' => 'nullable|string|max:500',
        'permissions.*.category' => 'required|string|max:100'
    ]);

    $createdPermissions = [];

    foreach ($validated['permissions'] as $permissionData) {
        $permission = Permission::create([
            'name' => $permissionData['name'],
            'description' => $permissionData['description'] ?? null,
            'category' => $permissionData['category'] ?? 'general',
            'guard_name' => 'web',
            'is_core' => false,
        ]);

        $createdPermissions[] = $permission;
    }

    return back()->with('success', count($createdPermissions) . ' permissions created successfully!');
}

    public function bulkStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'permissions' => 'required|array|min:1',
                'permissions.*.name' => 'required|string|max:255|unique:permissions,name',
                'permissions.*.description' => 'nullable|string|max:1000',
                'permissions.*.category' => 'required|string|max:100',
            ]);

            $created = [];
            DB::transaction(function () use ($validated, $request, &$created) {
                foreach ($validated['permissions'] as $permissionData) {
                    // Create permission using Spatie's method
                    $permission = Permission::create([
                        'name' => $permissionData['name'],
                        'guard_name' => 'web'
                    ]);

                    // Create metadata
                    PermissionMeta::create([
                        'permission_name' => $permission->name,
                        'description' => $permissionData['description'] ?? null,
                        'category' => $permissionData['category'],
                        'is_core' => false,
                        'created_by_user_id' => $request->user()->id,
                        'metadata' => [
                            'created_via' => 'bulk_interface',
                            'created_at' => now(),
                        ]
                    ]);

                    $created[] = $permission->name;
                }
            });

            Log::info('Bulk permissions created:', [
                'count' => count($created),
                'permissions' => $created,
                'created_by' => $request->user()->id,
            ]);

            return back()->with('success', 'Successfully created ' . count($created) . ' permissions!');

        } catch (\Exception $e) {
            Log::error('Error bulk creating permissions:', [
                'message' => $e->getMessage(),
                'data' => $request->all(),
            ]);
            return back()->withErrors(['error' => 'Failed to create permissions in bulk.']);
        }
    }

    public function bulkAssignCategory(Request $request)
    {
        try {
            $validated = $request->validate([
                'permission_ids' => 'required|array|min:1',
                'permission_ids.*' => 'exists:permissions,id',
                'category' => 'required|string|max:100',
            ]);

            $permissions = Permission::whereIn('id', $validated['permission_ids'])->get();
            
            $updated = [];
            DB::transaction(function () use ($permissions, $validated, $request, &$updated) {
                foreach ($permissions as $permission) {
                    $meta = PermissionMeta::where('permission_name', $permission->name)->first();
                    
                    // Skip core permissions
                    if ($meta && $meta->is_core) {
                        continue;
                    }

                    PermissionMeta::updateOrCreate(
                        ['permission_name' => $permission->name],
                        [
                            'category' => $validated['category'],
                            'last_modified_by' => $request->user()->id,
                            'metadata' => array_merge($meta->metadata ?? [], [
                                'bulk_updated' => now(),
                                'updated_via' => 'bulk_category_assignment',
                            ])
                        ]
                    );

                    $updated[] = $permission->name;
                }
            });

            Log::info('Bulk category assignment:', [
                'category' => $validated['category'],
                'count' => count($updated),
                'permissions' => $updated,
                'updated_by' => $request->user()->id,
            ]);

            return back()->with('success', 
                'Category assigned to ' . count($updated) . ' permissions successfully!');

        } catch (\Exception $e) {
            Log::error('Error in bulk category assignment:', [
                'message' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Failed to assign category to permissions.']);
        }
    }

    /**
     * Check if permission is core based on naming convention
     */
    private function isCore($permissionName)
    {
        $corePatterns = [
            'view-', 'manage-', 'create-', 'edit-', 'delete-', 
            'process-', 'download-', 'solve-'
        ];

        foreach ($corePatterns as $pattern) {
            if (str_starts_with($permissionName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get category from permission name
     */
    private function getCategory($permissionName)
    {
        if (str_contains($permissionName, 'dashboard')) return 'dashboard';
        if (str_contains($permissionName, 'user')) return 'user_management';
        if (str_contains($permissionName, 'role') || str_contains($permissionName, 'permission')) return 'role_management';
        if (str_contains($permissionName, 'timetable') || str_contains($permissionName, 'exam')) return 'timetable_management';
        if (str_contains($permissionName, 'school') || str_contains($permissionName, 'program') || str_contains($permissionName, 'unit')) return 'academic_core';
        if (str_contains($permissionName, 'own-')) return 'personal_access';
        if (str_contains($permissionName, 'faculty-')) {
            if (str_contains($permissionName, 'dashboard')) return 'faculty_dashboard';
            if (str_contains($permissionName, 'student')) return 'faculty_students';
            if (str_contains($permissionName, 'lecturer')) return 'faculty_lecturers';
            if (str_contains($permissionName, 'unit')) return 'faculty_units';
            if (str_contains($permissionName, 'enrollment')) return 'faculty_enrollments';
            if (str_contains($permissionName, 'timetable')) return 'faculty_timetables';
            if (str_contains($permissionName, 'report')) return 'faculty_reports';
            if (str_contains($permissionName, 'class')) return 'faculty_classes';
            if (str_contains($permissionName, 'program')) return 'faculty_programs';
        }
        if (str_contains($permissionName, 'custom-')) return 'custom';
        
        return 'general';
    }

    /**
     * Get available categories
     */
    private function getPermissionCategories()
    {
        return [
            'dashboard' => 'Dashboard Access',
            'user_management' => 'User Management', 
            'role_management' => 'Role & Permission Management',
            'system_settings' => 'System Settings',
            'academic_core' => 'Academic Management',
            'timetable_management' => 'Timetable Management',
            'personal_access' => 'Personal Access',
            'faculty_dashboard' => 'Faculty Dashboard',
            'faculty_students' => 'Faculty Student Management',
            'faculty_lecturers' => 'Faculty Lecturer Management',
            'faculty_units' => 'Faculty Unit Management',
            'faculty_enrollments' => 'Faculty Enrollment Management',
            'faculty_timetables' => 'Faculty Timetable Management',
            'faculty_reports' => 'Faculty Reports',
            'faculty_classes' => 'Faculty Class Management',
            'faculty_programs' => 'Faculty Program Management',
            'general' => 'General Permissions',
            'custom' => 'Custom Permissions',
        ];
    }

    /**
     * Get default categories for filtering
     */
    private function getDefaultCategories()
    {
        return array_keys($this->getPermissionCategories());
    }

    /**
     * Generate permission description from name
     */
    private function generatePermissionDescription($permissionName)
    {
        $name = str_replace('-', ' ', $permissionName);
        $name = ucwords($name);
        
        if (str_starts_with($permissionName, 'view-')) {
            return "View " . str_replace('View ', '', $name);
        } elseif (str_starts_with($permissionName, 'create-')) {
            return "Create " . str_replace('Create ', '', $name);
        } elseif (str_starts_with($permissionName, 'edit-')) {
            return "Edit " . str_replace('Edit ', '', $name);
        } elseif (str_starts_with($permissionName, 'delete-')) {
            return "Delete " . str_replace('Delete ', '', $name);
        } elseif (str_starts_with($permissionName, 'manage-')) {
            return "Manage " . str_replace('Manage ', '', $name);
        } elseif (str_starts_with($permissionName, 'process-')) {
            return "Process " . str_replace('Process ', '', $name);
        } elseif (str_starts_with($permissionName, 'download-')) {
            return "Download " . str_replace('Download ', '', $name);
        }
        
        return $name;
    }

    /**
     * Get permission usage statistics
     */
    public function getPermissionStats(Permission $permission)
    {
        $meta = PermissionMeta::where('permission_name', $permission->name)->first();
        
        $stats = [
            'role_count' => $permission->roles()->count(),
            'user_count' => $permission->users()->count(),
            'is_core' => $meta ? $meta->is_core : $this->isCore($permission->name),
            'category' => $meta ? $meta->category : $this->getCategory($permission->name),
            'description' => $meta ? $meta->description : $this->generatePermissionDescription($permission->name),
            'created_at' => $permission->created_at,
            'updated_at' => $permission->updated_at,
            'created_by' => $meta && $meta->createdBy ? $meta->createdBy->name : null,
            'last_modified_by' => $meta && $meta->lastModifiedBy ? $meta->lastModifiedBy->name : null,
        ];

        return response()->json($stats);
    }
}