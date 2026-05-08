<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SchoolBasedAccess
{
    public function handle(Request $request, Closure $next, $schoolCode = null)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Admin roles bypass school check
        if ($user->hasRole('Super Admin') || $user->hasRole('Admin')) {
            return $next($request);
        }

        // Get user's school code
        $userSchoolCode = $this->getUserSchoolFromRole($user)
            ?? ($user->schools ? strtoupper($user->schools) : null);

        if (!$userSchoolCode) {
            return redirect()->route('dashboard')
                ->with('error', 'No faculty assignment found. Please contact administrator.');
        }

        // Load valid schools DYNAMICALLY from database
        $validSchools = DB::table('schools')
            ->when(app()->has('tenant'), fn($q) => $q->where('tenant_id', tenant()->id))
            ->where('is_active', true)
            ->pluck('code')
            ->map(fn($c) => strtoupper($c))
            ->toArray();

        // Resolve school code from URL if not passed as parameter
        if (!$schoolCode) {
            $urlSegments = explode('/', trim($request->path(), '/'));
            $schoolCode  = strtoupper($urlSegments[0] ?? '');
        } else {
            $schoolCode = strtoupper($schoolCode);
        }

        // If this is a school-specific route, check access
        if (in_array($schoolCode, $validSchools)) {
            if ($userSchoolCode !== $schoolCode) {
                $pathSegments    = explode('/', $request->path());
                $pathSegments[0] = strtolower($userSchoolCode);
                $newPath         = implode('/', $pathSegments);

                return redirect($newPath)
                    ->with('warning', "Redirected to your faculty ({$userSchoolCode}).");
            }
        }

        $request->merge(['current_school_code' => $userSchoolCode]);
        return $next($request);
    }

    private function getUserSchoolFromRole($user): ?string
    {
        foreach ($user->getRoleNames() as $role) {
            if (str_starts_with($role, 'Faculty Admin - ')) {
                return str_replace('Faculty Admin - ', '', $role);
            }
        }
        return null;
    }
}
