<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoadUserPermissions
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $user = $request->user();
        
            try {
                // Force reload the user with relationships
                $user->load('roles.permissions', 'permissions');
                
                // Clear any cached permissions
                app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
                
                // Get all permissions (direct + role-based)
                $allPermissions = $user->getAllPermissions()->pluck('name')->toArray();
                $directPermissions = $user->getDirectPermissions()->pluck('name')->toArray();
                $rolePermissions = $user->getPermissionsViaRoles()->pluck('name')->toArray();
                $roles = $user->getRoleNames()->toArray();

                // Log for debugging with correct permission names
                \Log::info('LoadUserPermissions middleware', [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'roles' => $roles,
                    'roles_count' => count($roles),
                    'all_permissions_count' => count($allPermissions),
                    'all_permissions' => $allPermissions,
                    'direct_permissions_count' => count($directPermissions),
                    'direct_permissions' => $directPermissions,
                    'role_permissions_count' => count($rolePermissions),
                    'role_permissions' => $rolePermissions,
                    'has_admin_role' => $user->hasRole('Admin'),
                    'can_manage_users' => $user->can('manage-users'), // Use hyphen
                    'can_manage_roles' => $user->can('manage-roles'), // Use hyphen
                ]);

                // Share comprehensive user data with Inertia
                Inertia::share([
                    'auth.user.permissions' => $allPermissions,
                    'auth.user.direct_permissions' => $directPermissions,
                    'auth.user.role_permissions' => $rolePermissions,
                    'auth.user.roles' => $roles,
                    'auth.user.has_admin_role' => $user->hasRole('Admin'),
                    'auth.user.permission_count' => count($allPermissions),
                    'auth.user.can_manage_users' => $user->can('manage-users'), // Use hyphen
                    'auth.user.can_manage_roles' => $user->can('manage-roles'), // Use hyphen
                    'auth.user.debug_info' => [
                        'middleware_loaded' => true,
                        'timestamp' => now()->toISOString(),
                        'permissions_loaded' => count($allPermissions) > 0,
                    ]
                ]);
                
            } catch (\Exception $e) {
                \Log::error('Error in LoadUserPermissions middleware', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Share empty data in case of error
                Inertia::share([
                    'auth.user.permissions' => [],
                    'auth.user.roles' => [],
                    'auth.user.debug_info' => [
                        'middleware_error' => $e->getMessage(),
                        'timestamp' => now()->toISOString(),
                    ]
                ]);
            }
        }

        return $next($request);
    }
}
