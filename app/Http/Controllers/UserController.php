<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of users with pagination and search
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search', '');

        $users = User::query()
            ->when($search, function ($query, $search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('schools', 'like', "%{$search}%");
            })
            ->paginate($perPage); // Removed roles loading since we're not showing them

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'perPage' => $perPage,
            'search' => $search,
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        try {
            Log::info('Creating user with data:', $request->all());

            // Validate request - removed roles validation
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'phone' => 'required|string|max:255',
                'code' => 'required|string|max:255|unique:users',
                'password' => 'required|string|min:8',
                'schools' => 'nullable|string|max:255',
                'programs' => 'nullable|string|max:255',
            ]);

            // Create user
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'code' => $validated['code'],
                'password' => Hash::make($validated['password']),
                'schools' => $validated['schools'] ?? null,
                'programs' => $validated['programs'] ?? null,
            ]);

            // Optional: Assign a default role if you still want users to have roles in the background
            // $user->assignRole('Student'); // Uncomment if you want to assign a default role

            Log::info('User created successfully:', ['user_id' => $user->id]);

            return redirect()->route('admin.users.index')
                ->with('success', 'User created successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error creating user:', ['errors' => $e->errors()]);
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Error creating user:', ['message' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to create user: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Show the form for editing a user
     */
    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Edit', ['user' => $user]); // Removed roles loading
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        try {
            Log::info('Updating user with data:', [
                'user_id' => $user->id,
                'request_data' => $request->all()
            ]);

            // Validate request - removed roles validation
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email,' . $user->id,
                'phone' => 'required|string|max:255',
                'code' => 'required|string|max:255|unique:users,code,' . $user->id,
                'schools' => 'nullable|string|max:255',
                'programs' => 'nullable|string|max:255',
            ]);

            Log::info('Validation passed, updating user:', ['validated' => $validated]);

            // Update user basic info
            $user->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'code' => $validated['code'],
                'schools' => $validated['schools'] ?? null,
                'programs' => $validated['programs'] ?? null,
            ]);

            // Note: Roles are no longer updated here since the interface doesn't manage them

            Log::info('User updated successfully:', ['user_id' => $user->id]);

            return redirect()->route('admin.users.index')
                ->with('success', 'User updated successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error updating user:', [
                'user_id' => $user->id,
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Error updating user:', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['error' => 'Failed to update user: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user)
    {
        try {
            Log::info('Deleting user:', ['user_id' => $user->id]);

            $user->delete();

            Log::info('User deleted successfully:', ['user_id' => $user->id]);

            return redirect()->route('admin.users.index')
                ->with('success', 'User deleted successfully!');

        } catch (\Exception $e) {
            Log::error('Error deleting user:', [
                'user_id' => $user->id,
                'message' => $e->getMessage()
            ]);
            return back()->withErrors(['error' => 'Failed to delete user: ' . $e->getMessage()]);
        }
    }

    /**
     * Show form to change user's role
     */
    public function editRole(User $user)
    {
        $roles = Role::pluck('name');
        $currentRole = $user->roles->pluck('name')->first();

        return Inertia::render('Admin/Users/EditRole', [
            'user' => $user,
            'roles' => $roles,
            'currentRole' => $currentRole,
        ]);
    }

    /**
     * Update user's role (separate method for role-only updates)
     */
    public function updateRole(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'role' => 'required|exists:roles,name',
            ]);

            $user->syncRoles([$validated['role']]);

            return redirect()->route('admin.users.index')
                ->with('success', 'User role updated successfully!');

        } catch (\Exception $e) {
            Log::error('Error updating user role:', [
                'user_id' => $user->id,
                'message' => $e->getMessage()
            ]);
            return back()->withErrors(['error' => 'Failed to update user role: ' . $e->getMessage()]);
        }
    }

    /**
     * Get user roles and permissions for API
     */
    public function getUserRolesAndPermissions(Request $request)
    {
        try {
            $user = $request->user();
            return response()->json([
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting user roles and permissions:', [
                'message' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Failed to get user roles and permissions'], 500);
        }
    }

    /**
     * Update user roles and permissions via API
     */
    public function updateUserRolesAndPermissions(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'roles' => 'array',
                'permissions' => 'array',
            ]);

            $user->syncRoles($validated['roles'] ?? []);
            $user->syncPermissions($validated['permissions'] ?? []);

            return response()->json(['message' => 'User roles and permissions updated successfully.']);

        } catch (\Exception $e) {
            Log::error('Error updating user roles and permissions:', [
                'user_id' => $user->id,
                'message' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Failed to update user roles and permissions'], 500);
        }
    }

    /**
     * Get user permissions
     */
    public function getUserPermissions(User $user)
    {
        try {
            $permissions = $user->getAllPermissions();
            return response()->json($permissions);
        } catch (\Exception $e) {
            Log::error('Error getting user permissions:', [
                'user_id' => $user->id,
                'message' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Failed to get user permissions'], 500);
        }
    }
}