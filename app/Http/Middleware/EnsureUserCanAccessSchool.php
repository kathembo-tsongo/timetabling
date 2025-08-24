<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserCanAccessSchool
{
    /**
     * Ensure user can only access their assigned school routes
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Super Admin can access everything
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
            return $next($request);
        }

        // Get user's school code
        $userSchoolCode = $this->getUserSchoolCode($user);

        if (!$userSchoolCode) {
            return redirect()->route('dashboard')
                ->with('error', 'No faculty assignment found. Please contact administrator.');
        }

        // Extract school from URL
        $urlSegments = explode('/', trim($request->path(), '/'));
        $requestedSchool = strtoupper($urlSegments[0] ?? '');

        // Valid school codes
        $validSchools = ['SCES', 'SBS', 'SLS', 'SHS', 'TOURISM', 'SHM'];

        if (in_array($requestedSchool, $validSchools)) {
            if ($userSchoolCode !== $requestedSchool) {
                // Redirect to their own faculty's equivalent page
                return $this->redirectToUserSchool($request, $userSchoolCode);
            }
        }

        // Add school context to request
        $request->merge(['current_school_code' => $userSchoolCode]);

        return $next($request);
    }

    /**
     * Get user's school code from role or schools column
     */
    private function getUserSchoolCode($user)
    {
        // First try to get from role
        $roles = $user->getRoleNames();
        foreach ($roles as $role) {
            if (str_starts_with($role, 'Faculty Admin - ')) {
                return str_replace('Faculty Admin - ', '', $role);
            }
        }

        // Fallback to schools column
        return $user->schools ? strtoupper($user->schools) : null;
    }

    /**
     * Redirect user to their own school's equivalent page
     */
    private function redirectToUserSchool($request, $userSchoolCode)
    {
        $currentPath = $request->path();
        $pathSegments = explode('/', $currentPath);
        
        if (count($pathSegments) > 1) {
            // Replace school code with user's school
            $pathSegments[0] = strtolower($userSchoolCode);
            $newPath = implode('/', $pathSegments);
            
            return redirect($newPath)
                ->with('info', "Redirected to your faculty area ({$userSchoolCode}).");
        }

        // Fallback to dashboard
        return redirect()->route('faculty.dashboard', ['school' => strtolower($userSchoolCode)])
            ->with('info', "You can only access {$userSchoolCode} data.");
    }
}