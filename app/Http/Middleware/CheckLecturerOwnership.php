<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\UnitAssignment;

class CheckLecturerOwnership
{
    /**
     * Handle an incoming request - ensures lecturers can only access their own data
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        // Admin and Faculty Admin bypass ownership checks
        if ($user->hasRole('Admin') || $this->isFacultyAdmin($user)) {
            return $next($request);
        }
        
        // Check if user is a lecturer
        if (!$user->hasRole('Lecturer')) {
            abort(403, 'Access denied. Lecturer role required.');
        }
        
        // If accessing a specific unit, verify ownership
        $unitId = $request->route('unitId') ?? $request->input('unit_id');
        $semesterId = $request->route('semester_id') ?? $request->input('semester_id');
        
        if ($unitId && $semesterId) {
            $isAssigned = UnitAssignment::where('lecturer_code', $user->code)
                ->where('unit_id', $unitId)
                ->where('semester_id', $semesterId)
                ->exists();
            
            if (!$isAssigned) {
                Log::warning('Lecturer attempted to access unauthorized unit', [
                    'lecturer_code' => $user->code,
                    'unit_id' => $unitId,
                    'semester_id' => $semesterId
                ]);
                
                abort(403, 'Access denied. You are not assigned to this unit.');
            }
        }
        
        return $next($request);
    }
    
    private function isFacultyAdmin($user)
    {
        if (method_exists($user, 'roles')) {
            $userRoles = $user->roles()->get();
            foreach ($userRoles as $role) {
                if (str_contains($role->name, 'Faculty Admin')) {
                    return true;
                }
            }
        }
        return false;
    }
}
