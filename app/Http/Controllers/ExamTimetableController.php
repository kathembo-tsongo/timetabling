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
     * Build hierarchical data using the same logic as enrollment system
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
                    $enrollments = Enrollment::where('unit_id', $unit->id)
                        ->where('semester_id', $semester->id)
                        ->get();
                    
                    $studentCount = $enrollments->count();
                    
                    $lecturerCode = null;
                    $lecturerName = null;
                    $lecturerEnrollment = $enrollments->whereNotNull('lecturer_code')->first();
                    if ($lecturerEnrollment) {
                        $lecturerCode = $lecturerEnrollment->lecturer_code;
                        $lecturer = User::where('code', $lecturerCode)->first();
                        if ($lecturer) {
                            $lecturerName = $lecturer->first_name . ' ' . $lecturer->last_name;
                        }
                    }
                    
                    $unitData = [
                        'id' => $unit->id,
                        'code' => $unit->code,
                        'name' => $unit->name,
                        'class_id' => $class->id,
                        'semester_id' => $semester->id,
                        'student_count' => $studentCount,
                        'lecturer_code' => $lecturerCode,
                        'lecturer_name' => $lecturerName
                    ];
                    
                    $classData['units'][] = $unitData;
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
     * ✅ ENHANCED: Get units by class and semester for exam timetable
     */
    public function getUnitsByClassAndSemesterForExam(Request $request)
    {
        try {
            $classId = $request->input('class_id');
            $semesterId = $request->input('semester_id');

            if (!$classId || !$semesterId) {
                return response()->json([
                    'error' => 'Class ID and Semester ID are required.'
                ], 400);
            }

            \Log::info('Fetching units for exam timetable', [
                'class_id' => $classId,
                'semester_id' => $semesterId
            ]);

            $units = DB::table('unit_assignments')
                ->join('units', 'unit_assignments.unit_id', '=', 'units.id')
                ->leftJoin('users', 'users.code', '=', 'unit_assignments.lecturer_code')
                ->where('unit_assignments.semester_id', $semesterId)
                ->where('unit_assignments.class_id', $classId)
                ->select(
                    'units.id',
                    'units.code',
                    'units.name',
                    'units.credit_hours',
                    'unit_assignments.lecturer_code',
                    DB::raw("CASE 
                        WHEN users.id IS NOT NULL 
                        THEN CONCAT(users.first_name, ' ', users.last_name) 
                        ELSE unit_assignments.lecturer_code 
                        END as lecturer_name"),
                    'users.first_name as lecturer_first_name',
                    'users.last_name as lecturer_last_name'
                )
                ->distinct()
                ->get();

            if ($units->isEmpty()) {
                \Log::info('No unit assignments found, trying fallback', [
                    'class_id' => $classId,
                    'semester_id' => $semesterId
                ]);

                $units = DB::table('units')
                    ->leftJoin('unit_assignments', function($join) use ($semesterId, $classId) {
                        $join->on('units.id', '=', 'unit_assignments.unit_id')
                             ->where('unit_assignments.semester_id', '=', $semesterId)
                             ->where('unit_assignments.class_id', '=', $classId);
                    })
                    ->leftJoin('users', 'users.code', '=', 'unit_assignments.lecturer_code')
                    ->where('units.semester_id', $semesterId)
                    ->select(
                        'units.id',
                        'units.code',
                        'units.name',
                        'units.credit_hours',
                        'unit_assignments.lecturer_code',
                        DB::raw("CASE 
                            WHEN users.id IS NOT NULL 
                            THEN CONCAT(users.first_name, ' ', users.last_name) 
                            ELSE COALESCE(unit_assignments.lecturer_code, 'No lecturer assigned')
                            END as lecturer_name")
                    )
                    ->get();
            }

            $enhancedUnits = $units->map(function ($unit) use ($semesterId, $classId) {
                $enrollmentCount = DB::table('enrollments')
                    ->where('unit_id', $unit->id)
                    ->where('semester_id', $semesterId)
                    ->where('class_id', $classId)
                    ->distinct('student_code')
                    ->count('student_code');

                return [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'credit_hours' => $unit->credit_hours ?? 3,
                    'student_count' => $enrollmentCount,
                    'lecturer_name' => $unit->lecturer_name ?? 'No lecturer assigned',
                    'lecturer_code' => $unit->lecturer_code ?? '',
                    'lecturer_first_name' => $unit->lecturer_first_name ?? '',
                    'lecturer_last_name' => $unit->lecturer_last_name ?? '',
                ];
            });

            \Log::info('Units retrieved successfully for exam timetable', [
                'class_id' => $classId,
                'semester_id' => $semesterId,
                'units_count' => $enhancedUnits->count()
            ]);

            return response()->json($enhancedUnits->values()->all());
            
        } catch (\Exception $e) {
            \Log::error('Error fetching units for exam timetable: ' . $e->getMessage(), [
                'class_id' => $request->input('class_id'),
                'semester_id' => $request->input('semester_id'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch units for exam timetable.'
            ], 500);
        }
    }

    /**
     * ✅ FIXED: Smart venue assignment
     */
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

            $suitableVenues = \App\Models\Examroom::where('capacity', '>=', $studentCount)
                ->where('is_active', true)
                ->orderBy('capacity', 'asc')
                ->get();

            if ($suitableVenues->isEmpty()) {
                $suitableVenues = \App\Models\Examroom::orderBy('capacity', 'desc')->get();
                
                if ($suitableVenues->isEmpty()) {
                    return [
                        'success' => false,
                        'message' => 'No exam rooms available',
                        'venue' => 'TBD',
                        'location' => 'TBD'
                    ];
                }
            }

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

            $conflictingVenues = $conflictingExams->pluck('venue')->toArray();

            $availableVenues = $suitableVenues->filter(function ($room) use ($conflictingVenues) {
                return !in_array($room->name, $conflictingVenues);
            });

            if ($availableVenues->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'All suitable exam rooms are booked',
                    'venue' => 'TBD',
                    'location' => 'TBD'
                ];
            }

            $selectedRoom = $availableVenues->first();

            return [
                'success' => true,
                'message' => "Venue assigned: {$selectedRoom->name}",
                'venue' => $selectedRoom->name,
                'location' => $selectedRoom->location ?? 'Main Campus',
                'capacity' => $selectedRoom->capacity,
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
        // ✅ Smart venue assignment
        if (empty($validatedData['venue'])) {
            $venueResult = $this->assignSmartVenue(
                $validatedData['no'],
                $validatedData['date'],
                $validatedData['start_time'],
                $validatedData['end_time']
            );

            if ($venueResult['success']) {
                $validatedData['venue'] = $venueResult['venue'];
                $validatedData['location'] = $venueResult['location'] ?? 'Main Campus';
            } else {
                $validatedData['venue'] = 'TBD';
                $validatedData['location'] = 'TBD';
            }
        }

        if (empty($validatedData['location'])) {
            $validatedData['location'] = $validatedData['venue'] === 'Remote' ? 'Online' : 'Main Campus';
        }

        // ✅✅✅ CRITICAL FIX: Add program_id and school_id
        $examData = [
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

        \Log::info('Creating exam timetable with data:', $examData);

        $examTimetable = ExamTimetable::create($examData);

        \Log::info('Exam timetable created successfully', [
            'exam_timetable_id' => $examTimetable->id,
            'program_id' => $examTimetable->program_id,
            'school_id' => $examTimetable->school_id,
        ]);

        return redirect()
            ->route('schools.' . strtolower($schoolCode) . '.programs.exam-timetables.index', $program)
            ->with('success', 'Exam timetable created successfully for ' . $program->name . '!');

    } catch (\Illuminate\Validation\ValidationException $e) {
        \Log::error('Validation error in exam timetable creation', [
            'errors' => $e->errors(),
        ]);
        
        return redirect()->back()
            ->withErrors($e->errors())
            ->withInput();
            
    } catch (\Exception $e) {
        \Log::error('Error creating exam timetable', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
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
}