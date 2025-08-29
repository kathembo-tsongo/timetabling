<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use App\Models\RoleMeta;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RoleManagementController extends Controller
{
    /**
     * Display users grouped by roles with filtering and search
     */
    public function index(Request $request)
    {
        $selectedRole = $request->get('role', 'all');
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search', '');

        // Get all available roles with metadata
        $roles = Role::all()->map(function ($role) {
            $meta = RoleMeta::where('role_name', $role->name)->first();
            $role->description = $meta ? $meta->description : null;
            $role->is_core = $meta ? $meta->is_core : false;
            return $role;
        });

        // Build users query
        $usersQuery = User::query()
            ->with('roles')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('schools', 'like', "%{$search}%")
                      ->orWhere('programs', 'like', "%{$search}%");
                });
            });

        // Filter by role if selected
        if ($selectedRole === 'no_role') {
            $usersQuery->doesntHave('roles');
        } elseif ($selectedRole !== 'all') {
            $usersQuery->whereHas('roles', function ($query) use ($selectedRole) {
                $query->where('name', $selectedRole);
            });
        }

        $users = $usersQuery->paginate($perPage);

        // Get role counts for statistics
        $roleCounts = [];
        foreach ($roles as $role) {
            $roleCounts[$role->name] = User::whereHas('roles', function ($query) use ($role) {
                $query->where('name', $role->name);
            })->count();
        }

        // Add count for users without roles
        $roleCounts['no_role'] = User::doesntHave('roles')->count();
        $roleCounts['total'] = User::count();

        return Inertia::render('Admin/Roles/UsersByRole', [
            'users' => $users,
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'roleCounts' => $roleCounts,
            'perPage' => $perPage,
            'search' => $search,
        ]);
    }

    /**
     * Update user role (single role assignment)
     */
    public function updateUserRole(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'role' => 'required|exists:roles,name',
            ]);

            DB::transaction(function () use ($validated, $user, $request) {
                // Sync to single role (replaces all existing roles)
                $user->syncRoles([$validated['role']]);

                Log::info('User role updated:', [
                    'user_id' => $user->id,
                    'user_code' => $user->code,
                    'new_role' => $validated['role'],
                    'updated_by' => $request->user()->id,
                ]);
            });

            return back()->with('success', 'User role updated successfully!');

        } catch (\Exception $e) {
            Log::error('Error updating user role:', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'requested_role' => $request->input('role'),
            ]);
            return back()->withErrors(['error' => 'Failed to update user role.']);
        }
    }

    /**
     * Add role to user (multiple roles support)
     */
    public function addUserRole(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'role' => 'required|exists:roles,name',
            ]);

            DB::transaction(function () use ($validated, $user, $request) {
                // Add role without removing existing ones
                $user->assignRole($validated['role']);

                Log::info('Role added to user:', [
                    'user_id' => $user->id,
                    'user_code' => $user->code,
                    'added_role' => $validated['role'],
                    'updated_by' => $request->user()->id,
                ]);
            });

            return back()->with('success', 'Role added to user successfully!');

        } catch (\Exception $e) {
            Log::error('Error adding role to user:', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'requested_role' => $request->input('role'),
            ]);
            return back()->withErrors(['error' => 'Failed to add role to user.']);
        }
    }

    /**
     * Remove specific role from user
     */
    public function removeSpecificRole(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'role' => 'required|exists:roles,name',
            ]);

            DB::transaction(function () use ($validated, $user, $request) {
                $user->removeRole($validated['role']);

                Log::info('Role removed from user:', [
                    'user_id' => $user->id,
                    'user_code' => $user->code,
                    'removed_role' => $validated['role'],
                    'updated_by' => $request->user()->id,
                ]);
            });

            return back()->with('success', 'Role removed from user successfully!');

        } catch (\Exception $e) {
            Log::error('Error removing role from user:', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'requested_role' => $request->input('role'),
            ]);
            return back()->withErrors(['error' => 'Failed to remove role from user.']);
        }
    }

    /**
     * Remove all roles from user
     */
    public function removeUserRole(User $user)
    {
        try {
            DB::transaction(function () use ($user) {
                $previousRoles = $user->roles->pluck('name')->toArray();
                $user->roles()->detach();

                Log::info('All roles removed from user:', [
                    'user_id' => $user->id,
                    'user_code' => $user->code,
                    'previous_roles' => $previousRoles,
                ]);
            });

            return back()->with('success', 'All roles removed from user successfully!');

        } catch (\Exception $e) {
            Log::error('Error removing user roles:', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Failed to remove user roles.']);
        }
    }

    /**
     * Bulk assign role to multiple users
     */
    public function bulkAssignRole(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'exists:users,id',
                'role' => 'required|exists:roles,name',
                'action' => 'required|in:replace,add', // replace all roles or add to existing
            ]);

            $users = User::whereIn('id', $validated['user_ids'])->get();
            $successCount = 0;
            $failedUsers = [];

            DB::transaction(function () use ($users, $validated, $request, &$successCount, &$failedUsers) {
                foreach ($users as $user) {
                    try {
                        if ($validated['action'] === 'replace') {
                            $user->syncRoles([$validated['role']]);
                        } else {
                            $user->assignRole($validated['role']);
                        }
                        $successCount++;
                    } catch (\Exception $e) {
                        $failedUsers[] = [
                            'user' => $user->code,
                            'error' => $e->getMessage()
                        ];
                    }
                }

                Log::info('Bulk role assignment completed:', [
                    'role' => $validated['role'],
                    'action' => $validated['action'],
                    'success_count' => $successCount,
                    'failed_count' => count($failedUsers),
                    'updated_by' => $request->user()->id,
                ]);
            });

            $message = "Role {$validated['role']} " . 
                      ($validated['action'] === 'replace' ? 'assigned' : 'added') . 
                      " to {$successCount} users successfully!";

            if (count($failedUsers) > 0) {
                $message .= " {$count($failedUsers)} assignments failed.";
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Error in bulk role assignment:', [
                'message' => $e->getMessage(),
                'data' => $request->all(),
            ]);
            return back()->withErrors(['error' => 'Failed to assign roles to users.']);
        }
    }

    /**
     * Bulk remove role from multiple users
     */
    public function bulkRemoveRole(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'exists:users,id',
                'role' => 'required|exists:roles,name',
            ]);

            $users = User::whereIn('id', $validated['user_ids'])
                        ->whereHas('roles', function ($query) use ($validated) {
                            $query->where('name', $validated['role']);
                        })->get();

            $successCount = 0;
            $failedUsers = [];

            DB::transaction(function () use ($users, $validated, $request, &$successCount, &$failedUsers) {
                foreach ($users as $user) {
                    try {
                        $user->removeRole($validated['role']);
                        $successCount++;
                    } catch (\Exception $e) {
                        $failedUsers[] = [
                            'user' => $user->code,
                            'error' => $e->getMessage()
                        ];
                    }
                }

                Log::info('Bulk role removal completed:', [
                    'role' => $validated['role'],
                    'success_count' => $successCount,
                    'failed_count' => count($failedUsers),
                    'updated_by' => $request->user()->id,
                ]);
            });

            $message = "Role {$validated['role']} removed from {$successCount} users successfully!";

            if (count($failedUsers) > 0) {
                $message .= " " . count($failedUsers) . " removals failed.";
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Error in bulk role removal:', [
                'message' => $e->getMessage(),
                'data' => $request->all(),
            ]);
            return back()->withErrors(['error' => 'Failed to remove roles from users.']);
        }
    }

    /**
     * Get user role history/audit trail
     */
    public function getUserRoleHistory(User $user)
    {
        try {
            // This would require an audit table to track role changes over time
            // For now, return current roles with metadata
            $currentRoles = $user->roles->map(function ($role) {
                $meta = RoleMeta::where('role_name', $role->name)->first();
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $meta ? $meta->description : null,
                    'is_core' => $meta ? $meta->is_core : false,
                    'assigned_at' => $role->pivot->created_at ?? null,
                ];
            });

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'code' => $user->code,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                ],
                'current_roles' => $currentRoles,
                'roles_count' => $currentRoles->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting user role history:', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to get user role history.'], 500);
        }
    }

    /**
     * Get role assignment statistics
     */
    public function getRoleStatistics()
    {
        try {
            $roles = Role::all();
            $statistics = [];

            foreach ($roles as $role) {
                $meta = RoleMeta::where('role_name', $role->name)->first();
                $userCount = $role->users()->count();
                
                $statistics[] = [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $meta ? $meta->description : null,
                    'is_core' => $meta ? $meta->is_core : false,
                    'user_count' => $userCount,
                    'permissions_count' => $role->permissions()->count(),
                    'created_at' => $role->created_at,
                ];
            }

            // Sort by user count descending
            usort($statistics, function ($a, $b) {
                return $b['user_count'] - $a['user_count'];
            });

            $totalUsers = User::count();
            $usersWithRoles = User::has('roles')->count();
            $usersWithoutRoles = $totalUsers - $usersWithRoles;

            return response()->json([
                'role_statistics' => $statistics,
                'summary' => [
                    'total_users' => $totalUsers,
                    'users_with_roles' => $usersWithRoles,
                    'users_without_roles' => $usersWithoutRoles,
                    'total_roles' => $roles->count(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting role statistics:', [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Failed to get role statistics.'], 500);
        }
    }

    /**
     * Export user-role assignments
     */
    public function exportUserRoles(Request $request)
    {
        try {
            $format = $request->get('format', 'csv'); // csv, json
            $roleFilter = $request->get('role', 'all');

            $usersQuery = User::with('roles');
            
            if ($roleFilter !== 'all') {
                if ($roleFilter === 'no_role') {
                    $usersQuery->doesntHave('roles');
                } else {
                    $usersQuery->whereHas('roles', function ($query) use ($roleFilter) {
                        $query->where('name', $roleFilter);
                    });
                }
            }

            $users = $usersQuery->get();

            $exportData = $users->map(function ($user) {
                return [
                    'user_id' => $user->id,
                    'code' => $user->code,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'schools' => $user->schools,
                    'programs' => $user->programs,
                    'roles' => $user->roles->pluck('name')->implode(', '),
                    'roles_count' => $user->roles->count(),
                ];
            });

            if ($format === 'json') {
                return response()->json([
                    'data' => $exportData,
                    'exported_at' => now(),
                    'total_records' => $exportData->count(),
                ]);
            }

            // CSV format
            $filename = 'user_roles_export_' . now()->format('Y-m-d_H-i-s') . '.csv';
            
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
            ];

            $callback = function() use ($exportData) {
                $file = fopen('php://output', 'w');
                
                // CSV headers
                fputcsv($file, [
                    'User ID', 'Code', 'First Name', 'Last Name', 'Email', 'Phone', 
                    'Schools', 'Programs', 'Roles', 'Roles Count'
                ]);

                // CSV data
                foreach ($exportData as $row) {
                    fputcsv($file, $row->toArray());
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Error exporting user roles:', [
                'message' => $e->getMessage(),
            ]);
            return back()->withErrors(['error' => 'Failed to export user roles.']);
        }
    }
}