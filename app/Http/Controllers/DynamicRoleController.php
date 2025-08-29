<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\RoleMeta;
use App\Models\PermissionMeta;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DynamicRoleController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all'); // all, core, dynamic
        $search = $request->get('search', '');
        $perPage = $request->get('per_page', 15);

        $rolesQuery = Role::query()
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withCount('users')
            ->withCount('permissions')
            ->orderBy('name');

        $roles = $rolesQuery->paginate($perPage);

        // Add metadata to each role
        $roles->through(function ($role) {
            $meta = RoleMeta::where('role_name', $role->name)->first();
            $role->meta = $meta;
            $role->is_core = $meta ? $meta->is_core : false;
            $role->description = $meta ? $meta->description : null;
            return $role;
        });

        // Filter by core/dynamic after loading metadata
        if ($filter === 'core') {
            $roles->setCollection($roles->getCollection()->filter(function ($role) {
                return $role->is_core;
            }));
        } elseif ($filter === 'dynamic') {
            $roles->setCollection($roles->getCollection()->filter(function ($role) {
                return !$role->is_core;
            }));
        }

        // Get statistics
        $stats = [
            'total' => Role::count(),
            'core' => RoleMeta::where('is_core', true)->count(),
            'dynamic' => RoleMeta::where('is_core', false)->count(),
        ];

        // Get permissions for the create/edit modals
        $permissions = Permission::orderBy('name')->get()->map(function ($permission) {
            $meta = PermissionMeta::where('permission_name', $permission->name)->first();
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'description' => $meta?->description,
                'is_core' => $meta?->is_core ?? false,
                'category' => $meta?->category ?? 'general',
            ];
        });

        return Inertia::render('Admin/Roles/DynamicRoles', [
            'roles' => $roles,
            'stats' => $stats,
            'filter' => $filter,
            'search' => $search,
            'perPage' => $perPage,
            'permissions' => $permissions,
        ]);
    }

    public function create()
    {
        $permissions = Permission::orderBy('name')->get();
        
        // Group permissions by category using metadata
        $permissionsByCategory = [];
        foreach ($permissions as $permission) {
            $meta = PermissionMeta::where('permission_name', $permission->name)->first();
            $category = $meta ? $meta->category : 'general';
            
            if (!isset($permissionsByCategory[$category])) {
                $permissionsByCategory[$category] = [];
            }
            
            $permissionsByCategory[$category][] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'description' => $meta ? $meta->description : null,
                'is_core' => $meta ? $meta->is_core : false,
            ];
        }

        $permissionCategories = $this->getPermissionCategories();

        return Inertia::render('Admin/Roles/CreateRole', [
            'permissionsByCategory' => $permissionsByCategory,
            'permissionCategories' => $permissionCategories,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name',
                'description' => 'nullable|string|max:1000',
                'permissions' => 'array',
                'permissions.*' => 'exists:permissions,name',
            ]);

            DB::transaction(function () use ($validated, $request) {
                // Create role using Spatie method
                $role = Role::create([
                    'name' => $validated['name'],
                    'guard_name' => 'web'
                ]);

                // Assign permissions if provided
                if (!empty($validated['permissions'])) {
                    $role->givePermissionTo($validated['permissions']);
                }

                // Create metadata for the role
                RoleMeta::create([
                    'role_name' => $role->name,
                    'description' => $validated['description'],
                    'is_core' => false, // Dynamic roles are never core
                    'created_by_user_id' => $request->user()->id,
                    'metadata' => [
                        'created_via' => 'dynamic_interface',
                        'created_at' => now(),
                        'permissions_count' => count($validated['permissions'] ?? [])
                    ]
                ]);

                Log::info('Dynamic role created:', [
                    'role_name' => $role->name,
                    'created_by' => $request->user()->id,
                    'permissions_count' => count($validated['permissions'] ?? []),
                ]);
            });

            return back()->with('success', 'Role created successfully!');

        } catch (\Exception $e) {
            Log::error('Error creating dynamic role:', [
                'message' => $e->getMessage(),
                'data' => $request->all(),
            ]);
            return back()->withErrors(['error' => 'Failed to create role.'])->withInput();
        }
    }

    public function edit(Role $role)
    {
        $meta = RoleMeta::where('role_name', $role->name)->first();
        
        // Prevent editing core roles through dynamic interface
        if ($meta && $meta->is_core) {
            return response()->json(['error' => 'Core roles cannot be edited through this interface.'], 403);
        }

        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return response()->json([
            'role' => array_merge($role->toArray(), [
                'description' => $meta?->description,
                'is_core' => $meta?->is_core ?? false,
            ]),
            'rolePermissions' => $rolePermissions,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        try {
            $meta = RoleMeta::where('role_name', $role->name)->first();
            
            // Prevent editing core roles
            if ($meta && $meta->is_core) {
                return back()->withErrors(['error' => 'Core roles cannot be modified.']);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
                'description' => 'nullable|string|max:1000',
                'permissions' => 'array',
                'permissions.*' => 'exists:permissions,name',
            ]);

            DB::transaction(function () use ($validated, $request, $role) {
                $oldName = $role->name;
                
                // Update role name if changed (Spatie way)
                if ($role->name !== $validated['name']) {
                    $role->update(['name' => $validated['name']]);
                    
                    // Update metadata table reference
                    RoleMeta::where('role_name', $oldName)
                        ->update(['role_name' => $validated['name']]);
                }

                // Sync permissions
                $role->syncPermissions($validated['permissions'] ?? []);

                // Update or create metadata
                RoleMeta::updateOrCreate(
                    ['role_name' => $role->name],
                    [
                        'description' => $validated['description'],
                        'is_core' => false,
                        'last_modified_by' => $request->user()->id,
                        'metadata' => [
                            'last_updated' => now(),
                            'updated_via' => 'dynamic_interface',
                            'permissions_count' => count($validated['permissions'] ?? [])
                        ]
                    ]
                );

                Log::info('Dynamic role updated:', [
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                    'old_name' => $oldName,
                    'updated_by' => $request->user()->id,
                    'permissions_count' => count($validated['permissions'] ?? []),
                ]);
            });

            return back()->with('success', 'Role updated successfully!');

        } catch (\Exception $e) {
            Log::error('Error updating dynamic role:', [
                'role_id' => $role->id,
                'message' => $e->getMessage(),
                'data' => $request->all(),
            ]);
            return back()->withErrors(['error' => 'Failed to update role.'])->withInput();
        }
    }

    public function destroy(Role $role)
    {
        try {
            $meta = RoleMeta::where('role_name', $role->name)->first();
            
            // Prevent deleting core roles
            if ($meta && $meta->is_core) {
                return back()->withErrors(['error' => 'Core roles cannot be deleted.']);
            }

            // Check if role is assigned to users
            $userCount = $role->users()->count();
            if ($userCount > 0) {
                return back()->withErrors(['error' => "Cannot delete role. It is assigned to {$userCount} users."]);
            }

            DB::transaction(function () use ($role) {
                // Delete metadata first
                RoleMeta::where('role_name', $role->name)->delete();
                
                // Delete role using Spatie method
                $role->delete();
            });

            Log::info('Dynamic role deleted:', [
                'role_id' => $role->id,
                'role_name' => $role->name,
            ]);

            return back()->with('success', 'Role deleted successfully!');

        } catch (\Exception $e) {
            Log::error('Error deleting dynamic role:', [
                'role_id' => $role->id,
                'message' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Failed to delete role.']);
        }
    }

    public function clone(Role $role)
    {
        try {
            $newRoleName = $role->name . ' (Copy)';
            $counter = 1;

            // Ensure unique name
            while (Role::where('name', $newRoleName)->exists()) {
                $newRoleName = $role->name . ' (Copy ' . $counter . ')';
                $counter++;
            }

            DB::transaction(function () use ($role, $newRoleName) {
                $newRole = Role::create([
                    'name' => $newRoleName,
                    'guard_name' => 'web'
                ]);

                // Copy permissions
                $permissions = $role->permissions->pluck('name')->toArray();
                if (!empty($permissions)) {
                    $newRole->givePermissionTo($permissions);
                }

                // Get original metadata
                $originalMeta = RoleMeta::where('role_name', $role->name)->first();

                // Create metadata for cloned role
                RoleMeta::create([
                    'role_name' => $newRole->name,
                    'description' => ($originalMeta ? $originalMeta->description : '') . ' (Cloned)',
                    'is_core' => false, // Cloned roles are always dynamic
                    'created_by_user_id' => auth()->id(),
                    'metadata' => [
                        'cloned_from' => $role->id,
                        'cloned_from_name' => $role->name,
                        'created_via' => 'clone_interface',
                        'created_at' => now(),
                        'permissions_count' => count($permissions)
                    ]
                ]);

                Log::info('Role cloned:', [
                    'original_role' => $role->name,
                    'new_role' => $newRole->name,
                    'created_by' => auth()->id(),
                ]);
            });

            return back()->with('success', 'Role cloned successfully!');

        } catch (\Exception $e) {
            Log::error('Error cloning role:', [
                'role_id' => $role->id,
                'message' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Failed to clone role.']);
        }
    }

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

    public function getRoleStats(Role $role)
    {
        $meta = RoleMeta::where('role_name', $role->name)->first();
        
        $stats = [
            'user_count' => $role->users()->count(),
            'permissions_count' => $role->permissions()->count(),
            'is_core' => $meta ? $meta->is_core : false,
            'description' => $meta ? $meta->description : null,
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at,
            'created_by' => $meta && $meta->createdBy ? $meta->createdBy->name : null,
            'last_modified_by' => $meta && $meta->lastModifiedBy ? $meta->lastModifiedBy->name : null,
        ];

        return response()->json($stats);
    }
}