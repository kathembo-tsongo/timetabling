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
    
    // Get student's enrolled units
    $enrolledUnits = Enrollment::where('student_code', $user->code)
        ->where('semester_id', $selectedSemesterId)
        ->with(['unit'])
        ->get();
    
    $enrolledUnitIds = $enrolledUnits->pluck('unit.id')->filter()->toArray();
    
    // Log for debugging
    Log::info('MyTimetable Debug', [
        'student_code' => $user->code,
        'semester_id' => $selectedSemesterId,
        'enrolled_units_count' => $enrolledUnits->count(),
        'enrolled_unit_ids' => $enrolledUnitIds
    ]);
    
    $classTimetables = collect();
    
    if (!empty($enrolledUnitIds)) {
        $classTimetables = DB::table('class_timetable')
            ->leftJoin('units', 'class_timetable.unit_id', '=', 'units.id')
            ->leftJoin('users', 'users.code', '=', 'class_timetable.lecturer')
            ->whereIn('class_timetable.unit_id', $enrolledUnitIds)
            ->where('class_timetable.semester_id', $selectedSemesterId)
            ->select(
                'class_timetable.*',
                'units.code as unit_code',
                'units.name as unit_name',
                DB::raw("COALESCE(CONCAT(users.first_name, ' ', users.last_name), class_timetable.lecturer) as lecturer_name")
            )
            ->orderBy('class_timetable.day')
            ->orderBy('class_timetable.start_time')
            ->get();
        
        // Log the results
        Log::info('Timetables Retrieved', [
            'count' => $classTimetables->count(),
            'first_few' => $classTimetables->take(3)->toArray()
        ]);
    }
    
    return Inertia::render('Student/ClassTimetable', [
    'classTimetables' => [
        'data' => $classTimetables
    ],
    'enrolledUnits' => $enrolledUnits,
    'currentSemester' => $currentSemester,
    'semesters' => Semester::orderBy('name')->get(), // Add this
    'selectedSemesterId' => (int)$selectedSemesterId,
    'studentInfo' => [
        'name' => $user->first_name . ' ' . $user->last_name,
        'code' => $user->code
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
