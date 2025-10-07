<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Enrollment;

class CheckStudentOwnership
{
    /**
     * Handle an incoming request - ensures students can only access their own data
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        // Admin bypass ownership checks
        if ($user->hasRole('Admin')) {
            return $next($request);
        }
        
        // Check if user is a student
        if (!$user->hasRole('Student')) {
            abort(403, 'Access denied. Student role required.');
        }
        
        // If accessing a specific enrollment, verify ownership
        $enrollmentId = $request->route('enrollment');
        
        if ($enrollmentId) {
            $enrollment = Enrollment::find($enrollmentId);
            
            if (!$enrollment || $enrollment->student_code !== $user->code) {
                Log::warning('Student attempted to access unauthorized enrollment', [
                    'student_code' => $user->code,
                    'enrollment_id' => $enrollmentId
                ]);
                
                abort(403, 'Access denied. You can only access your own enrollments.');
            }
        }
        
        return $next($request);
    }
}
