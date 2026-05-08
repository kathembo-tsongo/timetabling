<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EnsureUserCanAccessSchool
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        // Admin roles can access everything
        if ($user->hasRole('Admin') || $user->hasRole('Super Admin')) {
            return $next($request);
        }

        // Get user's school code from role or schools column
        $userSchoolCode = $this->getUserSchoolCode($user);
        if (!$userSchoolCode) {
            return redirect()->route('dashboard')
                ->with('error', 'No faculty assignment found. Please contact administrator.');
        }

        // Load valid school codes DYNAMICALLY from database (not hardcoded)
        $validSchools = DB::table('schools')
            ->where('tenant_id', app()->has('tenant') ? tenant()->id : null)
            ->where('is_active', true)
            ->pluck('code')
            ->map(fn($c) => strtoupper($c))
            ->toArray();

        // Extract school from URL
        $urlSegments  = explode('/', trim($request->path(), '/'));
        $requestedSchool = strtoupper($urlSegments[0] ?? '');

        if (in_array($requestedSchool, $validSchools)) {
            if (strtoupper($userSchoolCode) !== $requestedSchool) {
                return $this->redirectToUserSchool($request, $userSchoolCode);
            }
        }

        $request->merge(['current_school_code' => $userSchoolCode]);
        return $next($request);
    }

    private function getUserSchoolCode($user): ?string
    {
        // Try role first
        foreach ($user->getRoleNames() as $role) {
            if (str_starts_with($role, 'Faculty Admin - ')) {
                return str_replace('Faculty Admin - ', '', $role);
            }
        }
        // Fallback to schools column
        return $user->schools ? strtoupper($user->schools) : null;
    }

    private function redirectToUserSchool(Request $request, string $userSchoolCode)
    {
        $pathSegments    = explode('/', $request->path());
        $pathSegments[0] = strtolower($userSchoolCode);
        $newPath         = implode('/', $pathSegments);

        return redirect($newPath)
            ->with('info', "Redirected to your faculty area ({$userSchoolCode}).");
    }
}
