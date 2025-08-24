<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!Auth::check()) {
            return redirect('login');
        }

        // Get the user's roles
        $userRoles = Auth::user()->getRoleNames();
        
        // Check if the user has the required role
        if (!$userRoles->contains($role)) {
            // Log the issue for debugging
            \Log::warning('Role check failed', [
                'user_id' => Auth::id(),
                'user_roles' => $userRoles,
                'required_role' => $role
            ]);
            
            // Redirect to appropriate page or show forbidden
            return redirect()->route('dashboard')->with('error', 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
