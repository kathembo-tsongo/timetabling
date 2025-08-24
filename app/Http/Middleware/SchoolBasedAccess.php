<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SchoolBasedAccess
{
    /**
     * Handle school-based access using Spatie permissions
     */
    public function handle(Request $request, Closure $next, $schoolCode = null)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Super Admin can access everything
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
            return $next($request);
        }

        // Get user's school from their specific faculty admin role
        $userSchoolCode = $this->getUserSchoolFromRole($user);
        
        // Also check the schools column as fallback
        if (!$userSchoolCode && $user->schools) {
            $userSchoolCode = strtoupper($user->schools);
        }

        if (!$userSchoolCode) {
            return redirect()->route('dashboard')
                ->with('error', 'No faculty assignment found. Please contact administrator.');
        }

        // Extract school code from URL if not provided as parameter
        if (!$schoolCode) {
            $urlSegments = explode('/', trim($request->path(), '/'));
            $schoolCode = strtoupper($urlSegments[0] ?? '');
        } else {
            $schoolCode = strtoupper($schoolCode);
        }

        // Valid school codes
        $validSchools = ['SCES', 'SBS', 'SLS', 'SHS', 'TOURISM', 'SHM'];

        // Check if this is a school-specific route
        if (in_array($schoolCode, $validSchools)) {
            if ($userSchoolCode !== $schoolCode) {
                // Instead of aborting, redirect to their own faculty dashboard
                $userSchoolLower = strtolower($userSchoolCode);
                
                // Try to redirect to the same type of page in their faculty
                $currentPath = $request->path();
                $pathSegments = explode('/', $currentPath);
                
                if (count($pathSegments) > 1) {
                    // Replace the school code in the URL with user's school
                    $pathSegments[0] = $userSchoolLower;
                    $newPath = implode('/', $pathSegments);
                    
                    return redirect($newPath)
                        ->with('warning', "Redirected to your faculty area. You can only access {$userSchoolCode} data.");
                } else {
                    // Fallback to their dashboard
                    return redirect()->route('faculty.dashboard', ['school' => $userSchoolLower])
                        ->with('warning', "Access denied: You can only access {$userSchoolCode} data.");
                }
            }
        }

        // Set school context for the request
        $request->merge(['current_school_code' => $userSchoolCode]);

        return $next($request);
    }

    /**
     * Get user's school code from their faculty admin role
     */
    private function getUserSchoolFromRole($user)
    {
        $roles = $user->getRoleNames();

        foreach ($roles as $role) {
            if (str_starts_with($role, 'Faculty Admin - ')) {
                return str_replace('Faculty Admin - ', '', $role);
            }
        }

        return null;
    }
}