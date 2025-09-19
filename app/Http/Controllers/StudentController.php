<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; 
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
    
    // Get current semester
    $currentSemester = Semester::where('is_active', true)->first();
    if (!$currentSemester) {
        $currentSemester = Semester::latest()->first();
    }
    
    $selectedSemesterId = $request->input('semester_id', $currentSemester->id);
    
    // Get student's current enrollments
    $enrolledUnits = Enrollment::where('student_code', $user->code)
        ->where('semester_id', $selectedSemesterId)
        ->with(['unit.school', 'semester', 'group'])
        ->get();
    
    // Get enrolled unit IDs to exclude from available units
    $enrolledUnitIds = $enrolledUnits->pluck('unit_id')->toArray();
    
    // Get available units for enrollment
    $availableUnits = Unit::where('semester_id', $selectedSemesterId)
        ->where('is_active', true)
        ->whereNotIn('id', $enrolledUnitIds)
        ->with(['school', 'program'])
        ->get();
    
    // ADD THIS DEBUG LOGGING
    Log::info('Student Enrollments Debug', [
        'student_code' => $user->code,
        'semester_id' => $selectedSemesterId,
        'enrolled_units_count' => $enrolledUnits->count(),
        'enrolled_unit_ids' => $enrolledUnitIds,
        'available_units_count' => $availableUnits->count(),
        'total_active_units' => Unit::where('is_active', true)->where('semester_id', $selectedSemesterId)->count(),
        'current_semester' => $currentSemester ? $currentSemester->name : 'None'
    ]);
    
    return Inertia::render('Student/Enrollments', [
        'enrollments' => [
            'data' => $enrolledUnits
        ],
        'availableUnits' => $availableUnits,
        'currentSemester' => $currentSemester,
        'selectedSemesterId' => (int)$selectedSemesterId,
        'userRoles' => [
            'isStudent' => true,
            'isAdmin' => false,
            'isLecturer' => false,
        ]
    ]);
}
public function enrollInUnit(Request $request)
{
    try {
        $request->validate([
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id'
        ]);
        
        $user = $request->user();
        
        // Check if already enrolled
        $existingEnrollment = Enrollment::where('student_code', $user->code)
            ->where('unit_id', $request->unit_id)
            ->where('semester_id', $request->semester_id)
            ->first();
        
        if ($existingEnrollment) {
            return back()->with('error', 'You are already enrolled in this unit.');
        }
        
        // Get student's class_id from existing enrollment or assign a default
        $studentClassId = Enrollment::where('student_code', $user->code)->value('class_id');
        
        // Create enrollment
        Enrollment::create([
            'student_code' => $user->code,
            'unit_id' => $request->unit_id,
            'semester_id' => $request->semester_id,
            'class_id' => $studentClassId, // ADD THIS IF YOUR TABLE HAS class_id
            'status' => 'active',
            'enrollment_date' => now()
        ]);
        
        Log::info('Student enrolled successfully', [
            'student_code' => $user->code,
            'unit_id' => $request->unit_id,
            'semester_id' => $request->semester_id
        ]);
        
        return back()->with('success', 'Successfully enrolled in the unit!');
        
    } catch (\Exception $e) {
        Log::error('Enrollment failed', [
            'student_code' => $user->code ?? 'unknown',
            'error' => $e->getMessage(),
            'request_data' => $request->all()
        ]);
        
        return back()->with('error', 'Failed to enroll: ' . $e->getMessage());
    }
}
/**
 * Display the student dashboard
 */
public function studentDashboard(Request $request)
{
    $user = $request->user();
    
    // Get current active semester
    $currentSemester = Semester::where('is_active', true)->first();
    if (!$currentSemester) {
        $currentSemester = Semester::latest()->first();
    }
    
    $enrolledUnits = collect();
    $upcomingExams = collect();
    
    if ($currentSemester) {
        // Get student's enrolled units for current semester
        $enrolledUnits = Enrollment::where('student_code', $user->code)
            ->where('semester_id', $currentSemester->id)
            ->with(['unit.school'])
            ->get()
            ->map(function ($enrollment) {
                return [
                    'id' => $enrollment->unit->id,
                    'code' => $enrollment->unit->code,
                    'name' => $enrollment->unit->name,
                    'school' => [
                        'name' => $enrollment->unit->school->name ?? 'Unknown School'
                    ]
                ];
            });

        // Get upcoming exams for enrolled units (if exam_timetables table exists)
        $enrolledUnitIds = $enrolledUnits->pluck('id')->toArray();
        
        if (!empty($enrolledUnitIds)) {
            try {
                // Check if exam_timetables table exists
                $upcomingExams = \DB::table('exam_timetables')
                    ->join('units', 'exam_timetables.unit_id', '=', 'units.id')
                    ->whereIn('exam_timetables.unit_id', $enrolledUnitIds)
                    ->where('exam_timetables.date', '>=', now()->toDateString())
                    ->orderBy('exam_timetables.date')
                    ->orderBy('exam_timetables.start_time')
                    ->select(
                        'exam_timetables.id',
                        'exam_timetables.date',
                        'exam_timetables.day',
                        'exam_timetables.start_time',
                        'exam_timetables.end_time',
                        'exam_timetables.venue',
                        'units.code as unit_code',
                        'units.name as unit_name'
                    )
                    ->get()
                    ->map(function ($exam) {
                        return [
                            'id' => $exam->id,
                            'date' => $exam->date,
                            'day' => $exam->day,
                            'start_time' => $exam->start_time,
                            'end_time' => $exam->end_time,
                            'venue' => $exam->venue,
                            'unit' => [
                                'code' => $exam->unit_code,
                                'name' => $exam->unit_name
                            ]
                        ];
                    });
            } catch (\Exception $e) {
                // If exam_timetables table doesn't exist, use empty collection
                Log::info('Exam timetables table not found or error occurred', ['error' => $e->getMessage()]);
                $upcomingExams = collect();
            }
        }
    }

    // Log for debugging
    Log::info('Student dashboard data', [
        'student_code' => $user->code,
        'current_semester' => $currentSemester?->name,
        'enrolled_units_count' => $enrolledUnits->count(),
        'upcoming_exams_count' => $upcomingExams->count()
    ]);

    return Inertia::render('Student/Dashboard', [
        'enrolledUnits' => $enrolledUnits,
        'upcomingExams' => $upcomingExams,
        'currentSemester' => $currentSemester ? [
            'id' => $currentSemester->id,
            'name' => $currentSemester->name,
            'year' => $currentSemester->year ?? null,
            'is_active' => $currentSemester->is_active
        ] : null
    ]);
}

public function myTimetable(Request $request)
{
    $user = $request->user();
    Log::info('myTimetable method called', ['url' => $request->fullUrl()]);
    
    // Get current active semester
    $currentSemester = Semester::where('is_active', true)->first();
    if (!$currentSemester) {
        $currentSemester = Semester::latest()->first();
    }
    
    // Get selected semester (default to current)
    $selectedSemesterId = $request->input('semester_id', $currentSemester->id ?? null);
    
    // Get student's class information from enrollments table
    $enrollment = Enrollment::where('student_code', $user->code)
        ->where('semester_id', $selectedSemesterId)
        ->first();
    $studentClassId = $enrollment->class_id ?? null;
    
    // Get student's enrolled units
    $enrolledUnits = Enrollment::where('student_code', $user->code)
        ->where('semester_id', $selectedSemesterId)
        ->with(['unit'])
        ->get();
    
    $enrolledUnitIds = $enrolledUnits->pluck('unit.id')->filter()->toArray();
    
    // Get class information if student has a class assigned
    $classInfo = null;
    if ($studentClassId) {
        $classInfo = DB::table('classes')->where('id', $studentClassId)->first();
    }
    
    // Log for debugging
    Log::info('MyTimetable Debug', [
        'student_code' => $user->code,
        'student_class_id' => $studentClassId,
        'class_info' => $classInfo,
        'semester_id' => $selectedSemesterId,
        'enrolled_units_count' => $enrolledUnits->count(),
        'enrolled_unit_ids' => $enrolledUnitIds
    ]);
    
    $classTimetables = collect();
    
    if (!empty($enrolledUnitIds) && $studentClassId) {
        $classTimetables = DB::table('class_timetable')
            ->leftJoin('units', 'class_timetable.unit_id', '=', 'units.id')
            ->leftJoin('users', 'users.code', '=', 'class_timetable.lecturer')
            ->leftJoin('classes', 'class_timetable.class_id', '=', 'classes.id')
            ->whereIn('class_timetable.unit_id', $enrolledUnitIds)
            ->where('class_timetable.semester_id', $selectedSemesterId)
            ->where('class_timetable.class_id', $studentClassId) // Filter by student's specific class
            ->select(
                'class_timetable.*',
                'units.code as unit_code',
                'units.name as unit_name',
                'classes.name as class_name',
                'classes.section as class_section',
                DB::raw("COALESCE(CONCAT(users.first_name, ' ', users.last_name), class_timetable.lecturer) as lecturer_name")
            )
            ->orderBy('class_timetable.day')
            ->orderBy('class_timetable.start_time')
            ->get();
        
        // Log the results
        Log::info('Timetables Retrieved', [
            'count' => $classTimetables->count(),
            'filters_applied' => [
                'class_id' => $studentClassId,
                'class_name' => $classInfo->name ?? 'Unknown',
                'section' => $classInfo->section ?? 'Unknown',
                'units' => $enrolledUnitIds
            ],
            'first_few' => $classTimetables->take(3)->toArray()
        ]);
    } else {
        Log::warning('Cannot fetch personalized timetable', [
            'student_code' => $user->code,
            'has_enrolled_units' => !empty($enrolledUnitIds),
            'has_class_assigned' => !is_null($studentClassId),
            'enrolled_units_count' => count($enrolledUnitIds),
            'class_id' => $studentClassId
        ]);
    }
    
    return Inertia::render('Student/ClassTimetable', [
        'classTimetables' => [
            'data' => $classTimetables
        ],
        'enrolledUnits' => $enrolledUnits,
        'currentSemester' => $currentSemester,
        'semesters' => Semester::orderBy('name')->get(),
        'selectedSemesterId' => (int)$selectedSemesterId,
        'studentInfo' => [
            'name' => $user->first_name . ' ' . $user->last_name,
            'code' => $user->code,
            'class_name' => $classInfo->name ?? null,
            'section' => $classInfo->section ?? null
        ]
    ]);
}
/**
 * Display the student's exam schedule
 */
public function myExams(Request $request)
{
    $user = $request->user();
    
    // Get current active semester
    $currentSemester = Semester::where('is_active', true)->first();
    if (!$currentSemester) {
        $currentSemester = Semester::latest()->first();
    }
    
    // Get student's enrolled units
    $enrolledUnits = Enrollment::where('student_code', $user->code)
        ->where('semester_id', $currentSemester->id ?? 1)
        ->with(['unit'])
        ->get();
    
    $enrolledUnitIds = $enrolledUnits->pluck('unit.id')->filter()->toArray();
    
    $upcomingExams = collect();
    
    if (!empty($enrolledUnitIds)) {
        try {
            $upcomingExams = DB::table('exam_timetables')
                ->join('units', 'exam_timetables.unit_id', '=', 'units.id')
                ->whereIn('exam_timetables.unit_id', $enrolledUnitIds)
                ->where('exam_timetables.date', '>=', now()->toDateString())
                ->orderBy('exam_timetables.date')
                ->orderBy('exam_timetables.start_time')
                ->select(
                    'exam_timetables.*',
                    'units.code as unit_code',
                    'units.name as unit_name'
                )
                ->get();
        } catch (\Exception $e) {
            Log::info('Exam timetables table not found', ['error' => $e->getMessage()]);
        }
    }
    
    return Inertia::render('Student/Exams', [
        'upcomingExams' => $upcomingExams,
        'enrolledUnits' => $enrolledUnits,
        'currentSemester' => $currentSemester
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
