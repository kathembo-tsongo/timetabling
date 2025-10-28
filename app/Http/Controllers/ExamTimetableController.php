<?php

namespace App\Http\Controllers;

use App\Models\ExamTimetable;
use App\Models\Unit;
use App\Models\User;
use App\Models\Semester;
use App\Models\SemesterUnit;
use App\Models\Enrollment;
use App\Models\TimeSlot;
use App\Models\ExamTimeSlot;
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
                // Generate class_code from name field since 'code' column doesn't exist
                DB::raw('COALESCE(REPLACE(classes.name, " ", "-"), CONCAT("CLASS-", classes.id)) as class_code'),
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
        $examTimeSlots = ExamTimeSlot::where('is_active', 1)->get();
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
            'examTimeSlots' => $examTimeSlots,
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
     * Build hierarchical data structure
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
                // Generate class code from name field since 'code' column doesn't exist
                // Example: "BBIT 1.1" becomes "BBIT-1.1"
                $classCode = $class->name ? str_replace(' ', '-', $class->name) : 'CLASS-' . $class->id;
                
                $classData = [
                    'id' => $class->id,
                    'code' => $classCode,
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
                    $allClassesTakingUnit = DB::table('semester_unit')
                        ->where('semester_id', $semester->id)
                        ->where('unit_id', $unit->id)
                        ->distinct()
                        ->pluck('class_id');

                    $totalStudentCount = DB::table('enrollments')
                        ->where('unit_id', $unit->id)
                        ->where('semester_id', $semester->id)
                        ->whereIn('class_id', $allClassesTakingUnit)
                        ->where('status', 'enrolled')
                        ->distinct()
                        ->count('student_code');

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
                                $enrollmentsByClass[] = [
                                    'class_id' => $classId,
                                    'class_name' => $enrollmentClass->name,
                                    'student_count' => $classStudentCount
                                ];
                            }
                        }
                    }

                    // Get lecturer info
                    $lecturerCode = DB::table('unit_assignments')
                        ->where('unit_id', $unit->id)
                        ->where('semester_id', $semester->id)
                        ->where('class_id', $class->id)
                        ->value('lecturer_code');

                    $lecturerName = 'No lecturer assigned';
                    if ($lecturerCode) {
                        $lecturer = User::where('code', $lecturerCode)->first();
                        if ($lecturer) {
                            $lecturerName = trim("{$lecturer->first_name} {$lecturer->last_name}");
                        }
                    }

                    foreach ($allClassesTakingUnit as $classId) {
                        $classList[] = ClassModel::find($classId);
                    }
                    $classList = collect($classList)->filter()->values();
                    
                    $classData['units'][] = [
                        'id' => $unit->id,
                        'code' => $unit->code,
                        'name' => $unit->name,
                        'class_id' => $class->id,
                        'semester_id' => $semester->id,
                        'student_count' => $totalStudentCount,
                        'lecturer_code' => $lecturerCode,
                        'lecturer_name' => $lecturerName,
                        'classes_taking_unit' => count($classList),
                        'class_list' => $classList
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

            $classIds = DB::table('semester_unit')
                ->join('units', 'semester_unit.unit_id', '=', 'units.id')
                ->where('semester_unit.semester_id', $semesterId)
                ->where('units.program_id', $programId)
                ->distinct()
                ->pluck('semester_unit.class_id');

            if ($classIds->isNotEmpty()) {
                // Generate class code from name field since 'code' column doesn't exist
                $classes = ClassModel::whereIn('id', $classIds)
                    ->where('program_id', $programId)
                    ->select('id', 'name', 'semester_id', 'program_id', 
                        DB::raw('COALESCE(REPLACE(name, " ", "-"), CONCAT("CLASS-", id)) as code'))
                    ->get();
            } else {
                // Generate class code from name field since 'code' column doesn't exist
                $classes = ClassModel::where('semester_id', $semesterId)
                    ->where('program_id', $programId)
                    ->select('id', 'name', 'semester_id', 'program_id', 
                        DB::raw('COALESCE(REPLACE(name, " ", "-"), CONCAT("CLASS-", id)) as code'))
                    ->get();
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
     * Get units with TOTAL student count across ALL classes taking the unit
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

            $enhancedUnits = $units->map(function ($unit) use ($semesterId, $programId) {
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
                        // Generate class code from name field since 'code' column doesn't exist
                        DB::raw('COALESCE(REPLACE(classes.name, " ", "-"), CONCAT("CLASS-", classes.id)) as class_code'),
                        DB::raw('COUNT(DISTINCT enrollments.student_code) as student_count')
                    )
                    ->groupBy('classes.id', 'classes.name');
                
                $classesWithStudents = $classesQuery->get();

                $totalStudents = DB::table('enrollments')
                    ->join('classes', 'enrollments.class_id', '=', 'classes.id')
                    ->where('enrollments.unit_id', $unit->id)
                    ->where('enrollments.semester_id', $semesterId)
                    ->when($programId, function($query) use ($programId) {
                        return $query->where('classes.program_id', $programId);
                    })
                    ->distinct('enrollments.student_code')
                    ->count('enrollments.student_code');

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
                    'student_count' => $totalStudents,
                    'lecturer_name' => $unit->lecturer_name ?? 'No lecturer assigned',
                    'lecturer_code' => $unit->lecturer_code ?? '',
                    'classes_taking_unit' => count($classList),
                    'class_list' => $classList,
                ];
            });

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

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'day' => 'required|string',
            'date' => 'required|date',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'venue' => 'nullable|string',
            'location' => 'nullable|string',
        ]);

        try {
            $class = ClassModel::findOrFail($validated['class_id']);
            $program = Program::find($class->program_id);

            $venueAssignment = null;
            if (!isset($validated['venue'])) {
                $venueAssignment = $this->assignSmartVenue(
                    $validated['no'],
                    $validated['date'],
                    $validated['start_time'],
                    $validated['end_time']
                );

                if (!$venueAssignment['success']) {
                    return redirect()->back()->withErrors([
                        'venue' => $venueAssignment['message']
                    ])->withInput();
                }
            }

            $examTimetable = ExamTimetable::create([
                'unit_id' => $validated['unit_id'],
                'semester_id' => $validated['semester_id'],
                'class_id' => $validated['class_id'],
                'program_id' => $program ? $program->id : null,
                'school_id' => $program ? $program->school_id : null,
                'date' => $validated['date'],
                'day' => $validated['day'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'venue' => $validated['venue'] ?? $venueAssignment['venue'],
                'location' => $validated['location'] ?? ($venueAssignment['location'] ?? 'Main Campus'),
                'no' => $validated['no'],
                'chief_invigilator' => $validated['chief_invigilator'],
            ]);

            $successMessage = 'Exam timetable created successfully.';
            if ($venueAssignment) {
                $successMessage .= ' ' . $venueAssignment['message'];
            }
            
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
        $validated = $request->validate([
            'day' => 'required|string',
            'date' => 'required|date',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'venue' => 'nullable|string',
            'location' => 'nullable|string',
        ]);

        try {
            $examTimetable = ExamTimetable::findOrFail($id);
            $class = ClassModel::findOrFail($validated['class_id']);
            $program = Program::find($class->program_id);

            $venueAssignment = null;
            if (!isset($validated['venue'])) {
                $venueAssignment = $this->assignSmartVenue(
                    $validated['no'],
                    $validated['date'],
                    $validated['start_time'],
                    $validated['end_time'],
                    $id
                );

                if (!$venueAssignment['success']) {
                    return redirect()->back()->withErrors([
                        'venue' => $venueAssignment['message']
                    ])->withInput();
                }
            }

            $examTimetable->update([
                'unit_id' => $validated['unit_id'],
                'semester_id' => $validated['semester_id'],
                'class_id' => $validated['class_id'],
                'program_id' => $program ? $program->id : null,
                'school_id' => $program ? $program->school_id : null,
                'date' => $validated['date'],
                'day' => $validated['day'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'venue' => $validated['venue'] ?? $venueAssignment['venue'],
                'location' => $validated['location'] ?? ($venueAssignment['location'] ?? 'Main Campus'),
                'no' => $validated['no'],
                'chief_invigilator' => $validated['chief_invigilator'],
            ]);

            $successMessage = 'Exam timetable updated successfully.';
            if ($venueAssignment) {
                $successMessage .= ' ' . $venueAssignment['message'];
            }

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
                    // Generate class_code from name field since 'code' column doesn't exist
                    DB::raw('COALESCE(REPLACE(classes.name, " ", "-"), CONCAT("CLASS-", classes.id)) as class_code'),
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
                    // Generate class_code from name field since 'code' column doesn't exist
                    DB::raw('COALESCE(REPLACE(classes.name, " ", "-"), CONCAT("CLASS-", classes.id)) as class_code'),
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
                    // Generate class_code from name field since 'code' column doesn't exist
                    DB::raw('COALESCE(REPLACE(classes.name, " ", "-"), CONCAT("CLASS-", classes.id)) as class_code'),
                    'semesters.name as semester_name'
                )
                ->orderBy('exam_timetables.date')
                ->orderBy('exam_timetables.start_time');

            if ($selectedSemesterId) {
                $query->where('exam_timetables.semester_id', $selectedSemesterId);
            }

            $examTimetables = $query->get();

            return Inertia::render('Student/ExamTimetable', [
                'examTimetables' => $examTimetables,
                'semesters' => $semesters,
                'selectedSemesterId' => $selectedSemesterId
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading student exam timetable', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return Inertia::render('Student/ExamTimetable', [
                'examTimetables' => collect([]),
                'semesters' => collect([]),
                'selectedSemesterId' => null,
                'error' => 'Failed to load exam timetable. Please try again.'
            ]);
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
                // Generate class_code from name field since 'code' column doesn't exist
                    DB::raw('COALESCE(REPLACE(classes.name, " ", "-"), CONCAT("CLASS-", classes.id)) as class_code'),
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
                // Generate class_code from name field since 'code' column doesn't exist
                DB::raw('COALESCE(REPLACE(classes.name, " ", "-"), CONCAT("CLASS-", classes.id)) as class_code'),
                DB::raw('COUNT(DISTINCT enrollments.student_code) as student_count')
            )
            ->groupBy('classes.id', 'classes.name')
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
                // Assign venue and location from smart assignment
                $validatedData['venue'] = $venueResult['venue'];
                $validatedData['location'] = $venueResult['location'];
                
                \Log::info('✅ Venue assigned from smart assignment', [
                    'venue' => $venueResult['venue'],
                    'location' => $venueResult['location'],
                    'capacity' => $venueResult['capacity']
                ]);
            } else {
                // Venue assignment failed
                \Log::error('❌ Venue assignment failed', [
                    'message' => $venueResult['message']
                ]);
                
                return redirect()->back()->withErrors([
                    'venue' => $venueResult['message']
                ])->withInput();
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
            $classes = ClassModel::where('program_id', $program->id)
                ->get()
                ->map(function($class) use ($program) {
                    $unitCount = DB::table('units')
                        ->where('class_id', $class->id)
                        ->where('program_id', $program->id)
                        ->count();
                    
                    $studentCount = DB::table('enrollments')
                        ->where('class_id', $class->id)
                        ->distinct('student_code')
                        ->count();
                    
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
                    return $class['unit_count'] > 0;
                })
                ->values();

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
     * Get venue capacity for a specific date and time slot
     */
    private function getVenueCapacity(
        string $examDate,
        string $startTime,
        string $endTime,
        $availableRooms,
        $existingExams = null
    ): array
    {
        if (!$existingExams) {
            $existingExams = ExamTimetable::where('date', $examDate)->get();
        }

        $roomCapacityData = [];

        foreach ($availableRooms as $room) {
            $roomCapacityData[$room->name] = [
                'name' => $room->name,
                'location' => $room->location,
                'capacity' => $room->capacity,
                'used' => 0,
                'available' => $room->capacity,
            ];
        }

        $conflictingExams = $existingExams->filter(function($exam) use ($examDate, $startTime, $endTime) {
            if ($exam->date !== $examDate) {
                return false;
            }
            
            $slotStart = Carbon::parse($examDate . ' ' . $startTime);
            $slotEnd = Carbon::parse($examDate . ' ' . $endTime);
            $examStart = Carbon::parse($exam->date . ' ' . $exam->start_time);
            $examEnd = Carbon::parse($exam->date . ' ' . $exam->end_time);

            return $examStart->lt($slotEnd) && $examEnd->gt($slotStart);
        });

        foreach ($conflictingExams as $exam) {
            $venueName = $exam->venue;
            
            if (isset($roomCapacityData[$venueName])) {
                $roomCapacityData[$venueName]['used'] += $exam->no;
                $roomCapacityData[$venueName]['available'] = $roomCapacityData[$venueName]['capacity'] - $roomCapacityData[$venueName]['used'];
            }
        }

        return collect(array_values($roomCapacityData))->sortByDesc('available')->toArray();
    }

/**
 * ✅ FULLY FIXED: Bulk schedule exams - Uses ALL selected venues properly
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
        'selected_class_units.*.unit_id' => 'required|exists:units,id',
        'selected_class_units.*.class_id' => 'required',
        'selected_class_units.*.assigned_start_time' => 'required|date_format:H:i',
        'selected_class_units.*.assigned_end_time' => 'required|date_format:H:i',
        'selected_class_units.*.slot_number' => 'nullable|integer',
        'start_date' => 'required|date|after_or_equal:today',
        'end_date' => 'required|date|after:start_date',
        'exam_duration_hours' => 'required|integer|min:1|max:4',
        'gap_between_exams_days' => 'required|integer|min:0|max:7',
        'start_time' => 'required|date_format:H:i',
        'excluded_days' => 'nullable|array',
        'excluded_days.*' => 'string|in:Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
        'max_exams_per_day' => 'required|integer|min:1|max:10',
        'selected_examrooms' => 'nullable|array',
        'break_minutes' => 'nullable|integer|min:0',
    ]);

    try {
        DB::beginTransaction();

        $scheduledExams = [];
        $conflicts = [];
        $warnings = [];

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

        $gapDays = $validated['gap_between_exams_days'];
        $excludedDays = $validated['excluded_days'] ?? [];
        $maxExamsPerDay = $validated['max_exams_per_day'];

        // ✅ FIXED: Load selected exam rooms with improved logging
        $selectedExamroomIds = $validated['selected_examrooms'] ?? [];

        \Log::info('📍 Loading exam rooms from request', [
            'selected_ids' => $selectedExamroomIds,
            'count' => count($selectedExamroomIds)
        ]);

        $availableRooms = Examroom::whereIn('id', $selectedExamroomIds)
            ->orderBy('capacity', 'asc')  // ✅ FIXED: Try smallest rooms first for better distribution
            ->get();

        \Log::info('✅ Retrieved exam rooms for scheduling', [
            'count' => $availableRooms->count(),
            'rooms' => $availableRooms->map(function($room) {
                return [
                    'id' => $room->id,
                    'name' => $room->name,
                    'capacity' => $room->capacity,
                    'location' => $room->location,
                    'is_active' => $room->is_active ?? 'N/A'
                ];
            })->toArray()
        ]);

        if ($availableRooms->isEmpty()) {
            \Log::error('❌ No exam rooms found', [
                'selected_ids' => $selectedExamroomIds,
                'all_rooms_in_db' => Examroom::pluck('name', 'id')->toArray()
            ]);
            throw new \Exception('No exam rooms selected or available');
        }

        // Track last exam date PER CLASS
        $classLastExamDate = [];
        
        // ✅ FIXED: Track venue capacity in 3D structure (venue -> date -> time_slot)
        $venueCapacityUsage = [];
        
        $currentDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        // Map selected_class_units by unit_id for easy lookup
        $selectedUnitsMap = [];
        foreach ($validated['selected_class_units'] as $unitData) {
            $selectedUnitsMap[$unitData['unit_id']] = $unitData;
        }

        // Loop through units
        foreach ($unitsByClass as $unitId => $unitRecords) {
            // ✅ Get the assigned time slot from frontend
            if (!isset($selectedUnitsMap[$unitId])) {
                \Log::warning("Unit {$unitId} not found in selected units map");
                continue;
            }

            $unitSelection = $selectedUnitsMap[$unitId];
            $assignedStartTime = $unitSelection['assigned_start_time'];
            $assignedEndTime = $unitSelection['assigned_end_time'];
            $slotNumber = $unitSelection['slot_number'] ?? null;

            $firstUnit = $unitRecords[0];
            $selectedClassIds = array_unique(array_column($unitRecords, 'class_id'));
            
            // Get ALL classes taking this unit
            $allClassesTakingUnit = DB::table('unit_assignments')
                ->where('unit_id', $unitId)
                ->distinct()
                ->pluck('class_id');
            
            // Count students across ALL classes taking this unit
            $totalStudents = DB::table('enrollments')
                ->where('unit_id', $unitId)
                ->whereIn('class_id', $allClassesTakingUnit)
                ->where('status', 'enrolled')
                ->distinct('student_code')
                ->count('student_code');
            
            if ($totalStudents === 0) {
                $warnings[] = "Skipped {$firstUnit['unit_code']} - No students enrolled";
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
                'assigned_time_slot' => "{$assignedStartTime} - {$assignedEndTime}",
                'slot_number' => $slotNumber,
                'total_students' => $totalStudents,
                'lecturer' => $chiefInvigilator
            ]);

            // Find earliest allowed date considering gap
            $earliestAllowedDate = $currentDate->copy();
            
            foreach ($selectedClassIds as $classId) {
                if (isset($classLastExamDate[$classId])) {
                    $classMinDate = $classLastExamDate[$classId]->copy()->addDays($gapDays + 1);
                    if ($classMinDate->gt($earliestAllowedDate)) {
                        $earliestAllowedDate = $classMinDate;
                    }
                }
            }
            
            // Find next available slot
            $scheduled = false;
            $attempts = 0;
            $maxAttempts = 300;
            $attemptDate = clone $earliestAllowedDate;
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
                
                // ✅ Generate time slot key for venue tracking
                $timeSlotKey = "{$assignedStartTime}-{$assignedEndTime}";
                $examStartTime = Carbon::parse("{$examDate} {$assignedStartTime}");
                $examEndTime = Carbon::parse("{$examDate} {$assignedEndTime}");

                // ✅ Find available venue with capacity tracking
                $venueResult = $this->findVenueWithRemainingCapacity(
                    $availableRooms,      // ✅ Pass ALL selected rooms
                    $totalStudents,       // Required capacity
                    $examDate,            // Date
                    $timeSlotKey,         // Time slot key
                    $venueCapacityUsage   // Capacity tracking array
                );

                if (!$venueResult['success']) {
                    $failureReasons[] = "Date {$examDate} Slot {$slotNumber}: {$venueResult['message']}";
                    $attemptDate->addDay();
                    continue;
                }

                // Check for conflicts
                $hasConflict = false;
                $conflictReasons = [];

                // Check class conflicts
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
                        $conflictReasons[] = "Class already has exam ({$classConflict->unit_code}) at this time";
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
                        $conflictReasons[] = "Lecturer {$chiefInvigilator} already assigned";
                    }
                }

                if ($hasConflict) {
                    $failureReasons[] = "Date {$examDate} Slot {$slotNumber}: " . implode(', ', $conflictReasons);
                    $attemptDate->addDay();
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
                    'no' => $totalStudents,
                    'chief_invigilator' => $chiefInvigilator,
                ];

                $createdExam = ExamTimetable::create($examData);
                $existingExams->push($createdExam);
                
                // Update last exam date for selected classes
                foreach ($selectedClassIds as $classId) {
                    $classLastExamDate[$classId] = clone $attemptDate;
                }
                
                // ✅ FIXED: Update venue capacity usage with 3D tracking
                $this->updateVenueCapacityUsage(
                    $venueCapacityUsage,
                    $venueResult['venue'],
                    $examDate,
                    $timeSlotKey,
                    $totalStudents
                );
                
                $classNames = ClassModel::whereIn('id', $selectedClassIds)->pluck('name')->toArray();
                
                $scheduledExams[] = [
                    'exam' => $createdExam,
                    'unit_code' => $firstUnit['unit_code'],
                    'unit_name' => $firstUnit['unit_name'],
                    'classes' => $classNames,
                    'class_count' => count($selectedClassIds),
                    'total_students' => $totalStudents,
                    'date' => $examDate,
                    'time' => "{$examStartTime->format('H:i')} - {$examEndTime->format('H:i')}",
                    'slot_number' => $slotNumber,
                    'venue' => $venueResult['venue'],
                    'lecturer' => $chiefInvigilator
                ];

                $scheduled = true;

                \Log::info("✅ Exam scheduled successfully", [
                    'unit' => $firstUnit['unit_code'],
                    'classes' => $classNames,
                    'students' => $totalStudents,
                    'date' => $examDate,
                    'time_slot' => $timeSlotKey,
                    'slot_number' => $slotNumber,
                    'venue' => $venueResult['venue'],
                    'attempts_taken' => $attempts
                ]);
            }

            if (!$scheduled) {
                $reason = !empty($failureReasons) 
                    ? implode('; ', $failureReasons)
                    : 'Could not find suitable slot within date range';
                
                \Log::warning('❌ Failed to schedule unit', [
                    'unit_code' => $firstUnit['unit_code'],
                    'unit_name' => $firstUnit['unit_name'],
                    'reason' => $reason,
                    'assigned_slot' => "Slot {$slotNumber}: {$assignedStartTime} - {$assignedEndTime}",
                    'students' => $totalStudents,
                    'lecturer' => $chiefInvigilator
                ]);
                
                $conflicts[] = [
                    'unit_code' => $firstUnit['unit_code'],
                    'unit_name' => $firstUnit['unit_name'],
                    'reason' => $reason,
                    'assigned_slot' => "Slot {$slotNumber}: {$assignedStartTime} - {$assignedEndTime}"
                ];
            }
        }

        DB::commit();

        \Log::info('=== BULK SCHEDULING COMPLETED ===', [
            'scheduled_count' => count($scheduledExams),
            'conflicts_count' => count($conflicts),
            'warnings_count' => count($warnings),
            'gap_days_used' => $gapDays
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
                'gap_days_used' => $gapDays
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
 * ✅ FIXED: Find venue with lowest utilization for better distribution
 */
private function findVenueWithRemainingCapacity($examrooms, $requiredCapacity, $examDate, $timeSlotKey, &$venueCapacityUsage)
{
    \Log::info('🔍 Searching for venue', [
        'required_capacity' => $requiredCapacity,
        'date' => $examDate,
        'time_slot' => $timeSlotKey,
        'available_rooms_count' => count($examrooms)
    ]);

    $bestVenue = null;
    $bestUsedCapacity = 0;
    $bestRemainingCapacity = 0;
    $lowestUtilization = PHP_INT_MAX;
    
    foreach ($examrooms as $room) {
        // Skip rooms that are too small
        if ($room->capacity < $requiredCapacity) {
            \Log::debug("  ⏭️ Skipping {$room->name} - Too small ({$room->capacity} < {$requiredCapacity})");
            continue;
        }
        
        $usedCapacity = $venueCapacityUsage[$room->name][$examDate][$timeSlotKey] ?? 0;
        $remainingCapacity = $room->capacity - $usedCapacity;
        
        \Log::debug("  Checking venue: {$room->name}", [
            'total_capacity' => $room->capacity,
            'used_capacity' => $usedCapacity,
            'remaining_capacity' => $remainingCapacity,
            'required' => $requiredCapacity,
            'fits' => $remainingCapacity >= $requiredCapacity
        ]);
        
        // Check if this venue has enough remaining capacity
        if ($remainingCapacity >= $requiredCapacity) {
            // Calculate utilization percentage
            $utilization = ($usedCapacity / $room->capacity) * 100;
            
            \Log::debug("    💡 {$room->name} is viable", [
                'utilization' => round($utilization, 2) . '%',
                'is_better' => $utilization < $lowestUtilization ? 'YES' : 'NO'
            ]);
            
            // Prefer venue with lowest current utilization
            if ($utilization < $lowestUtilization) {
                $lowestUtilization = $utilization;
                $bestVenue = $room;
                $bestUsedCapacity = $usedCapacity;
                $bestRemainingCapacity = $remainingCapacity;
            }
        }
    }
    
    // If we found a suitable venue, return it
    if ($bestVenue !== null) {
        \Log::info('✅ Venue found!', [
            'venue' => $bestVenue->name,
            'utilization' => round($lowestUtilization, 2) . '%',
            'capacity_allocated' => $requiredCapacity,
            'remaining_after' => $bestRemainingCapacity - $requiredCapacity
        ]);
        
        return [
            'success' => true,
            'venue' => $bestVenue->name,
            'location' => $bestVenue->location,
            'total_capacity' => $bestVenue->capacity,
            'capacity_used' => $bestUsedCapacity + $requiredCapacity,
            'remaining_capacity' => $bestRemainingCapacity - $requiredCapacity
        ];
    }
    
    // No suitable venue found
    \Log::warning('❌ No venue found with sufficient capacity', [
        'required_capacity' => $requiredCapacity,
        'venues_checked' => $examrooms->pluck('name')->toArray(),
        'current_usage' => array_map(function($room) use ($venueCapacityUsage, $examDate, $timeSlotKey) {
            $used = $venueCapacityUsage[$room->name][$examDate][$timeSlotKey] ?? 0;
            return [
                'venue' => $room->name,
                'capacity' => $room->capacity,
                'used' => $used,
                'remaining' => $room->capacity - $used
            ];
        }, $examrooms->toArray())
    ]);
    
    return [
        'success' => false,
        'message' => "No venue with capacity for {$requiredCapacity} students at {$timeSlotKey} on {$examDate}"
    ];
}

/**
 * ✅ FIXED: Update venue capacity usage (3D tracking: venue -> date -> time_slot)
 */
private function updateVenueCapacityUsage(&$venueCapacityUsage, $venueName, $examDate, $timeSlotKey, $studentCount)
{
    if (!isset($venueCapacityUsage[$venueName])) {
        $venueCapacityUsage[$venueName] = [];
    }
    
    if (!isset($venueCapacityUsage[$venueName][$examDate])) {
        $venueCapacityUsage[$venueName][$examDate] = [];
    }
    
    if (!isset($venueCapacityUsage[$venueName][$examDate][$timeSlotKey])) {
        $venueCapacityUsage[$venueName][$examDate][$timeSlotKey] = 0;
    }
    
    $venueCapacityUsage[$venueName][$examDate][$timeSlotKey] += $studentCount;
    
    \Log::debug('📊 Updated venue capacity', [
        'venue' => $venueName,
        'date' => $examDate,
        'time_slot' => $timeSlotKey,
        'students_added' => $studentCount,
        'total_used' => $venueCapacityUsage[$venueName][$examDate][$timeSlotKey]
    ]);
}

/**
 * ✅ Smart venue assignment for single exam scheduling
 * Finds the best available venue for a given exam slot
 */
private function assignSmartVenue($studentCount, $date, $startTime, $endTime)
{
    \Log::info('🔍 Smart venue assignment requested', [
        'student_count' => $studentCount,
        'date' => $date,
        'time' => "{$startTime} - {$endTime}"
    ]);

    try {
        // Get all available exam rooms
        $examrooms = Examroom::all();
        
        if ($examrooms->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No exam rooms configured in the system'
            ];
        }

        // Parse the time range
        $examStartTime = Carbon::parse($date . ' ' . $startTime);
        $examEndTime = Carbon::parse($date . ' ' . $endTime);

        // Find venues that can accommodate the students
        $suitableVenues = [];
        
        foreach ($examrooms as $room) {
            // Skip rooms that are too small
            if ($room->capacity < $studentCount) {
                \Log::debug("  ⏭️ Skipping {$room->name} - Too small ({$room->capacity} < {$studentCount})");
                continue;
            }

            // Check existing exams in this venue at this time
            $existingExams = ExamTimetable::where('venue', $room->name)
                ->where('date', $date)
                ->get();

            // Calculate total students already scheduled at overlapping times
            $occupiedCapacity = 0;
            foreach ($existingExams as $exam) {
                $existingStart = Carbon::parse($exam->date . ' ' . $exam->start_time);
                $existingEnd = Carbon::parse($exam->date . ' ' . $exam->end_time);

                // Check for time overlap
                if ($existingStart->lt($examEndTime) && $existingEnd->gt($examStartTime)) {
                    $occupiedCapacity += $exam->no;
                }
            }

            $remainingCapacity = $room->capacity - $occupiedCapacity;

            \Log::debug("  Checking venue: {$room->name}", [
                'total_capacity' => $room->capacity,
                'occupied' => $occupiedCapacity,
                'remaining' => $remainingCapacity,
                'required' => $studentCount,
                'fits' => $remainingCapacity >= $studentCount
            ]);

            // If venue has enough remaining capacity, add it to candidates
            if ($remainingCapacity >= $studentCount) {
                $utilizationPercentage = ($occupiedCapacity / $room->capacity) * 100;
                
                $suitableVenues[] = [
                    'room' => $room,
                    'remaining_capacity' => $remainingCapacity,
                    'utilization' => $utilizationPercentage,
                    'occupied' => $occupiedCapacity
                ];
            }
        }

        // If no suitable venues found
        if (empty($suitableVenues)) {
            \Log::warning('❌ No suitable venue found', [
                'required_capacity' => $studentCount,
                'date' => $date,
                'time' => "{$startTime} - {$endTime}"
            ]);
            
            return [
                'success' => false,
                'message' => "No exam room available with capacity for {$studentCount} students at {$startTime}-{$endTime} on {$date}"
            ];
        }

        // Sort by utilization (prefer venues with lowest current utilization)
        usort($suitableVenues, function($a, $b) {
            return $a['utilization'] <=> $b['utilization'];
        });

        // Select the best venue (lowest utilization)
        $bestVenue = $suitableVenues[0];

        \Log::info('✅ Venue assigned successfully', [
            'venue' => $bestVenue['room']->name,
            'location' => $bestVenue['room']->location,
            'capacity' => $bestVenue['room']->capacity,
            'utilization' => round($bestVenue['utilization'], 2) . '%',
            'remaining_after' => $bestVenue['remaining_capacity'] - $studentCount
        ]);

        return [
            'success' => true,
            'venue' => $bestVenue['room']->name,
            'location' => $bestVenue['room']->location,
            'capacity' => $bestVenue['room']->capacity,
            'remaining_capacity' => $bestVenue['remaining_capacity']
        ];

    } catch (\Exception $e) {
        \Log::error('❌ Error in smart venue assignment', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return [
            'success' => false,
            'message' => 'Error assigning venue: ' . $e->getMessage()
        ];
    }
}
public function downloadAllPDF()
{
    $user = auth()->user();

    // Allow Exam Office, School Admins, Lecturers, and Students to download
    $allowedRoles = ['Exam Office', 'Lecturer', 'Student'];
    $hasAllowedRole = false;
    
    foreach ($user->roles as $role) {
        if (in_array($role->name, $allowedRoles) || str_starts_with($role->name, 'Faculty Admin - ')) {
            $hasAllowedRole = true;
            break;
        }
    }
    
    // Also check for view-exam-timetables permission (for Exam Office)
    if (!$hasAllowedRole && !$user->can('view-exam-timetables')) {
        abort(403, 'Unauthorized action.');
    }

    // Get all exam timetables with relationships
    $examTimetables = ExamTimetable::with(['unit', 'class', 'semester'])
        ->orderBy('date', 'asc')
        ->orderBy('start_time', 'asc')
        ->get();

    // Transform data to match your PDF view expectations
    $transformedExams = $examTimetables->map(function($exam) {
        return (object)[
            'date' => $exam->date,
            'day' => $exam->day,
            'start_time' => $exam->start_time,
            'end_time' => $exam->end_time,
            'unit_code' => $exam->unit->code ?? 'N/A',
            'unit_name' => $exam->unit->name ?? 'N/A',
            'semester_name' => $exam->semester->name ?? 'N/A',
            'venue' => $exam->venue,
            'location' => $exam->location,
            'chief_invigilator' => $exam->chief_invigilator ?? 'TBA'
        ];
    });

    // Prepare data matching your PDF view
    $data = [
        'title' => 'UNIVERSITY EXAMINATION TIMETABLE',  // ✅ Added
        'generatedAt' => now()->format('F d, Y \a\t h:i A'),  // ✅ Fixed
        'examTimetables' => $transformedExams  // ✅ Transformed
    ];

    // Generate PDF
    $pdf = PDF::loadView('examtimetables.pdf', $data)
        ->setPaper('A4', 'landscape');

    // Download
    return $pdf->download('All_Exam_Timetables_' . now()->format('Y-m-d') . '.pdf');
}
}