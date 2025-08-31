<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DynamicRoleController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $filter = $request->get('filter', 'all');
        $perPage = $request->get('per_page', 20);

        // Build roles query
        $rolesQuery = Role::query()
            ->with(['permissions'])
            ->withCount(['users', 'permissions'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                    if (method_exists(Role::class, 'description')) {
                        $q->orWhere('description', 'like', "%{$search}%");
                    }
                });
            });

        // Apply filter
        if ($filter !== 'all') {
            if ($filter === 'core') {
                // Check if is_core column exists, otherwise use name patterns
                if (\Schema::hasColumn('roles', 'is_core')) {
                    $rolesQuery->where('is_core', true);
                } else {
                    // Fallback: assume core roles based on common names
                    $rolesQuery->whereIn('name', ['Admin', 'Student', 'Lecturer', 'Exam Office', 'Faculty Admin']);
                }
            } elseif ($filter === 'dynamic') {
                if (\Schema::hasColumn('roles', 'is_core')) {
                    $rolesQuery->where('is_core', false);
                } else {
                    // Fallback: exclude common core role names
                    $rolesQuery->whereNotIn('name', ['Admin', 'Student', 'Lecturer', 'Exam Office', 'Faculty Admin']);
                }
            }
        }

        $roles = $rolesQuery->orderBy('name')->paginate($perPage);

        // Get all permissions for role creation/editing
        $permissions = Permission::orderBy('name')->get();

        // Calculate stats
        $totalRoles = Role::count();
        $coreRolesCount = 0;
        $dynamicRolesCount = 0;

        if (\Schema::hasColumn('roles', 'is_core')) {
            $coreRolesCount = Role::where('is_core', true)->count();
            $dynamicRolesCount = Role::where('is_core', false)->count();
        } else {
            // Fallback logic
            $coreRoleNames = ['Admin', 'Student', 'Lecturer', 'Exam Office', 'Faculty Admin'];
            $coreRolesCount = Role::whereIn('name', $coreRoleNames)->count();
            $dynamicRolesCount = $totalRoles - $coreRolesCount;
        }

        $stats = [
            'total' => $totalRoles,
            'core' => $coreRolesCount,
            'dynamic' => $dynamicRolesCount,
        ];

        return Inertia::render('Admin/Roles/DynamicRoles', [
            'roles' => $roles,
            'permissions' => $permissions,
            'stats' => $stats,
            'filter' => $filter,
            'search' => $search,
            'perPage' => (int) $perPage,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $roleData = [
            'name' => $validated['name'],
            'guard_name' => 'web',
        ];

        // Add description if the column exists
        if (\Schema::hasColumn('roles', 'description')) {
            $roleData['description'] = $validated['description'] ?? null;
        }

        // Add is_core if the column exists
        if (\Schema::hasColumn('roles', 'is_core')) {
            $roleData['is_core'] = false; // Dynamic roles are not core
        }

        $role = Role::create($roleData);

        // Assign permissions if provided
        if (!empty($validated['permissions'])) {
            $role->givePermissionTo($validated['permissions']);
        }

        return back()->with('success', 'Role created successfully!');
    }

    public function update(Request $request, Role $role)
    {
        // Prevent editing core roles
        $isCoreRole = false;
        if (\Schema::hasColumn('roles', 'is_core')) {
            $isCoreRole = $role->is_core;
        } else {
            // Fallback: check if it's a core role by name
            $coreRoleNames = ['Admin', 'Student', 'Lecturer', 'Exam Office', 'Faculty Admin'];
            $isCoreRole = in_array($role->name, $coreRoleNames);
        }

        if ($isCoreRole) {
            return back()->withErrors(['error' => 'Core roles cannot be modified.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id . '|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $updateData = [
            'name' => $validated['name'],
        ];

        // Add description if the column exists
        if (\Schema::hasColumn('roles', 'description')) {
            $updateData['description'] = $validated['description'] ?? null;
        }

        $role->update($updateData);

        // Sync permissions
        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        } else {
            $role->syncPermissions([]);
        }

        return back()->with('success', 'Role updated successfully!');
    }

    public function updatePermissions(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        // Sync permissions
        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', 'Role permissions updated successfully!');
    }

    public function destroy(Role $role)
    {
        // Prevent deleting core roles
        $isCoreRole = false;
        if (\Schema::hasColumn('roles', 'is_core')) {
            $isCoreRole = $role->is_core;
        } else {
            // Fallback: check if it's a core role by name
            $coreRoleNames = ['Admin', 'Student', 'Lecturer', 'Exam Office', 'Faculty Admin'];
            $isCoreRole = in_array($role->name, $coreRoleNames);
        }

        if ($isCoreRole) {
            return back()->withErrors(['error' => 'Core roles cannot be deleted.']);
        }

        // Check if role has users
        if ($role->users()->count() > 0) {
            return back()->withErrors(['error' => 'Cannot delete role that is assigned to users.']);
        }

        $role->delete();

        return back()->with('success', 'Role deleted successfully!');
    }

    public function bulkCreate(Request $request)
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*.name' => 'required|string|unique:roles,name|max:255',
            'roles.*.description' => 'nullable|string|max:500',
            'roles.*.permissions' => 'array',
            'roles.*.permissions.*' => 'exists:permissions,name'
        ]);

        $createdRoles = [];

        foreach ($validated['roles'] as $roleData) {
            $newRoleData = [
                'name' => $roleData['name'],
                'guard_name' => 'web',
            ];

            // Add description if the column exists
            if (\Schema::hasColumn('roles', 'description')) {
                $newRoleData['description'] = $roleData['description'] ?? null;
            }

            // Add is_core if the column exists
            if (\Schema::hasColumn('roles', 'is_core')) {
                $newRoleData['is_core'] = false;
            }

            $role = Role::create($newRoleData);

            // Assign permissions if provided
            if (!empty($roleData['permissions'])) {
                $role->givePermissionTo($roleData['permissions']);
            }

            $createdRoles[] = $role;
        }

        return back()->with('success', count($createdRoles) . ' roles created successfully!');
    }

    public function create()
    {
        $permissions = Permission::orderBy('name')->get();
        
        return Inertia::render('Admin/Roles/Create', [
            'permissions' => $permissions,
        ]);
    }

    public function edit(Role $role)
    {
        $role->load('permissions');
        $permissions = Permission::orderBy('name')->get();

        return Inertia::render('Admin/Roles/Edit', [
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }

    public function clone(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $newRoleData = [
            'name' => $validated['name'],
            'guard_name' => 'web',
        ];

        // Add description if the column exists
        if (\Schema::hasColumn('roles', 'description')) {
            $newRoleData['description'] = $validated['description'] ?? null;
        }

        // Add is_core if the column exists
        if (\Schema::hasColumn('roles', 'is_core')) {
            $newRoleData['is_core'] = false; // Cloned roles are always dynamic
        }

        $newRole = Role::create($newRoleData);

        // Copy all permissions from the original role
        $permissions = $role->permissions->pluck('name')->toArray();
        if (!empty($permissions)) {
            $newRole->givePermissionTo($permissions);
        }

        return back()->with('success', 'Role cloned successfully!');
    }
}