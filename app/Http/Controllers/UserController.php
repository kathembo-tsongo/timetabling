<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $roleFilter = $request->get('role', '');
        $statusFilter = $request->get('status', '');
        $perPage = $request->get('per_page', 15);

        $usersQuery = User::query()
            ->with(['roles'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($roleFilter, function ($query, $role) {
                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('name', $role);
                });
            })
            ->when($statusFilter, function ($query, $status) {
                switch ($status) {
                    case 'verified':
                        $query->whereNotNull('email_verified_at');
                        break;
                    case 'unverified':
                        $query->whereNull('email_verified_at');
                        break;
                }
            })
            ->orderBy('created_at', 'desc');

        $users = $usersQuery->paginate($perPage);

        // Get all roles for filter dropdown
        $roles = Role::orderBy('name')->get();

        // Calculate stats
        $stats = [
            'total' => User::count(),
            'active' => User::whereNotNull('email_verified_at')->count(),
            'inactive' => User::whereNull('email_verified_at')->count(),
            'with_roles' => User::whereHas('roles')->count(),
        ];

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $roleFilter,
                'status' => $statusFilter,
                'per_page' => (int) $perPage,
            ],
            'roles' => $roles,
            'stats' => $stats,
        ]);
    }

    // NEW METHOD: User Roles Management Page
    public function roles(Request $request)
    {
        $search = $request->get('search', '');
        $roleFilter = $request->get('role', 'all');
        $perPage = $request->get('per_page', 15);

        // Build the users query
        $usersQuery = User::query()
            ->with(['roles'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            });

        // Apply role filter
        if ($roleFilter !== 'all') {
            if ($roleFilter === 'no_role') {
                $usersQuery->whereDoesntHave('roles');
            } else {
                $usersQuery->whereHas('roles', function ($q) use ($roleFilter) {
                    $q->where('name', $roleFilter);
                });
            }
        }

        $users = $usersQuery->orderBy('created_at', 'desc')->paginate($perPage);

        // Get all roles
        $roles = Role::orderBy('name')->get();

        // Calculate role counts
        $roleCounts = [
            'total' => User::count(),
            'no_role' => User::whereDoesntHave('roles')->count(),
        ];

        // Add count for each role
        foreach ($roles as $role) {
            $roleCounts[$role->name] = $role->users()->count();
        }

        return Inertia::render('Admin/UserRoles', [
            'users' => $users,
            'roles' => $roles,
            'selectedRole' => $roleFilter,
            'roleCounts' => $roleCounts,
            'perPage' => (int) $perPage,
            'search' => $search,
        ]);
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();
        
        return Inertia::render('Admin/Users/Create', [
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:users,code',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255', 
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id'
        ]);

        $user = User::create([
            'code' => $validated['code'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => bcrypt($validated['password']),
        ]);

        // Assign roles using Spatie
        if (!empty($validated['roles'])) {
            $roleNames = Role::whereIn('id', $validated['roles'])->pluck('name');
            $user->assignRole($roleNames);
        }

        return back()->with('success', 'User created successfully!');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:users,code,' . $user->id,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id'
        ]);

        $updateData = [
            'code' => $validated['code'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ];

        // Only update password if provided
        if (!empty($validated['password'])) {
            $updateData['password'] = bcrypt($validated['password']);
        }

        $user->update($updateData);

        // Sync roles using Spatie
        if (isset($validated['roles'])) {
            $roleNames = Role::whereIn('id', $validated['roles'])->pluck('name');
            $user->syncRoles($roleNames);
        }

        return back()->with('success', 'User updated successfully!');
    }

    public function edit(User $user)
    {
        $user->load('roles');
        $roles = Role::orderBy('name')->get();

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    // NEW METHOD: Update individual user role
    public function updateUserRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'nullable|string|exists:roles,name'
        ]);

        // Remove all current roles and assign the new one
        $user->syncRoles($validated['role'] ? [$validated['role']] : []);

        return back()->with('success', 'User role updated successfully!');
    }

    // NEW METHOD: Remove all roles from user
    public function removeUserRole(User $user)
    {
        $user->syncRoles([]);
        
        return back()->with('success', 'User roles removed successfully!');
    }

    // NEW METHOD: Bulk assign roles
    public function bulkAssignRole(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'role' => 'required|string|exists:roles,name',
            'action' => 'required|in:replace,add'
        ]);

        $users = User::whereIn('id', $validated['user_ids'])->get();

        foreach ($users as $user) {
            if ($validated['action'] === 'replace') {
                $user->syncRoles([$validated['role']]);
            } else {
                $user->assignRole($validated['role']);
            }
        }

        return back()->with('success', 'Roles assigned to ' . count($users) . ' users successfully!');
    }

    public function destroy(User $user)
    {
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return back()->with('success', 'User deleted successfully!');
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        // Prevent deleting yourself
        if (in_array(auth()->id(), $validated['user_ids'])) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        User::whereIn('id', $validated['user_ids'])->delete();

        return back()->with('success', count($validated['user_ids']) . ' users deleted successfully!');
    }
}