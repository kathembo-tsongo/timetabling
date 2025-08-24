<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Unit;
use App\Models\Enrollment;
use App\Models\Semester;
use Inertia\Inertia;

class StudentController extends Controller
{
    /**
     * Display the student's enrollments
     */
    /**
 * Display the student's enrollments
 */
public function myEnrollments(Request $request)
{
    $user = $request->user();
    
    // Check if user has the Student role - TEMPORARILY COMMENT THIS OUT FOR DEBUGGING
    // if (!$user->hasRole('Student')) {
    //     abort(403, 'You must be a student to access this page.');
    // }
    
    // Get current semester
    $currentSemester = Semester::where('is_active', true)->first();
    if (!$currentSemester) {
        $currentSemester = Semester::latest()->first();
    }
    
    // Get all semesters for filtering
    $semesters = Semester::orderBy('name')->get();
    
    // Find semesters where the student has enrollments
    $studentSemesterIds = Enrollment::where('student_code', $user->code)
        ->distinct()
        ->pluck('semester_id')
        ->toArray();
    
    // If student has enrollments, default to their first enrollment semester
    // Otherwise use the current semester
    $defaultSemesterId = !empty($studentSemesterIds) 
        ? $studentSemesterIds[0] 
        : $currentSemester->id;
    
    // Get selected semester (default to first semester with enrollments)
    $selectedSemesterId = $request->input('semester_id', $defaultSemesterId);
    
    // Get student's enrolled units for the selected semester using student code
    $enrolledUnits = Enrollment::where('student_code', $user->code)
        ->where('semester_id', $selectedSemesterId)
        ->with(['unit.school', 'unit.lecturer', 'semester', 'lecturer', 'group'])
        ->get();

        
    
    // For debugging
    Log::info('Student enrollments', [
        'student_code' => $user->code,
        'semester_id' => $selectedSemesterId,
        'available_semesters' => $studentSemesterIds,
        'count' => $enrolledUnits->count(),
        'has_student_role' => $user->hasRole('Student')
    ]);
    
    return Inertia::render('Student/Enrollments', [
        'enrollments' => [
            'data' => $enrolledUnits
        ],
        'currentSemester' => $currentSemester,
        'semesters' => $semesters,
        'selectedSemesterId' => (int)$selectedSemesterId,
        'studentSemesters' => $studentSemesterIds, // Pass this to highlight semesters with enrollments
    ]);
}
    
    /**
     * Display the student's profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        
        // Get student details with related data
        $student = User::with(['faculty', 'enrollments'])
            ->where('id', $user->id)
            ->first();
            
        return Inertia::render('Student/Profile', [
            'student' => $student,
        ]);
    }

    
}
