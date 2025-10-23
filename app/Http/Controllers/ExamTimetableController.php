<?php

namespace App\Http\Controllers;

use App\Models\ExamTimetable;
use App\Models\Unit;
use App\Models\User;
use App\Models\Semester;
use App\Models\SemesterUnit;
use App\Models\Enrollment;
use App\Models\TimeSlot;
use App\Models\Examroom;
use App\Models\Program;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ExamTimetableController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        Log::info('Accessing /examtimetable', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);

        if (!$user->can('manage-examtimetables')) {
            abort(403, 'Unauthorized action.');
        }

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');

        $examTimetables = ExamTimetable::query()
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
            ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
            ->select(
                'exam_timetables.id',
                'exam_timetables.date',
                'exam_timetables.day',
                'exam_timetables.start_time',
                'exam_timetables.end_time',
                'exam_timetables.venue',
                'exam_timetables.location',
                'exam_timetables.no',
                'exam_timetables.chief_invigilator',
                'exam_timetables.unit_id',
                'exam_timetables.semester_id',
                'exam_timetables.class_id',
                'units.name as unit_name',
                'units.code as unit_code',
                'classes.name as class_name',
                \Schema::hasColumn('classes', 'code') 
                    ? 'classes.code as class_code'
                    : DB::raw('CONCAT("CLASS-", classes.id) as class_code'),
                'semesters.name as semester_name'
            )
            ->when($request->has('search') && $request->search !== '', function ($query) use ($request) {
                $search = $request->search;
                $query->where('exam_timetables.day', 'like', "%{$search}%")
                    ->orWhere('exam_timetables.date', 'like', "%{$search}%")
                    ->orWhere('units.code', 'like', "%{$search}%")
                    ->orWhere('units.name', 'like', "%{$search}%");
            })
            ->orderBy('exam_timetables.date')
            ->paginate($request->get('per_page', 10));

        $lecturers = User::role('Lecturer')
            ->select('id', 'code', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
            ->get();

        $semesters = Semester::all();
        $examrooms = Examroom::all();
        $timeSlots = TimeSlot::all();
        $classes = ClassModel::all();

        $hierarchicalData = $this->buildHierarchicalData();

        $classesBySemester = [];
        $unitsByClass = [];
        $allUnits = collect();

        foreach ($hierarchicalData as $semesterData) {
            $classesBySemester[$semesterData['id']] = $semesterData['classes'];
            
            foreach ($semesterData['classes'] as $classData) {
                $unitsByClass[$classData['id']] = $classData['units'];
                
                foreach ($classData['units'] as $unitData) {
                    $allUnits->push((object) $unitData);
                }
            }
        }

        Log::info('Hierarchical data structure:', [
            'semesters_count' => count($hierarchicalData),
            'total_units' => $allUnits->count(),
            'sample_structure' => array_slice($hierarchicalData, 0, 1)
        ]);

        return Inertia::render('ExamTimetables/Index', [
            'examTimetables' => $examTimetables,
            'lecturers' => $lecturers,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'classes' => $classes,
            'examrooms' => $examrooms,
            'timeSlots' => $timeSlots,
            'hierarchicalData' => $hierarchicalData,
            'classesBySemester' => $classesBySemester,
            'unitsByClass' => $unitsByClass,
            'can' => [
                'create' => $user->can('create-exam-timetables'),
                'edit' => $user->can('edit-exam-timetables'),
                'delete' => $user->can('delete-exam-timetables'),
                'process' => $user->can('process-exam-timetables'),
                'solve_conflicts' => $user->can('solve-exam-conflicts'),
                'download' => $user->can('download-exam-timetables'),
            ],
        ]);
    }

    /**
 * REPLACE YOUR buildHierarchicalData() method with this
 * This fixes the 160 vs 40 student count issue in the modal
 */
private function buildHierarchicalData()
{
    $semesters = Semester::all();
    $hierarchicalData = [];
    
    foreach ($semesters as $semester) {
        $semesterData = [
            'id' => $semester->id,
            'name' => $semester->name,
            'classes' => []
        ];
        
        $classIds = DB::table('semester_unit')
            ->where('semester_id', $semester->id)
            ->distinct()
            ->pluck('class_id');

        if ($classIds->isNotEmpty()) {
            $classesInSemester = ClassModel::whereIn('id', $classIds)->get();
        } else {
            $classesInSemester = ClassModel::where('semester_id', $semester->id)->get();
        }
    
        foreach ($classesInSemester as $class) {
            $columns = \Schema::getColumnListing('classes');
            $hasCodeColumn = in_array('code', $columns);
            
            $classData = [
                'id' => $class->id,
                'code' => $hasCodeColumn ? ($class->code ?? 'CLASS-' . $class->id) : 'CLASS-' . $class->id,
                'name' => $class->name,
                'semester_id' => $semester->id,
                'units' => []
            ];
            
            $unitIds = DB::table('semester_unit')
                ->where('semester_id', $semester->id)
                ->where('class_id', $class->id)
                ->pluck('unit_id');

            if ($unitIds->isNotEmpty()) {
                $unitsInClass = Unit::whereIn('id', $unitIds)->get();
            } else {
                $unitsInClass = Unit::where('class_id', $class->id)->get();
            }
            
            foreach ($unitsInClass as $unit) {
                // ✅ FIX: Get all classes taking this unit in this semester
                $allClassesTakingUnit = DB::table('semester_unit')
                    ->where('semester_id', $semester->id)
                    ->where('unit_id', $unit->id)
                    ->distinct()
                    ->pluck('class_id');

                // ✅ FIX: Count TOTAL unique students across ALL classes
                $totalStudentCount = DB::table('enrollments')
                    ->where('unit_id', $unit->id)
                    ->where('semester_id', $semester->id)
                    ->whereIn('class_id', $allClassesTakingUnit)
                    ->where('status', 'enrolled')
                    ->distinct()
                    ->count('student_code');

                // Get enrollment breakdown by class
                $enrollmentsByClass = [];
                $classList = [];
                
                foreach ($allClassesTakingUnit as $classId) {
                    $classStudentCount = DB::table('enrollments')
                        ->where('unit_id', $unit->id)
                        ->where('semester_id', $semester->id)
                        ->where('class_id', $classId)
                        ->where('status', 'enrolled')
                        ->distinct()
                        ->count('student_code');
                    
                    if ($classStudentCount > 0) {
                        $enrollmentClass = ClassModel::find($classId);
                        if ($enrollmentClass) {
                            $classList[] = [
                                'id' => $enrollmentClass->id,
                                'name' => $enrollmentClass->name,
                                'code' => $hasCodeColumn ? ($enrollmentClass->code ?? 'CLASS-' . $enrollmentClass->id) : 'CLASS-' . $enrollmentClass->id,
                                'student_count' => $classStudentCount
                            ];
                        }
                    }
                }
                
                // Skip units with no enrollments
                if ($totalStudentCount === 0) {
                    continue;
                }
                
                $lecturerAssignment = DB::table('unit_assignments')
                    ->where('unit_id', $unit->id)
                    ->where('class_id', $class->id)
                    ->first();

                $lecturerCode = $lecturerAssignment->lecturer_code ?? null;
                $lecturerName = null;

                if ($lecturerCode) {
                    $lecturer = User::where('code', $lecturerCode)->first();
                    if ($lecturer) {
                        $lecturerName = "{$lecturer->first_name} {$lecturer->last_name}";
                    }
                }
                
                $classData['units'][] = [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'class_id' => $class->id,
                    'semester_id' => $semester->id,
                    'student_count' => $totalStudentCount,  // ✅ Total across all classes
                    'lecturer_code' => $lecturerCode,
                    'lecturer_name' => $lecturerName,
                    'classes_taking_unit' => count($classList),
                    'class_list' => $classList  // ✅ Breakdown by class
                ];
            }
            
            if (!empty($classData['units'])) {
                $semesterData['classes'][] = $classData;
            }
        }
        
        if (!empty($semesterData['classes'])) {
            $hierarchicalData[] = $semesterData;
        }
    }
    
    return $hierarchicalData;
}

    /**
     * Get classes for a specific semester (API endpoint)
     */
    public function getClassesBySemester(Request $request, $semesterId)
{
    try {
        $programId = $request->input('program_id');
        
        Log::info('Getting classes for semester and program', [
            'semester_id' => $semesterId,
            'program_id' => $programId
        ]);

        // ✅ Filter by BOTH semester_id AND program_id
        $classIds = DB::table('semester_unit')
            ->join('units', 'semester_unit.unit_id', '=', 'units.id')
            ->where('semester_unit.semester_id', $semesterId)
            ->where('units.program_id', $programId)
            ->distinct()
            ->pluck('semester_unit.class_id');

        if ($classIds->isNotEmpty()) {
            $columns = \Schema::getColumnListing('classes');
            $hasCodeColumn = in_array('code', $columns);
            
            if ($hasCodeColumn) {
                $classes = ClassModel::whereIn('id', $classIds)
                    ->where('program_id', $programId) // ✅ Additional safety check
                    ->select('id', 'name', 'code', 'semester_id', 'program_id')
                    ->get();
            } else {
                $classes = ClassModel::whereIn('id', $classIds)
                    ->where('program_id', $programId) // ✅ Additional safety check
                    ->select('id', 'name', 'semester_id', 'program_id', DB::raw('CONCAT("CLASS-", id) as code'))
                    ->get();
            }
        } else {
            // Fallback: get classes directly from ClassModel
            $columns = \Schema::getColumnListing('classes');
            $hasCodeColumn = in_array('code', $columns);
            
            if ($hasCodeColumn) {
                $classes = ClassModel::where('semester_id', $semesterId)
                    ->where('program_id', $programId) // ✅ Filter by program
                    ->select('id', 'name', 'code', 'semester_id', 'program_id')
                    ->get();
            } else {
                $classes = ClassModel::where('semester_id', $semesterId)
                    ->where('program_id', $programId) // ✅ Filter by program
                    ->select('id', 'name', 'semester_id', 'program_id', DB::raw('CONCAT("CLASS-", id) as code'))
                    ->get();
            }
        }

        Log::info('Found classes for semester and program', [
            'semester_id' => $semesterId,
            'program_id' => $programId,
            'classes_count' => $classes->count()
        ]);

        return response()->json([
            'success' => true,
            'classes' => $classes
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to get classes for semester: ' . $e->getMessage());
        return response()->json([
            'success' => false, 
            'message' => 'Failed to get classes: ' . $e->getMessage()
        ], 500);
    }
}
/**
 * ✅ FIXED: Get units with TOTAL student count across ALL classes taking the unit
 * Handles missing 'code' column in classes table
 */
public function getUnitsByClassAndSemesterForExam(Request $request)
{
    try {
        $classId = $request->input('class_id');
        $semesterId = $request->input('semester_id');
        $programId = $request->input('program_id');

        if (!$classId || !$semesterId) {
            return response()->json([
                'error' => 'Class ID and Semester ID are required.'
            ], 400);
        }

        \Log::info('Fetching units with cross-class student counts', [
            'class_id' => $classId,
            'semester_id' => $semesterId,
            'program_id' => $programId
        ]);

        // ✅ Check if 'code' column exists in classes table
        $hasCodeColumn = \Schema::hasColumn('classes', 'code');

        // Get units for this specific class
        $units = DB::table('unit_assignments')
            ->join('units', 'unit_assignments.unit_id', '=', 'units.id')
            ->leftJoin('users', 'users.code', '=', 'unit_assignments.lecturer_code')
            ->where('unit_assignments.semester_id', $semesterId)
            ->where('unit_assignments.class_id', $classId)
            ->when($programId, function($query) use ($programId) {
                return $query->where('units.program_id', $programId);
            })
            ->select(
                'units.id',
                'units.code',
                'units.name',
                'units.credit_hours',
                'units.program_id',
                'unit_assignments.lecturer_code',
                DB::raw("CASE 
                    WHEN users.id IS NOT NULL 
                    THEN CONCAT(users.first_name, ' ', users.last_name) 
                    ELSE unit_assignments.lecturer_code 
                    END as lecturer_name")
            )
            ->distinct()
            ->get();

        if ($units->isEmpty()) {
            return response()->json([]);
        }

        // ✅ For each unit, calculate TOTAL students across ALL classes in this program
        $enhancedUnits = $units->map(function ($unit) use ($semesterId, $programId, $hasCodeColumn) {
            // Get all classes taking this unit in this semester and program
            $classesQuery = DB::table('enrollments')
                ->join('classes', 'enrollments.class_id', '=', 'classes.id')
                ->where('enrollments.unit_id', $unit->id)
                ->where('enrollments.semester_id', $semesterId)
                ->when($programId, function($query) use ($programId) {
                    return $query->where('classes.program_id', $programId);
                })
                ->select(
                    'classes.id as class_id',
                    'classes.name as class_name',
                    // ✅ Conditionally select 'code' column
                    $hasCodeColumn 
                        ? DB::raw('classes.code as class_code')
                        : DB::raw('CONCAT("CLASS-", classes.id) as class_code'),
                    DB::raw('COUNT(DISTINCT enrollments.student_code) as student_count')
                )
                ->groupBy('classes.id', 'classes.name');
            
            // ✅ Only add 'classes.code' to GROUP BY if column exists
            if ($hasCodeColumn) {
                $classesQuery->groupBy('classes.code');
            }
            
            $classesWithStudents = $classesQuery->get();

            // Calculate total students across all classes
           // ✅ Get unique students across all sections (avoid double counting)
            $totalStudents = DB::table('enrollments')
                ->join('classes', 'enrollments.class_id', '=', 'classes.id')
                ->where('enrollments.unit_id', $unit->id)
                ->where('enrollments.semester_id', $semesterId)
                ->when($programId, function($query) use ($programId) {
                    return $query->where('classes.program_id', $programId);
            })
                ->distinct('enrollments.student_code')
                ->count('enrollments.student_code');


            // Format class list with student counts
            $classList = $classesWithStudents->map(function($class) {
                return [
                    'id' => $class->class_id,
                    'name' => $class->class_name,
                    'code' => $class->class_code,
                    'student_count' => $class->student_count
                ];
            })->toArray();

            return [
    'id' => $unit->id,
    'code' => $unit->code,
    'name' => $unit->name,
    'credit_hours' => $unit->credit_hours ?? 3,
    'student_count' => $totalStudents, // ✅ Unique across all classes
    'lecturer_name' => $unit->lecturer_name ?? 'No lecturer assigned',
    'lecturer_code' => $unit->lecturer_code ?? '',
    'classes_taking_unit' => count($classList),
    'class_list' => $classList,
];

        });

        \Log::info('Units retrieved with cross-class counts', [
            'semester_id' => $semesterId,
            'program_id' => $programId,
            'units_count' => $enhancedUnits->count(),
            'sample_unit' => $enhancedUnits->first()
        ]);

        return response()->json($enhancedUnits->values()->all());
        
    } catch (\Exception $e) {
        \Log::error('Error fetching units with cross-class counts: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'error' => 'Failed to fetch units for exam timetable.'
        ], 500);
    }
}

   private function assignSmartVenue(
    int $studentCount,
    string $date,
    string $startTime,
    string $endTime,
    ?int $excludeExamId = null
)
{
    try {
        \Log::info('Starting smart venue assignment', [
            'student_count' => $studentCount,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        // Get all suitable venues by capacity
        $suitableVenues = \App\Models\Examroom::where('capacity', '>=', $studentCount)
            ->where('is_active', true)
            ->orderBy('capacity', 'asc')
            ->get();

        if ($availableVenues->isEmpty()) {
    return [
        'success' => false,
        'message' => 'All suitable exam rooms are at full capacity',
        'venue' => 'TBD',
        'location' => 'TBD',
        'reason' => 'capacity_exceeded', // ✅ ADD THIS
        'details' => [
            'required_capacity' => $studentCount,
            'checked_rooms' => $suitableVenues->count()
        ]
    ];
}

        // Get all conflicting exams at the same time
        $conflictingExams = \App\Models\ExamTimetable::where('date', $date)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<=', $startTime)
                          ->where('end_time', '>=', $endTime);
                    });
            });

        if ($excludeExamId) {
            $conflictingExams->where('id', '!=', $excludeExamId);
        }

        $conflictingExams = $conflictingExams->get();

        // Calculate used capacity per venue
        $venueUsage = [];
        foreach ($conflictingExams as $exam) {
            if (!isset($venueUsage[$exam->venue])) {
                $venueUsage[$exam->venue] = 0;
            }
            $venueUsage[$exam->venue] += $exam->no; // 'no' is student count
        }

        // Find venue with enough remaining capacity
        foreach ($suitableVenues as $room) {
            $usedCapacity = $venueUsage[$room->name] ?? 0;
            $remainingCapacity = $room->capacity - $usedCapacity;

            if ($remainingCapacity >= $studentCount) {
                return [
                    'success' => true,
                    'message' => "Venue assigned: {$room->name} (Remaining: {$remainingCapacity}/{$room->capacity})",
                    'venue' => $room->name,
                    'location' => $room->location ?? 'Main Campus',
                    'capacity' => $room->capacity,
                    'used_capacity' => $usedCapacity,
                    'remaining_capacity' => $remainingCapacity,
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'All suitable exam rooms have insufficient remaining capacity',
            'venue' => 'TBD',
            'location' => 'TBD'
        ];

    } catch (\Exception $e) {
        \Log::error('Error in smart venue assignment', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return [
            'success' => false,
            'message' => 'Failed to assign venue: ' . $e->getMessage(),
            'venue' => 'TBD',
            'location' => 'TBD'
        ];
    }
}
        /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'day' => 'required|string',
            'date' => 'required|date',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);

        try {
            $venueAssignment = $this->assignSmartVenue(
                $request->no,
                $request->date,
                $request->start_time,
                $request->end_time
            );

            if (!$venueAssignment['success']) {
                return redirect()->back()->withErrors([
                    'venue' => $venueAssignment['message']
                ])->withInput();
            }

           // ✅ Create exam timetable with all required fields INCLUDING program_id and school_id
$examTimetable = ExamTimetable::create([
    'unit_id' => $validatedData['unit_id'],
    'semester_id' => $validatedData['semester_id'],
    'class_id' => $validatedData['class_id'],
    'program_id' => $program->id,  // ✅ ADD THIS
    'school_id' => $program->school_id,  // ✅ ADD THIS
    'date' => $validatedData['date'],
    'day' => $validatedData['day'],
    'start_time' => $validatedData['start_time'],
    'end_time' => $validatedData['end_time'],
    'venue' => $validatedData['venue'],
    'location' => $validatedData['location'],
    'no' => $validatedData['no'],
    'chief_invigilator' => $validatedData['chief_invigilator'],
]);
            $successMessage = 'Exam timetable created successfully. ' . $venueAssignment['message'];
            
            return redirect()->back()->with('success', $successMessage);
        } catch (\Exception $e) {
            \Log::error('Failed to create exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create exam timetable: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'day' => 'required|string',
            'date' => 'required|date',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);

        try {
            $examTimetable = ExamTimetable::findOrFail($id);

            $venueAssignment = $this->assignSmartVenue(
                $request->no,
                $request->date,
                $request->start_time,
                $request->end_time,
                $id
            );

            if (!$venueAssignment['success']) {
                return redirect()->back()->withErrors([
                    'venue' => $venueAssignment['message']
                ])->withInput();
            }

            $examTimetable->update([
    'unit_id' => $validatedData['unit_id'],
    'semester_id' => $validatedData['semester_id'],
    'class_id' => $validatedData['class_id'],
    'program_id' => $program->id,  // ✅ ADD THIS
    'school_id' => $program->school_id,  // ✅ ADD THIS
    'date' => $validatedData['date'],
    'day' => $validatedData['day'],
    'start_time' => $validatedData['start_time'],
    'end_time' => $validatedData['end_time'],
    'venue' => $validatedData['venue'],
    'location' => $validatedData['location'],
    'no' => $validatedData['no'],
    'chief_invigilator' => $validatedData['chief_invigilator'],
]);

            $successMessage = 'Exam timetable updated successfully. ' . $venueAssignment['message'];

            return redirect()->back()->with('success', $successMessage);
        } catch (\Exception $e) {
            \Log::error('Failed to update exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update exam timetable: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $examTimetable = ExamTimetable::findOrFail($id);
            $examTimetable->delete();
            return redirect()->back()->with('success', 'Exam timetable deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to delete exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete exam timetable: ' . $e->getMessage());
        }
    }

/**
     * Process exam timetables
     */
    public function process(Request $request)
    {
        try {
            \Log::info('Processing exam timetable');
            return redirect()->back()->with('success', 'Exam timetable processed successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to process exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to process exam timetable: ' . $e->getMessage());
        }
    }

    /**
     * Solve exam conflicts
     */
    public function solveConflicts(Request $request)
    {
        try {
            \Log::info('Solving exam timetable conflicts');
            return redirect()->back()->with('success', 'Exam conflicts resolved successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to solve exam conflicts: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to solve exam conflicts: ' . $e->getMessage());
        }
    }

    /**
     * Download exam timetable as PDF
     */
    public function downloadPDF(Request $request)
    {
        try {
            if (!view()->exists('examtimetables.pdf')) {
                \Log::error('PDF template not found: examtimetables.pdf');
                return redirect()->back()->with('error', 'PDF template not found. Please contact the administrator.');
            }

            $query = ExamTimetable::query()
                ->join('units', 'exam_timetables.unit_id', '=', 'units.id')
                ->join('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
                ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
                ->select(
                    'exam_timetables.*',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'semesters.name as semester_name',
                    'classes.name as class_name'
                );

            if ($request->has('semester_id')) {
                $query->where('exam_timetables.semester_id', $request->semester_id);
            }

            if ($request->has('class_id')) {
                $query->where('exam_timetables.class_id', $request->class_id);
            }

            $examTimetables = $query->orderBy('exam_timetables.date')
                ->orderBy('exam_timetables.start_time')
                ->get();

            $pdf = Pdf::loadView('examtimetables.pdf', [
                'examTimetables' => $examTimetables,
                'title' => 'Exam Timetable',
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            $pdf->setPaper('a4', 'landscape');

            return $pdf->download('examtimetable.pdf');
        } catch (\Exception $e) {
            \Log::error('Failed to generate PDF: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['success' => true, 'message' => 'Create form ready']);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $examTimetable = ExamTimetable::with(['unit', 'semester', 'class'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'examTimetable' => $examTimetable
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam timetable not found'
            ], 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        try {
            $examTimetable = ExamTimetable::with(['unit', 'semester', 'class'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'examTimetable' => $examTimetable
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exam timetable not found'
            ], 404);
        }
    }

    /**
     * Ajax delete method
     */
    public function ajaxDestroy($id)
    {
        try {
            $examTimetable = ExamTimetable::findOrFail($id);
            $examTimetable->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Exam timetable deleted successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete exam timetable via AJAX', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete exam timetable: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display specific exam details for student
     */
    public function viewStudentExamDetails($examtimetableId)
    {
        $user = auth()->user();
        
        try {
            $examTimetable = ExamTimetable::leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
                ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
                ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
                ->select(
                    'exam_timetables.*',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'classes.name as class_name',
                    'classes.code as class_code',
                    'semesters.name as semester_name'
                )
                ->where('exam_timetables.id', $examtimetableId)
                ->first();

            if (!$examTimetable) {
                abort(404, 'Exam timetable not found.');
            }

            $enrollment = $user->enrollments()
                ->where('unit_id', $examTimetable->unit_id)
                ->where('semester_id', $examTimetable->semester_id)
                ->first();

            if (!$enrollment) {
                abort(403, 'You are not enrolled in this unit.');
            }

            return Inertia::render('Student/ExamDetails', [
                'examTimetable' => $examTimetable,
                'enrollment' => $enrollment,
                'auth' => ['user' => $user]
            ]);
        } catch (\Exception $e) {
            Log::error('Error viewing student exam details', [
                'exam_id' => $examtimetableId,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->route('student.exams')->with('error', 'Unable to view exam details.');
        }
    }

    /**
     * Download student's exam timetable as PDF
     */
    public function downloadStudentTimetable(Request $request)
    {
        $user = auth()->user();
        
        try {
            Log::info('Student PDF download requested', [
                'user_id' => $user->id,
                'student_id' => $user->student_id ?? $user->code,
                'semester_id' => $request->get('semester_id')
            ]);

            $enrollments = $user->enrollments()->with(['unit', 'semester', 'class'])->get();
            
            if ($enrollments->isEmpty()) {
                Log::warning('No enrollments found for student PDF download', ['user_id' => $user->id]);
                return redirect()->back()->with('error', 'No enrollments found. Cannot generate timetable.');
            }

            $query = ExamTimetable::query()
                ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
                ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
                ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
                ->whereIn('exam_timetables.unit_id', $enrollments->pluck('unit_id'))
                ->whereIn('exam_timetables.semester_id', $enrollments->pluck('semester_id'))
                ->select(
                    'exam_timetables.id',
                    'exam_timetables.date',
                    'exam_timetables.day',
                    'exam_timetables.start_time',
                    'exam_timetables.end_time',
                    'exam_timetables.venue',
                    'exam_timetables.location',
                    'exam_timetables.no',
                    'exam_timetables.chief_invigilator',
                    'exam_timetables.unit_id',
                    'exam_timetables.semester_id',
                    'exam_timetables.class_id',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'classes.name as class_name',
                    \Schema::hasColumn('classes', 'code') 
                        ? 'classes.code as class_code'
                        : DB::raw('CONCAT("CLASS-", classes.id) as class_code'),
                    'semesters.name as semester_name'
                )
                ->orderBy('exam_timetables.date')
                ->orderBy('exam_timetables.start_time');

            if ($request->has('semester_id') && $request->semester_id) {
                $query->where('exam_timetables.semester_id', $request->semester_id);
                $selectedSemester = Semester::find($request->semester_id);
            } else {
                $selectedSemester = $enrollments->first()->semester;
            }

            $examTimetables = $query->get();

            Log::info('Found exam timetables for PDF', [
                'user_id' => $user->id,
                'count' => $examTimetables->count(),
                'semester' => $selectedSemester->name ?? 'Unknown'
            ]);

            if (!view()->exists('examtimetables.student')) {
                Log::error('Blade template not found: examtimetables.student');
                return redirect()->back()->with('error', 'PDF template not found. Please contact the administrator.');
            }

            $data = [
                'examTimetables' => $examTimetables,
                'student' => $user,
                'currentSemester' => $selectedSemester,
                'title' => 'Student Exam Timetable',
                'generatedAt' => now()->format('F j, Y \a\t g:i A'),
                'studentName' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'studentId' => $user->student_id ?? $user->code ?? $user->id,
            ];

            $pdf = PDF::loadView('examtimetables.student', $data);
            $pdf->setPaper('a4', 'portrait');
            
            $pdf->setOptions([
                'defaultFont' => 'Arial',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'debugPng' => false,
                'debugKeepTemp' => false,
                'debugCss' => false,
            ]);

            $studentId = $user->student_id ?? $user->code ?? $user->id;
            $filename = "exam-timetable-{$studentId}-" . now()->format('Y-m-d') . ".pdf";
            
            Log::info('PDF generated successfully', [
                'user_id' => $user->id,
                'filename' => $filename
            ]);

            return $pdf->download($filename);
            
        } catch (\Exception $e) {
            Log::error('Failed to generate student exam timetable PDF', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    public function viewStudentTimetable(Request $request)
    {
        $user = $request->user();

        $enrollments = $user->enrollments()->with(['unit', 'semester', 'class'])->get();

        $examTimetables = \App\Models\ExamTimetable::whereIn('unit_id', $enrollments->pluck('unit_id'))
            ->whereIn('semester_id', $enrollments->pluck('semester_id'))
            ->with(['unit', 'semester', 'class', 'examroom'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return \Inertia\Inertia::render('Student/ExamTimetable', [
            'examTimetables' => $examTimetables,
        ]);
    }

    public function studentExamTimetable(Request $request)
    {
        $user = auth()->user();
        
        try {
            $enrollments = $user->enrollments()->with(['unit', 'semester', 'class'])->get();
            
            if ($enrollments->isEmpty()) {
                return Inertia::render('Student/ExamTimetable', [
                    'examTimetables' => collect([]),
                    'semesters' => collect([]),
                    'selectedSemesterId' => null,
                    'message' => 'No enrollments found. Please enroll in units to view your exam timetable.'
                ]);
            }

            $semesters = $enrollments->pluck('semester')->unique('id')->values();
            
            $selectedSemesterId = $request->get('semester_id');
            if (!$selectedSemesterId) {
                $selectedSemesterId = $semesters->first()->id ?? null;
            }

            $query = ExamTimetable::query()
                ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
                ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
                ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
                ->whereIn('exam_timetables.unit_id', $enrollments->pluck('unit_id'))
                ->whereIn('exam_timetables.semester_id', $enrollments->pluck('semester_id'))
                ->select(
                    'exam_timetables.id',
                    'exam_timetables.date',
                    'exam_timetables.day',
                    'exam_timetables.start_time',
                    'exam_timetables.end_time',
                    'exam_timetables.venue',
                    'exam_timetables.location',
                    'exam_timetables.no',
                    'exam_timetables.chief_invigilator',
                    'exam_timetables.unit_id',
                    'exam_timetables.semester_id',
                    'exam_timetables.class_id',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'classes.name as class_name',
                    \Schema::hasColumn('classes', 'code') 
                        ? 'classes.code as class_code'
                        : DB::raw('CONCAT("CLASS-", classes.id) as class_code'),
                    'semesters.name as semester_name'
                );

            if ($selectedSemesterId) {
                $query->where('exam_timetables.semester_id', $selectedSemesterId);
            }

            $examTimetables = $query->orderBy('exam_timetables.date')
                ->orderBy('exam_timetables.start_time')
                ->get();

            Log::info('Student exam timetable loaded', [
                'user_id' => $user->id,
                'enrollments_count' => $enrollments->count(),
                'semesters_count' => $semesters->count(),
                'exams_count' => $examTimetables->count(),
                'selected_semester_id' => $selectedSemesterId
            ]);

            return Inertia::render('Student/ExamTimetable', [
                'examTimetables' => $examTimetables,
                'semesters' => $semesters,
                'selectedSemesterId' => $selectedSemesterId,
                'enrollments' => $enrollments,
                'student' => $user
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error loading student exam timetable', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Inertia::render('Student/ExamTimetable', [
                'examTimetables' => collect([]),
                'semesters' => collect([]),
                'selectedSemesterId' => null,
                'message' => 'Error loading exam timetable. Please try again or contact support.'
            ]);
        }
    }

    // ====================================================================
    // PROGRAM-SPECIFIC METHODS
    // ====================================================================

    /**
     * Display exam timetables for a specific program
     */
    public function programExamTimetables(Program $program, Request $request, $schoolCode)
    {
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        $perPage = $request->per_page ?? 15;
        $search = $request->search ?? '';
        $semesterId = $request->semester_id;
        
        $query = ExamTimetable::query()
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
            ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
            ->where('units.program_id', $program->id)
            ->select(
                'exam_timetables.id',
                'exam_timetables.date',
                'exam_timetables.day',
                'exam_timetables.start_time',
                'exam_timetables.end_time',
                'exam_timetables.venue',
                'exam_timetables.location',
                'exam_timetables.no',
                'exam_timetables.chief_invigilator',
                'exam_timetables.unit_id',
                'exam_timetables.semester_id',
                'exam_timetables.class_id',
                'units.name as unit_name',
                'units.code as unit_code',
                'classes.name as class_name',
                \Schema::hasColumn('classes', 'code') 
                    ? 'classes.code as class_code'
                    : DB::raw('CONCAT("CLASS-", classes.id) as class_code'),
                'semesters.name as semester_name'
            )
            ->when($search, function($q) use ($search) {
                $q->where('exam_timetables.day', 'like', "%{$search}%")
                  ->orWhere('exam_timetables.date', 'like', "%{$search}%")
                  ->orWhere('units.code', 'like', "%{$search}%")
                  ->orWhere('units.name', 'like', "%{$search}%");
            })
            ->when($semesterId, function($q) use ($semesterId) {
                $q->where('exam_timetables.semester_id', $semesterId);
            })
            ->orderBy('exam_timetables.date')
            ->orderBy('exam_timetables.start_time');
        
        $examTimetables = $query->paginate($perPage)->withQueryString();
        
        $semesters = Semester::all();
        
        return Inertia::render('Schools/' . strtoupper($schoolCode) . '/Programs/ExamTimetables/Index', [
            'examTimetables' => $examTimetables,
            'program' => $program->load('school'),
            'semesters' => $semesters,
            'schoolCode' => strtoupper($schoolCode),
            'filters' => [
                'search' => $search,
                'semester_id' => $semesterId ? (int) $semesterId : null,
                'per_page' => (int) $perPage,
            ],
            'can' => [
                'create' => auth()->user()->can('create-exam-timetables'),
                'edit' => auth()->user()->can('edit-exam-timetables'),
                'delete' => auth()->user()->can('delete-exam-timetables'),
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * ✅ FIXED: Store a new exam timetable for a specific program
     */
  
public function storeProgramExamTimetable(Program $program, Request $request, $schoolCode)
{
    \Log::info('=== EXAM TIMETABLE STORE REQUEST ===', [
        'program_id' => $program->id,
        'program_name' => $program->name,
        'school_id' => $program->school_id,
        'school_code' => $schoolCode,
        'request_data' => $request->all(),
    ]);

    $validatedData = $request->validate([
        'semester_id' => 'required|integer|exists:semesters,id',
        'class_id' => 'required|integer|exists:classes,id',
        'unit_id' => 'required|integer|exists:units,id',
        'date' => 'required|date|date_format:Y-m-d',
        'day' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i|after:start_time',
        'chief_invigilator' => 'required|string|max:255',
        'no' => 'required|integer|min:1',
        'venue' => 'nullable|string|max:255',
        'location' => 'nullable|string|max:255',
    ], [
        'semester_id.required' => 'Please select a semester',
        'class_id.required' => 'Please select a class',
        'unit_id.required' => 'Please select a unit',
        'no.required' => 'Number of students is required',
        'no.min' => 'Number of students must be at least 1',
    ]);

    try {
        // ✅ STEP 1: Calculate ACTUAL total student count across ALL classes taking this unit
        $totalStudentsCalculated = DB::table('enrollments')
            ->join('classes', 'enrollments.class_id', '=', 'classes.id')
            ->where('enrollments.unit_id', $validatedData['unit_id'])
            ->where('enrollments.semester_id', $validatedData['semester_id'])
            ->where('classes.program_id', $program->id) // ✅ Only count students in this program
            ->distinct('enrollments.student_code')
            ->count('enrollments.student_code');

        // Get class breakdown for logging
        $classBreakdown = DB::table('enrollments')
            ->join('classes', 'enrollments.class_id', '=', 'classes.id')
            ->where('enrollments.unit_id', $validatedData['unit_id'])
            ->where('enrollments.semester_id', $validatedData['semester_id'])
            ->where('classes.program_id', $program->id)
            ->select(
                'classes.id as class_id',
                'classes.name as class_name',
                'classes.code as class_code',
                DB::raw('COUNT(DISTINCT enrollments.student_code) as student_count')
            )
            ->groupBy('classes.id', 'classes.name', 'classes.code')
            ->get();

        \Log::info('✅ Calculated student count for exam', [
            'unit_id' => $validatedData['unit_id'],
            'semester_id' => $validatedData['semester_id'],
            'program_id' => $program->id,
            'total_students_calculated' => $totalStudentsCalculated,
            'user_provided_count' => $validatedData['no'],
            'class_breakdown' => $classBreakdown->toArray(),
            'classes_count' => $classBreakdown->count()
        ]);

        // ✅ STEP 2: Use calculated count (allow user override if they manually changed it)
        // If user's count matches or is close to calculated, use calculated
        // If significantly different, respect user's override but log a warning
        $studentCount = $validatedData['no'];
        
        if (abs($totalStudentsCalculated - $validatedData['no']) > 5) {
            \Log::warning('⚠️ User-provided student count differs significantly from calculated', [
                'calculated' => $totalStudentsCalculated,
                'user_provided' => $validatedData['no'],
                'difference' => abs($totalStudentsCalculated - $validatedData['no'])
            ]);
        } else {
            // Use calculated count if close enough
            $studentCount = $totalStudentsCalculated;
        }

        // Ensure we have at least 1 student
        $studentCount = max($studentCount, 1);

        // ✅ STEP 3: Smart venue assignment with CORRECT student count
        if (empty($validatedData['venue'])) {
            $venueResult = $this->assignSmartVenue(
                $studentCount, // ✅ Use accurate total count
                $validatedData['date'],
                $validatedData['start_time'],
                $validatedData['end_time']
            );

            
if ($venueResult['success']) {
    // Get the venue's total capacity
    $venue = \App\Models\Examroom::where('name', $venueResult['venue'])->first();
    
    if ($venue) {
        // Calculate total students already scheduled at this venue during this time
        $venueOccupancy = $existingExams->filter(function($exam) use ($examDate, $examStartTime, $examEndTime, $venueResult) {
            if ($exam->date !== $examDate || $exam->venue !== $venueResult['venue']) {
                return false;
            }
            
            $existingStart = Carbon::parse($exam->date . ' ' . $exam->start_time);
            $existingEnd = Carbon::parse($exam->date . ' ' . $exam->end_time);
            
            return $existingStart->lt($examEndTime) && $existingEnd->gt($examStartTime);
        })->sum('no'); // Sum of all students ('no' field)

        $remainingCapacity = $venue->capacity - $venueOccupancy;

        // Check if adding this exam would exceed capacity
        if ($enrollmentCount > $remainingCapacity) {
            $hasConflict = true;
            $conflictReasons[] = "Venue {$venueResult['venue']} has insufficient capacity (Need: {$enrollmentCount}, Available: {$remainingCapacity}/{$venue->capacity})";
        }
    }
}

        }

        // Set default location if still empty
        if (empty($validatedData['location'])) {
            $validatedData['location'] = $validatedData['venue'] === 'Remote' ? 'Online' : 'Main Campus';
        }

        // ✅ STEP 4: Create exam timetable with ALL required fields
        $examData = [
            'unit_id' => $validatedData['unit_id'],
            'semester_id' => $validatedData['semester_id'],
            'class_id' => $validatedData['class_id'], // Primary class (usually first one)
            'program_id' => $program->id,
            'school_id' => $program->school_id,
            'date' => $validatedData['date'],
            'day' => $validatedData['day'],
            'start_time' => $validatedData['start_time'],
            'end_time' => $validatedData['end_time'],
            'venue' => $validatedData['venue'],
            'location' => $validatedData['location'],
            'no' => $studentCount, // ✅ Use calculated accurate count
            'chief_invigilator' => $validatedData['chief_invigilator'],
        ];

        \Log::info('📝 Creating exam timetable with data:', $examData);

        $examTimetable = ExamTimetable::create($examData);

        \Log::info('✅ Exam timetable created successfully', [
            'exam_timetable_id' => $examTimetable->id,
            'unit_code' => DB::table('units')->where('id', $examTimetable->unit_id)->value('code'),
            'program_id' => $examTimetable->program_id,
            'school_id' => $examTimetable->school_id,
            'student_count' => $examTimetable->no,
            'venue' => $examTimetable->venue,
            'classes_involved' => $classBreakdown->count()
        ]);

        return redirect()
            ->route('schools.' . strtolower($schoolCode) . '.programs.exam-timetables.index', $program)
            ->with('success', "Exam timetable created successfully for {$program->name}! Scheduled for {$studentCount} students across {$classBreakdown->count()} class(es).");

    } catch (\Illuminate\Validation\ValidationException $e) {
        \Log::error('❌ Validation error in exam timetable creation', [
            'errors' => $e->errors(),
        ]);
        
        return redirect()->back()
            ->withErrors($e->errors())
            ->withInput();
            
    } catch (\Exception $e) {
        \Log::error('❌ Error creating exam timetable', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'program_id' => $program->id,
            'request_data' => $validatedData ?? $request->all()
        ]);

        return redirect()->back()
            ->withErrors(['error' => 'Failed to create exam timetable: ' . $e->getMessage()])
            ->withInput();
    }
}

    /**
     * Show create form for program exam timetable
     */
    public function createProgramExamTimetable(Program $program, $schoolCode)
    {
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        $semesters = Semester::all();
        $lecturers = User::role('Lecturer')
            ->select('id', 'code', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
            ->get();

        return Inertia::render('Schools/Programs/ExamTimetables/Create', [
            'program' => $program->load('school'),
            'semesters' => $semesters,
            'lecturers' => $lecturers,
            'schoolCode' => $schoolCode,
        ]);
    }

    /**
     * Show specific program exam timetable
     */
    public function showProgramExamTimetable(Program $program, $timetable, $schoolCode)
    {
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        $examTimetable = ExamTimetable::with(['unit', 'semester'])
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
            ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
            ->where('exam_timetables.id', $timetable)
            ->where('units.program_id', $program->id)
            ->select(
                'exam_timetables.*',
                'units.name as unit_name',
                'units.code as unit_code',
                'classes.name as class_name',
                'semesters.name as semester_name'
            )
            ->firstOrFail();

        return Inertia::render('Schools/Programs/ExamTimetables/Show', [
            'examTimetable' => $examTimetable,
            'program' => $program->load('school'),
            'schoolCode' => $schoolCode,
        ]);
    }

    /**
     * Show edit form for program exam timetable
     */
    public function editProgramExamTimetable(Program $program, $timetable, $schoolCode)
    {
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        $examTimetable = ExamTimetable::with(['unit', 'semester'])
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->where('exam_timetables.id', $timetable)
            ->where('units.program_id', $program->id)
            ->select('exam_timetables.*')
            ->firstOrFail();

        $semesters = Semester::all();
        $lecturers = User::role('Lecturer')
            ->select('id', 'code', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
            ->get();

        return Inertia::render('Schools/Programs/ExamTimetables/Edit', [
            'examTimetable' => $examTimetable,
            'program' => $program->load('school'),
            'semesters' => $semesters,
            'lecturers' => $lecturers,
            'schoolCode' => $schoolCode,
        ]);
    }

    /**
     * ✅ FIXED: Update exam timetable for a specific program
     */
    /**
 * ✅ FIXED: Update exam timetable for a specific program
 */
public function updateProgramExamTimetable(Program $program, $timetable, Request $request, $schoolCode)
{
    $examTimetable = ExamTimetable::findOrFail($timetable);
    
    \Log::info('=== EXAM TIMETABLE UPDATE REQUEST ===', [
        'exam_id' => $examTimetable->id,
        'program_id' => $program->id,
        'school_id' => $program->school_id,
    ]);
    
    $validatedData = $request->validate([
        'semester_id' => 'required|integer|exists:semesters,id',
        'class_id' => 'required|integer|exists:classes,id',
        'unit_id' => 'required|integer|exists:units,id',
        'date' => 'required|date|date_format:Y-m-d',
        'day' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
        'start_time' => 'required|date_format:H:i',
        'end_time' => 'required|date_format:H:i|after:start_time',
        'chief_invigilator' => 'required|string|max:255',
        'no' => 'required|integer|min:1',
        'venue' => 'nullable|string|max:255',
        'location' => 'nullable|string|max:255',
    ]);

    try {
        if (empty($validatedData['venue'])) {
            $venueResult = $this->assignSmartVenue(
                $validatedData['no'],
                $validatedData['date'],
                $validatedData['start_time'],
                $validatedData['end_time'],
                $examTimetable->id
            );

            if ($venueResult['success']) {
                $validatedData['venue'] = $venueResult['venue'];
                $validatedData['location'] = $venueResult['location'];
            } else {
                $validatedData['venue'] = 'TBD';
                $validatedData['location'] = 'TBD';
            }
        }

        if (empty($validatedData['location'])) {
            $validatedData['location'] = $validatedData['venue'] === 'Remote' ? 'Online' : 'Main Campus';
        }

        // ✅✅✅ CRITICAL FIX: Add program_id and school_id to update
        $updateData = [
            'unit_id' => $validatedData['unit_id'],
            'semester_id' => $validatedData['semester_id'],
            'class_id' => $validatedData['class_id'],
            'program_id' => $program->id,  // ✅ THIS IS THE FIX
            'school_id' => $program->school_id,  // ✅ THIS IS THE FIX
            'date' => $validatedData['date'],
            'day' => $validatedData['day'],
            'start_time' => $validatedData['start_time'],
            'end_time' => $validatedData['end_time'],
            'venue' => $validatedData['venue'],
            'location' => $validatedData['location'],
            'no' => $validatedData['no'],
            'chief_invigilator' => $validatedData['chief_invigilator'],
        ];

        \Log::info('Updating exam timetable with data:', $updateData);

        $examTimetable->update($updateData);

        \Log::info('Program exam timetable updated successfully', [
            'exam_timetable_id' => $examTimetable->id,
            'program_id' => $examTimetable->program_id,
            'school_id' => $examTimetable->school_id,
        ]);

        return redirect()
            ->route('schools.' . strtolower($schoolCode) . '.programs.exam-timetables.index', $program)
            ->with('success', 'Exam timetable updated successfully for ' . $program->name . '!');

    } catch (\Exception $e) {
        \Log::error('Error updating program exam timetable: ' . $e->getMessage(), [
            'exam_timetable_id' => $examTimetable->id,
            'exception' => $e->getTraceAsString()
        ]);

        return redirect()->back()
            ->withErrors(['error' => 'Failed to update exam timetable: ' . $e->getMessage()])
            ->withInput();
    }
}

    /**
     * Delete program exam timetable
     */
    public function destroyProgramExamTimetable(Program $program, $timetable, $schoolCode)
    {
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        try {
            $examTimetable = ExamTimetable::leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
                ->where('exam_timetables.id', $timetable)
                ->where('units.program_id', $program->id)
                ->select('exam_timetables.*')
                ->firstOrFail();
            
            $examTimetable->delete();
            
            return redirect()->back()->with('success', 'Exam timetable deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Error deleting program exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error deleting exam timetable. Please try again.');
        }
    }

    /**
     * Download program exam timetable as PDF
     */
    public function downloadProgramExamTimetablePDF(Program $program, $schoolCode)
    {
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        try {
            $query = ExamTimetable::query()
                ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
                ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
                ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
                ->where('units.program_id', $program->id)
                ->select(
                    'exam_timetables.*',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'semesters.name as semester_name',
                    'classes.name as class_name'
                );

            $examTimetables = $query->orderBy('exam_timetables.date')
                ->orderBy('exam_timetables.start_time')
                ->get();

            if (!view()->exists('examtimetables.pdf')) {
                return redirect()->back()->with('error', 'PDF template not found. Please contact the administrator.');
            }

            $pdf = Pdf::loadView('examtimetables.pdf', [
                'examTimetables' => $examTimetables,
                'title' => $program->name . ' - Exam Timetable',
                'program' => $program,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            $pdf->setPaper('a4', 'landscape');

            return $pdf->download($program->code . '-exam-timetable.pdf');
        } catch (\Exception $e) {
            Log::error('Failed to generate program exam timetable PDF: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
 * Get classes with their units for bulk scheduling
 */
public function getClassesWithUnits(Program $program, $schoolCode)
{
    try {
        \Log::info('Getting classes with units for bulk scheduling', [
            'program_id' => $program->id,
            'program_name' => $program->name,
            'school_code' => $schoolCode
        ]);

        $classes = ClassModel::where('program_id', $program->id)
            ->get()
            ->map(function($class) use ($program) {
                // Get unit count for this class
                $unitCount = DB::table('units')
                    ->where('class_id', $class->id)
                    ->where('program_id', $program->id)
                    ->count();
                
                // Get student count
                $studentCount = DB::table('enrollments')
                    ->where('class_id', $class->id)
                    ->distinct('student_code')
                    ->count();
                
                // Check if code column exists
                $columns = \Schema::getColumnListing('classes');
                $hasCodeColumn = in_array('code', $columns);
                
                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'code' => $hasCodeColumn ? ($class->code ?? 'CLASS-' . $class->id) : 'CLASS-' . $class->id,
                    'semester_id' => $class->semester_id,
                    'program_id' => $class->program_id,
                    'unit_count' => $unitCount,
                    'student_count' => $studentCount,
                ];
            })
            ->filter(function($class) {
                // Only include classes that have units
                return $class['unit_count'] > 0;
            })
            ->values();

        \Log::info('Classes with units retrieved', [
            'program_id' => $program->id,
            'classes_count' => $classes->count(),
            'total_units' => $classes->sum('unit_count')
        ]);

        return response()->json([
            'success' => true,
            'classes' => $classes
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Failed to get classes with units', [
            'program_id' => $program->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch classes: ' . $e->getMessage()
        ], 500);
    }
}
/**
 * HELPER METHOD - Add this after your assignSmartVenue() method
 */
private function getVenueCapacity($date, $startTime, $endTime, $availableRooms)
{
    // Get exams already scheduled for this time slot
    $scheduledExams = ExamTimetable::where('date', $date)
        ->where(function($query) use ($startTime, $endTime) {
            $query->where(function($q) use ($startTime, $endTime) {
                $q->where('start_time', '<=', $startTime)
                  ->where('end_time', '>', $startTime);
            })
            ->orWhere(function($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                  ->where('end_time', '>=', $endTime);
            })
            ->orWhere(function($q) use ($startTime, $endTime) {
                $q->where('start_time', '>=', $startTime)
                  ->where('end_time', '<=', $endTime);
            });
        })
        ->get();

    // Calculate used capacity per room
    $roomUsage = [];
    foreach ($scheduledExams as $exam) {
        if (!isset($roomUsage[$exam->venue])) {
            $roomUsage[$exam->venue] = 0;
        }
        $roomUsage[$exam->venue] += $exam->no;
    }

    // Create room availability map
    $roomAvailability = [];
    foreach ($availableRooms as $room) {
        $used = $roomUsage[$room->name] ?? 0;
        $roomAvailability[] = [
            'name' => $room->name,
            'location' => $room->location,
            'capacity' => $room->capacity,
            'used' => $used,
            'available' => $room->capacity - $used
        ];
    }

    // Sort by available capacity (largest first)
    usort($roomAvailability, function($a, $b) {
        return $b['available'] - $a['available'];
    });

    return $roomAvailability;
}


/**
 * COMPLETE FIXED METHOD - Replace your entire bulkScheduleExams method with this
 */
public function bulkScheduleExams(Program $program, Request $request, $schoolCode)
{
    \Log::info('=== BULK EXAM SCHEDULING STARTED ===', [
        'program_id' => $program->id,
        'program_name' => $program->name,
        'request_data' => $request->all()
    ]);

    $validated = $request->validate([
        'selected_class_units' => 'required|array|min:1',
        'start_date' => 'required|date|after_or_equal:today',
        'end_date' => 'required|date|after:start_date',
        'exam_duration_hours' => 'required|integer|min:1|max:4',
        'gap_between_exams_days' => 'required|integer|min:0|max:7',
        'start_time' => 'required|date_format:H:i',
        'excluded_days' => 'nullable|array',
        'excluded_days.*' => 'string|in:Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
        'max_exams_per_day' => 'required|integer|min:1|max:10',
        'selected_examrooms' => 'nullable|array'
    ]);

    try {
        DB::beginTransaction();

        $scheduledExams = [];
        $conflicts = [];
        $warnings = [];
        
        // Build time slots from frontend
        $timeSlotMap = [];
        foreach ($validated['selected_class_units'] as $unit) {
            if (isset($unit['assigned_start_time']) && isset($unit['assigned_end_time'])) {
                $key = $unit['assigned_start_time'] . '-' . $unit['assigned_end_time'];
                if (!isset($timeSlotMap[$key])) {
                    $timeSlotMap[$key] = [
                        'start' => $unit['assigned_start_time'],
                        'end' => $unit['assigned_end_time'],
                        'label' => 'Slot ' . (count($timeSlotMap) + 1)
                    ];
                }
            }
        }
        $timeSlots = !empty($timeSlotMap) ? array_values($timeSlotMap) : [
            ['start' => '09:00', 'end' => '11:00', 'label' => 'Slot 1'],
            ['start' => '11:30', 'end' => '13:30', 'label' => 'Slot 2']
        ];

        // Get all existing exams for conflict checking
        $existingExams = ExamTimetable::whereBetween('date', [
            $validated['start_date'], 
            $validated['end_date']
        ])->get();

        // Extract class IDs from selected_class_units
        $classIds = collect($validated['selected_class_units'])
            ->pluck('class_id')
            ->unique()
            ->toArray();

        // Get unique units across all selected classes
        $unitsData = DB::table('unit_assignments')
            ->join('units', 'unit_assignments.unit_id', '=', 'units.id')
            ->whereIn('unit_assignments.class_id', $classIds)
            ->where('units.program_id', $program->id)
            ->select(
                'units.id as unit_id',
                'units.code as unit_code',
                'units.name as unit_name',
                'unit_assignments.class_id',
                'unit_assignments.lecturer_code',
                'unit_assignments.semester_id'
            )
            ->get();
        // Group units manually
        $unitsByClass = [];
        foreach ($unitsData as $unitRecord) {
            $unitId = $unitRecord->unit_id;
            if (!isset($unitsByClass[$unitId])) {
                $unitsByClass[$unitId] = [];
            }
            $unitsByClass[$unitId][] = (array) $unitRecord;
        }

        if (empty($unitsByClass)) {
            throw new \Exception('No units found for the selected classes');
        }

        \Log::info('Units grouped for bulk scheduling', [
            'total_units' => count($unitsByClass),
            'units' => array_keys($unitsByClass),
            'exam_period' => "{$validated['start_date']} to {$validated['end_date']}"
        ]);

        // Calculate gap
        $examPeriodDays = Carbon::parse($validated['start_date'])->diffInDays(Carbon::parse($validated['end_date']));
        $totalUnits = count($unitsByClass);
        
        if ($validated['gap_between_exams_days'] == 0 && $totalUnits > 0) {
            $recommendedGap = max(1, floor($examPeriodDays / $totalUnits));
            $gapDays = min($recommendedGap, 3);
            \Log::info("Auto-calculated gap: {$gapDays} days", [
                'exam_period_days' => $examPeriodDays,
                'total_units' => $totalUnits
            ]);
        } else {
            $gapDays = $validated['gap_between_exams_days'];
        }

        $excludedDays = $validated['excluded_days'] ?? [];
        $maxExamsPerDay = $validated['max_exams_per_day'];

        // ✅ FIX: Load selected exam rooms
        $availableRooms = Examroom::where('is_active', true)
            ->whereIn('id', $validated['selected_examrooms'] ?? [])
            ->orderBy('capacity', 'desc')
            ->get();

        if ($availableRooms->isEmpty()) {
            throw new \Exception('No exam rooms selected or available');
        }

        // Track last exam date PER CLASS
        $classLastExamDate = [];
        
        // Track exams scheduled per day per time slot
        $dailySchedule = [];
        
        $currentDate = Carbon::parse($validated['start_date']);

        // Loop through units
        foreach ($unitsByClass as $unitId => $unitRecords) {
            $firstUnit = $unitRecords[0];
            
            // Get all class IDs studying this unit (from selected classes only)
            $selectedClassIds = array_unique(array_column($unitRecords, 'class_id'));
            
            // ✅ FIX: Get ALL classes taking this unit (not just selected ones)
            $allClassesTakingUnit = DB::table('unit_assignments')
                ->where('unit_id', $unitId)
                ->distinct()
                ->pluck('class_id');
            
            // ✅ FIX: Count students across ALL classes taking this unit
            $totalStudents = DB::table('enrollments')
                ->where('unit_id', $unitId)
                ->whereIn('class_id', $allClassesTakingUnit)  // ✅ All classes, not just selected
                ->where('status', 'enrolled')
                ->distinct('student_code')
                ->count('student_code');
            
            if ($totalStudents === 0) {
                $warnings[] = "Skipped {$firstUnit['unit_code']} - No students enrolled across selected classes";
                continue;
            }
            
            // Get lecturer
            $lecturerCodes = array_filter(array_column($unitRecords, 'lecturer_code'));
            $lecturerCode = null;
            
            if (!empty($lecturerCodes)) {
                $lecturerCounts = array_count_values($lecturerCodes);
                arsort($lecturerCounts);
                $lecturerCode = array_key_first($lecturerCounts);
            }
            
            $lecturer = $lecturerCode ? User::where('code', $lecturerCode)->first() : null;
            $chiefInvigilator = $lecturer 
                ? trim("{$lecturer->first_name} {$lecturer->last_name}")
                : 'No lecturer assigned';
            
            \Log::info("Processing unit for scheduling", [
                'unit_code' => $firstUnit['unit_code'],
                'unit_name' => $firstUnit['unit_name'],
                'selected_class_count' => count($selectedClassIds),
                'all_classes_count' => count($allClassesTakingUnit),
                'total_students' => $totalStudents,
                'lecturer_code' => $lecturerCode,
                'chief_invigilator' => $chiefInvigilator,
                'selected_classes' => $selectedClassIds,
                'all_classes' => $allClassesTakingUnit->toArray()
            ]);

            // Find earliest allowed date considering gap for selected classes only
            $earliestAllowedDate = clone $currentDate;
            
            foreach ($selectedClassIds as $classId) {
                if (isset($classLastExamDate[$classId])) {
                    $classMinDate = $classLastExamDate[$classId]->copy()->addDays($gapDays + 1);
                    if ($classMinDate->gt($earliestAllowedDate)) {
                        $earliestAllowedDate = $classMinDate;
                    }
                }
            }
            
            \Log::info("Earliest allowed date for unit", [
                'unit_code' => $firstUnit['unit_code'],
                'earliest_date' => $earliestAllowedDate->format('Y-m-d'),
                'reason' => 'Respecting gap between exams for classes'
            ]);

            // Find next available slot
            $scheduled = false;
            $attempts = 0;
            $maxAttempts = 300;
            $attemptDate = clone $earliestAllowedDate;
            $endDate = Carbon::parse($validated['end_date']);
            $failureReasons = [];

            while (!$scheduled && $attempts < $maxAttempts && $attemptDate->lte($endDate)) {
                $attempts++;

                // Skip excluded days
                if (in_array($attemptDate->format('l'), $excludedDays)) {
                    $attemptDate->addDay();
                    continue;
                }

                $examDate = $attemptDate->format('Y-m-d');
                $examDay = $attemptDate->format('l');
                
                // Initialize daily schedule for this date
                if (!isset($dailySchedule[$examDate])) {
                    $dailySchedule[$examDate] = [];
                }

                $dayAttempted = false;

            // Use assigned time from frontend (already randomized)
                $assignedStart = $firstUnit['assigned_start_time'];
                $assignedEnd = $firstUnit['assigned_end_time'];
                
                $dayAttempted = true;
                
                // Initialize time slot counter
                if (!isset($dailySchedule[$examDate][$assignedStart])) {
                    $dailySchedule[$examDate][$assignedStart] = 0;
                }

                $examStartTime = Carbon::parse($examDate . ' ' . $assignedStart);
                $examEndTime = Carbon::parse($examDate . ' ' . $assignedEnd);



                    // ✅ FIX: Smart venue assignment with room sharing
                    $roomAvailability = $this->getVenueCapacity(
                        $examDate,
                        $examStartTime->format('H:i'),
                        $examEndTime->format('H:i'),
                        $availableRooms
                    );

                    $venueResult = ['success' => false];
                    foreach ($roomAvailability as $room) {
                        if ($room['available'] >= $totalStudents) {
                            $venueResult = [
                                'success' => true,
                                'venue' => $room['name'],
                                'location' => $room['location'],
                                'capacity' => $room['capacity'],
                                'used_before' => $room['used'],
                                'available_before' => $room['available']
                            ];
                            
                            \Log::info("📍 Room allocated", [
                                'venue' => $room['name'],
                                'unit' => $firstUnit['unit_code'],
                                'students' => $totalStudents,
                                'was_used' => $room['used'],
                                'now_used' => $room['used'] + $totalStudents,
                                'utilization' => round((($room['used'] + $totalStudents) / $room['capacity']) * 100, 1) . '%'
                            ]);
                            break;
                        }
                    }

                    if (!$venueResult['success']) {
                        $failureReasons[] = "Date {$examDate} {$timeSlot['label']}: No venue with capacity for {$totalStudents} students";
                        continue;
                    }

                    // Check conflicts for selected classes only
                    $hasConflict = false;
                    $conflictReasons = [];

                    // Check class conflicts (for selected classes)
                    foreach ($selectedClassIds as $classId) {
                        $classConflict = $existingExams->first(function($exam) use ($examDate, $examStartTime, $examEndTime, $classId) {
                            if ($exam->date !== $examDate || $exam->class_id !== $classId) {
                                return false;
                            }
                            
                            $existingStart = Carbon::parse($exam->date . ' ' . $exam->start_time);
                            $existingEnd = Carbon::parse($exam->date . ' ' . $exam->end_time);
                            
                            return $existingStart->lt($examEndTime) && $existingEnd->gt($examStartTime);
                        });

                        if ($classConflict) {
                            $hasConflict = true;
                            $conflictReasons[] = "Class {$classId} has exam for {$classConflict->unit_code}";
                            break;
                        }
                    }

                    // Check lecturer conflicts
                    if (!$hasConflict && $chiefInvigilator !== 'No lecturer assigned') {
                        $lecturerConflict = $existingExams->first(function($exam) use ($examDate, $examStartTime, $examEndTime, $chiefInvigilator) {
                            if ($exam->date !== $examDate || $exam->chief_invigilator !== $chiefInvigilator) {
                                return false;
                            }
                            
                            $existingStart = Carbon::parse($exam->date . ' ' . $exam->start_time);
                            $existingEnd = Carbon::parse($exam->date . ' ' . $exam->end_time);
                            
                            return $existingStart->lt($examEndTime) && $existingEnd->gt($examStartTime);
                        });

                        if ($lecturerConflict) {
                            $hasConflict = true;
                            $conflictReasons[] = "Lecturer {$chiefInvigilator} invigilating {$lecturerConflict->unit_code}";
                        }
                    }

                    if ($hasConflict) {
                        $failureReasons[] = "Date {$examDate} {$timeSlot['label']}: " . implode(', ', $conflictReasons);
                        continue;
                    }

                    // ✅ SUCCESS - Create exam
                    $primaryClassId = $selectedClassIds[0];
                    $primaryClass = ClassModel::find($primaryClassId);
                    
                    $examData = [
                        'unit_id' => $unitId,
                        'semester_id' => $firstUnit['semester_id'] ?? $primaryClass->semester_id,
                        'class_id' => $primaryClassId,
                        'program_id' => $program->id,
                        'school_id' => $program->school_id,
                        'date' => $examDate,
                        'day' => $examDay,
                        'start_time' => $examStartTime->format('H:i'),
                        'end_time' => $examEndTime->format('H:i'),
                        'venue' => $venueResult['venue'],
                        'location' => $venueResult['location'] ?? 'Main Campus',
                        'no' => $totalStudents,  // ✅ Total across ALL classes
                        'chief_invigilator' => $chiefInvigilator,
                    ];

                    $createdExam = ExamTimetable::create($examData);
                    $existingExams->push($createdExam);
                    
                    // Update last exam date for selected classes
                    $examDateCarbon = Carbon::parse($examDate);
                    foreach ($selectedClassIds as $classId) {
                        $classLastExamDate[$classId] = clone $examDateCarbon;
                    }
                    
                    // Track this exam in daily schedule
                    $dailySchedule[$examDate][$assignedStart]++;
                    
                    $classNames = ClassModel::whereIn('id', $selectedClassIds)->pluck('name')->toArray();
                    
                    $scheduledExams[] = [
                        'exam' => $createdExam,
                        'unit_code' => $firstUnit['unit_code'],
                        'unit_name' => $firstUnit['unit_name'],
                        'classes' => $classNames,
                        'class_count' => count($selectedClassIds),
                        'total_students' => $totalStudents,  // ✅ Total across all classes
                        'date' => $examDate,
                        'time' => "{$examStartTime->format('H:i')} - {$examEndTime->format('H:i')}",
                        'venue' => $venueResult['venue'],
                        'lecturer' => $chiefInvigilator
                    ];

                    $scheduled = true;

                    \Log::info("✅ Scheduled exam successfully", [
                        'unit' => $firstUnit['unit_code'],
                        'classes' => $classNames,
                        'students' => $totalStudents,
                        'date' => $examDate,
                        'time_slot' => $assignedStart . '-' . $assignedEnd,
                        'venue' => $venueResult['venue'],
                        'lecturer' => $chiefInvigilator,
                        'attempts_taken' => $attempts
                    ]);
                    
                 
                
                
                if (!$scheduled && $dayAttempted) {
                    $attemptDate->addDay();
                }
            }

            if (!$scheduled) {
    $reason = 'Could not find suitable slot within date range';
    
    // ✅ FIX: Check BOTH conflictReasons AND failureReasons for capacity issues
    $allReasons = implode(' ', array_merge($conflictReasons ?? [], $failureReasons ?? []));
    
    if (str_contains($allReasons, 'insufficient capacity') || 
        str_contains($allReasons, 'No venue with capacity') ||
        str_contains(strtolower($allReasons), 'capacity')) {
        $reason = 'Insufficient venue capacity - all exam rooms are full';
    } else if (!empty($failureReasons)) {
        $reason = implode('; ', $failureReasons);
    } else if (!empty($conflictReasons)) {
        $reason = implode('; ', $conflictReasons);
    }
    
    $conflicts[] = [
        'unit_code' => $unit->code,
        'unit_name' => $unit->name,
        'class_name' => $class->name,
        'reason' => $reason,
        'all_failure_reasons' => $failureReasons ?? [],
        'conflict_details' => $conflictReasons ?? []
    ];
}


        }

        DB::commit();

        \Log::info('=== BULK SCHEDULING COMPLETED ===', [
            'scheduled_count' => count($scheduledExams),
            'conflicts_count' => count($conflicts),
            'warnings_count' => count($warnings),
            'gap_days_used' => $gapDays,
            'date_distribution' => array_map(function($slots) {
                return array_sum($slots);
            }, $dailySchedule)
        ]);

        return response()->json([
            'success' => true,
            'scheduled' => $scheduledExams,
            'conflicts' => $conflicts,
            'warnings' => $warnings,
            'summary' => [
                'total_scheduled' => count($scheduledExams),
                'total_conflicts' => count($conflicts),
                'total_warnings' => count($warnings),
                'gap_days_used' => $gapDays,
                'date_distribution' => $dailySchedule
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        
        \Log::error('Bulk scheduling failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Bulk scheduling failed: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * ✅ HELPER METHOD 1: Find venue with sufficient REMAINING capacity
 * Allows multiple exams to share the same venue
 * 
 * ADD THIS METHOD to your controller class
 */
private function findVenueWithCapacity($examrooms, $requiredCapacity, $examDate, $examStartTime, $examEndTime, &$venueCapacityUsage)
{
    $timeKey = $examStartTime->format('Y-m-d H:i') . '-' . $examEndTime->format('H:i');
    
    foreach ($examrooms as $room) {
        // Calculate how much capacity is already used
        $usedCapacity = $venueCapacityUsage[$room->name][$timeKey] ?? 0;
        $remainingCapacity = $room->capacity - $usedCapacity;
        
        // Check if there's enough remaining capacity
        if ($remainingCapacity >= $requiredCapacity) {
            return [
                'success' => true,
                'venue' => $room->name,
                'location' => $room->location,
                'total_capacity' => $room->capacity,
                'capacity_used' => $usedCapacity + $requiredCapacity,
                'remaining_capacity' => $remainingCapacity - $requiredCapacity
            ];
        }
    }
    
    return [
        'success' => false,
        'message' => 'No venue with sufficient remaining capacity'
    ];
}

/**
 * ✅ HELPER METHOD 2: Update venue capacity usage tracker
 * 
 * ADD THIS METHOD to your controller class
 */
private function updateVenueCapacityUsage(&$venueCapacityUsage, $venueName, $timeKey, $studentCount)
{
    if (!isset($venueCapacityUsage[$venueName])) {
        $venueCapacityUsage[$venueName] = [];
    }
    
    if (!isset($venueCapacityUsage[$venueName][$timeKey])) {
        $venueCapacityUsage[$venueName][$timeKey] = 0;
    }
    
    $venueCapacityUsage[$venueName][$timeKey] += $studentCount;
}
}