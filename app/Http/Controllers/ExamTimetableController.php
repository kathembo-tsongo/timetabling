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

    public function getUnitsForBulkScheduling(Request $request, Program $program, $schoolCode)
    {
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        try {
            $semesterId = $request->get('semester_id');

            Log::info('🔍 Fetching units for bulk scheduling', [
                'program_id' => $program->id,
                'semester_id' => $semesterId
            ]);

            // ✅ Try BOTH possible FK names (class_id and group_id)
            // First, let's check which column exists
            $enrollmentColumns = DB::select("SHOW COLUMNS FROM enrollments");
            $hasGroupId = collect($enrollmentColumns)->contains('Field', 'group_id');
            $hasClassId = collect($enrollmentColumns)->contains('Field', 'class_id');
            
            $classFKColumn = $hasGroupId ? 'group_id' : 'class_id';
            
            Log::info('📊 Enrollments table structure', [
                'has_group_id' => $hasGroupId,
                'has_class_id' => $hasClassId,
                'using_column' => $classFKColumn
            ]);

            // ✅ Query with correct FK column
            $units = DB::table('enrollments')
                ->join('classes', "enrollments.{$classFKColumn}", '=', 'classes.id')
                ->join('units', 'enrollments.unit_id', '=', 'units.id')
                ->where('units.program_id', $program->id)
                ->where('enrollments.semester_id', $semesterId)
                ->where('enrollments.status', 'enrolled')
                ->select(
                    'units.id as unit_id',
                    'units.code as unit_code',
                    'units.name as unit_name',
                    'enrollments.lecturer_code',
                    "enrollments.{$classFKColumn} as class_id",
                    'classes.name as class_name',
                    'classes.section as class_section',
                    DB::raw('COUNT(DISTINCT enrollments.student_code) as student_count')
                )
                ->groupBy(
                    'units.id',
                    'units.code',
                    'units.name',
                    'enrollments.lecturer_code',
                    "enrollments.{$classFKColumn}",
                    'classes.name',
                    'classes.section'
                )
                ->having('student_count', '>', 0)
                ->orderBy('units.code')
                ->orderBy('classes.name')
                ->orderBy('classes.section')
                ->get();

            Log::info('✅ Found units for scheduling', [
                'count' => $units->count(),
                'sample' => $units->take(2)->toArray()
            ]);

            return response()->json([
                'success' => true,
                'units' => $units->map(function($unit) {
                    $lecturerName = $this->getLecturerName($unit->lecturer_code);
                    
                    return [
                        'unit_id' => $unit->unit_id,
                        'class_id' => $unit->class_id,
                        'unit_code' => $unit->unit_code,
                        'unit_name' => $unit->unit_name,
                        'class_name' => $unit->class_name,
                        'class_section' => $unit->class_section ?? 'N/A',
                        'student_count' => (int)$unit->student_count,
                        'lecturer_code' => $unit->lecturer_code ?? 'N/A',
                        'lecturer_name' => $lecturerName,
                        'display_name' => sprintf(
                            '%s - %s | %s (Sec %s) | %s [%d students]',
                            $unit->unit_code,
                            $unit->unit_name,
                            $unit->class_name,
                            $unit->class_section ?? 'N/A',
                            $lecturerName,
                            $unit->student_count
                        )
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Failed to fetch units for bulk scheduling', [
                'program_id' => $program->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch units: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ FIXED: Bulk store - properly populate group_name
     */
    public function bulkStore(Request $request, Program $program, $schoolCode)
    {
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        $validated = $request->validate([
            'selections' => 'required|array|min:1',
            'selections.*.unit_id' => 'required|exists:units,id',
            'selections.*.class_id' => 'required|exists:classes,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'duration_hours' => 'required|integer|min:1|max:8',
            'venue' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'chief_invigilator' => 'required|string|max:255',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        // ✅ Detect which FK column to use
        $enrollmentColumns = DB::select("SHOW COLUMNS FROM enrollments");
        $hasGroupId = collect($enrollmentColumns)->contains('Field', 'group_id');
        $classFKColumn = $hasGroupId ? 'group_id' : 'class_id';

        DB::beginTransaction();
        
        try {
            $created = 0;
            $errors = [];
            $endTime = date('H:i', strtotime($validated['start_time']) + ($validated['duration_hours'] * 3600));

            Log::info('📝 Starting bulk exam creation', [
                'selections_count' => count($validated['selections']),
                'semester' => $validated['semester_id'],
                'using_fk_column' => $classFKColumn
            ]);

            foreach ($validated['selections'] as $selection) {
                $unitId = $selection['unit_id'];
                $classId = $selection['class_id'];

                // ✅ Get class with section
                $class = Classes::findOrFail($classId);
                
                Log::info('Processing selection', [
                    'unit_id' => $unitId,
                    'class_id' => $classId,
                    'class_name' => $class->name,
                    'class_section' => $class->section
                ]);
                
                // ✅ Count students using correct FK
                $enrolledCount = DB::table('enrollments')
                    ->where('unit_id', $unitId)
                    ->where($classFKColumn, $classId)
                    ->where('semester_id', $validated['semester_id'])
                    ->where('status', 'enrolled')
                    ->distinct('student_code')
                    ->count('student_code');

                if ($enrolledCount === 0) {
                    $unit = Unit::find($unitId);
                    $errors[] = "No enrolled students for {$unit->code} in {$class->name}";
                    continue;
                }

                // Get lecturer
                $lecturerCode = DB::table('enrollments')
                    ->where('unit_id', $unitId)
                    ->where($classFKColumn, $classId)
                    ->where('semester_id', $validated['semester_id'])
                    ->value('lecturer_code');

                $chiefInvigilator = $validated['chief_invigilator'];
                if ($lecturerCode) {
                    $lecturer = DB::table('users')->where('code', $lecturerCode)->first();
                    if ($lecturer) {
                        $chiefInvigilator = trim("{$lecturer->first_name} {$lecturer->last_name}");
                    }
                }

                // ✅ CRITICAL: Set group_name to class section
                $groupName = $class->section ?? null;
                
                Log::info('Creating exam with group_name', [
                    'class_id' => $classId,
                    'class_name' => $class->name,
                    'class_section' => $class->section,
                    'group_name' => $groupName,
                    'student_count' => $enrolledCount
                ]);

                $examData = [
                    'unit_id' => $unitId,
                    'class_id' => $classId,
                    'semester_id' => $validated['semester_id'],
                    'program_id' => $program->id,
                    'school_id' => $program->school_id,
                    'group_name' => $groupName, // ✅ This should now be populated!
                    'date' => $validated['date'],
                    'day' => date('l', strtotime($validated['date'])),
                    'start_time' => $validated['start_time'],
                    'end_time' => $endTime,
                    'venue' => $validated['venue'],
                    'location' => $validated['location'] ?? 'Main Campus',
                    'no' => $enrolledCount,
                    'chief_invigilator' => $chiefInvigilator,
                ];

                $exam = ExamTimetable::create($examData);
                $created++;

                Log::info('✅ Exam created', [
                    'exam_id' => $exam->id,
                    'group_name' => $exam->group_name,
                    'student_count' => $exam->no
                ]);
            }

            DB::commit();

            if ($created > 0) {
                $message = "Successfully created {$created} exam schedule(s).";
                if (!empty($errors)) {
                    $message .= " " . count($errors) . " failed.";
                }
                
                return redirect()
                    ->route('programs.exam-timetables.index', [$program->id, $schoolCode])
                    ->with('success', $message);
            } else {
                DB::rollBack();
                return back()
                    ->withErrors(['error' => 'No exams were created. ' . implode(' ', $errors)])
                    ->withInput();
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Bulk exam creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()
                ->withErrors(['error' => 'Failed to create exam timetables: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * ✅ FIXED: Store single exam with group_name
     */
    public function store(Request $request, Program $program, $schoolCode)
    {
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'class_id' => 'required|exists:classes,id',
            'semester_id' => 'required|exists:semesters,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'venue' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'no' => 'required|integer|min:1',
            'chief_invigilator' => 'required|string|max:255',
        ]);

        try {
            // ✅ Get class with section
            $class = Classes::findOrFail($validated['class_id']);
            
            Log::info('Creating single exam', [
                'class_id' => $validated['class_id'],
                'class_name' => $class->name,
                'class_section' => $class->section
            ]);
            
            // ✅ CRITICAL: Set group_name
            $groupName = $class->section ?? null;
            
            $examTimetable = ExamTimetable::create([
                'unit_id' => $validated['unit_id'],
                'class_id' => $validated['class_id'],
                'semester_id' => $validated['semester_id'],
                'program_id' => $program->id,
                'school_id' => $program->school_id,
                'group_name' => $groupName, // ✅ Set section here!
                'date' => $validated['date'],
                'day' => date('l', strtotime($validated['date'])),
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'venue' => $validated['venue'],
                'location' => $validated['location'],
                'no' => $validated['no'],
                'chief_invigilator' => $validated['chief_invigilator'],
            ]);

            Log::info('✅ Exam created successfully', [
                'exam_id' => $examTimetable->id,
                'group_name' => $examTimetable->group_name
            ]);

            return redirect()
                ->route('programs.exam-timetables.index', [$program->id, $schoolCode])
                ->with('success', 'Exam timetable created successfully.');

        } catch (\Exception $e) {
            Log::error('❌ Failed to create exam timetable', [
                'error' => $e->getMessage(),
                'data' => $validated
            ]);

            return back()
                ->withErrors(['error' => 'Failed to create exam timetable: ' . $e->getMessage()])
                ->withInput();
        }
    }

    private function getLecturerName($lecturerCode): string
    {
        if (!$lecturerCode) return 'Not Assigned';
        
        $lecturer = DB::table('users')
            ->where('code', $lecturerCode)
            ->first();
            
        return $lecturer 
            ? trim(($lecturer->first_name ?? '') . ' ' . ($lecturer->last_name ?? ''))
            : 'Not Assigned';
    }




     


    
    

    /**
     * Check for scheduling conflicts
     */
    private function checkConflicts(array $examData, ?int $excludeId = null, int $programId): array
    {
        $conflicts = [];
        $startTime = $examData['start_time'];
        $endTime = $examData['end_time'];
        $date = $examData['date'];

        $query = ExamTimetable::where('date', $date)
            ->where('program_id', $programId)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                  ->orWhereBetween('end_time', [$startTime, $endTime])
                  ->orWhere(function ($q2) use ($startTime, $endTime) {
                      $q2->where('start_time', '<=', $startTime)
                         ->where('end_time', '>=', $endTime);
                  });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        // Check venue conflict
        if ((clone $query)->where('venue', $examData['venue'])->exists()) {
            $conflicts[] = [
                'type' => 'venue',
                'severity' => 'error',
                'message' => 'Venue already booked for this time slot'
            ];
        }

        // Check class + section conflict
        if ((clone $query)
            ->where('class_id', $examData['class_id'])
            ->where('group_name', $examData['group_name'] ?? null)
            ->exists()) {
            $conflicts[] = [
                'type' => 'class',
                'severity' => 'error',
                'message' => 'Class section already has an exam scheduled'
            ];
        }

        // Check unit + class + section conflict
        if ((clone $query)
            ->where('unit_id', $examData['unit_id'])
            ->where('class_id', $examData['class_id'])
            ->where('group_name', $examData['group_name'] ?? null)
            ->exists()) {
            $conflicts[] = [
                'type' => 'unit',
                'severity' => 'error',
                'message' => 'This unit for this class section already has an exam'
            ];
        }

        return $conflicts;
    }

    /**
     * ✅ CORRECTED: Index with proper joins using group_id
     */
    public function index(Request $request, Program $program, $schoolCode)
    {
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        $query = ExamTimetable::query()
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
            ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
            ->where('exam_timetables.program_id', $program->id)
            ->select(
                'exam_timetables.*',
                'units.name as unit_name',
                'units.code as unit_code',
                'classes.name as class_name',
                'classes.section as class_section',
                'exam_timetables.group_name',
                DB::raw('COALESCE(REPLACE(classes.name, " ", "-"), CONCAT("CLASS-", classes.id)) as class_code'),
                'semesters.name as semester_name'
            );

        // Get lecturer info from enrollments using group_id
        $examTimetables = $query->orderBy('exam_timetables.date')
            ->orderBy('exam_timetables.start_time')
            ->orderBy('classes.section')
            ->paginate($request->get('per_page', 15));

        // ✅ Add lecturer names from enrollments
        foreach ($examTimetables as $exam) {
            $lecturerCode = DB::table('enrollments')
                ->where('unit_id', $exam->unit_id)
                ->where('group_id', $exam->class_id) // ✅ group_id = class_id
                ->where('semester_id', $exam->semester_id)
                ->value('lecturer_code');
                
            if ($lecturerCode) {
                $exam->lecturer_code = $lecturerCode;
                $exam->lecturer_name = $this->getLecturerName($lecturerCode);
            }
        }

        return inertia('ExamTimetable/Index', [
            'examTimetables' => $examTimetables,
            'program' => $program,
            'semesters' => Semester::all(),
            'schoolCode' => $schoolCode,
            'filters' => $request->only(['search', 'semester_id', 'per_page']),
            'can' => [
                'create' => auth()->user()->can('create-exam-timetables'),
                'edit' => auth()->user()->can('edit-exam-timetables'),
                'delete' => auth()->user()->can('delete-exam-timetables'),
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
            'classes.section as class_section', // ✅ ADD THIS LINE
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
        'venue' => 'nullable|string|max:255',
        'location' => 'nullable|string|max:255',
    ]);

    try {
        // ✅ CORRECT: Use group_id to identify individual sections
        $sectionsWithStudents = DB::table('enrollments')
            ->join('classes', 'enrollments.group_id', '=', 'classes.id') // ✅ KEY FIX: group_id!
            ->where('enrollments.unit_id', $validatedData['unit_id'])
            ->where('enrollments.semester_id', $validatedData['semester_id'])
            ->where('classes.program_id', $program->id)
            ->where('enrollments.status', 'enrolled')
            ->select(
                'classes.id as class_id',
                'classes.name as class_name',
                'classes.section as class_section',
                'classes.year_level',
                DB::raw('COUNT(DISTINCT enrollments.student_code) as student_count')
            )
            ->groupBy('classes.id', 'classes.name', 'classes.section', 'classes.year_level')
            ->having('student_count', '>', 0)
            ->orderBy('classes.year_level')
            ->orderBy('classes.section')
            ->get();

        if ($sectionsWithStudents->isEmpty()) {
            return redirect()->back()
                ->withErrors(['error' => 'No students enrolled in this unit for any class section'])
                ->withInput();
        }

        \Log::info('📊 Individual class sections for this unit:', [
            'unit_id' => $validatedData['unit_id'],
            'semester_id' => $validatedData['semester_id'],
            'sections_found' => $sectionsWithStudents->count(),
            'breakdown' => $sectionsWithStudents->map(function($s) {
                return [
                    'class_id' => $s->class_id,
                    'name' => $s->class_name,
                    'section' => $s->class_section,
                    'students' => $s->student_count
                ];
            })->toArray()
        ]);

        // ✅ Schedule EACH section separately
        $scheduledExams = [];
        $failedSections = [];

        foreach ($sectionsWithStudents as $section) {
            $fullClassName = trim($section->class_name . ' ' . ($section->class_section ?? ''));
            
            \Log::info("📝 Scheduling section: {$fullClassName}", [
                'class_id' => $section->class_id,
                'students' => $section->student_count
            ]);

            // Find venue for THIS section only
            $venueResult = $this->assignSmartVenue(
                $section->student_count,
                $validatedData['date'],
                $validatedData['start_time'],
                $validatedData['end_time']
            );

            if (!$venueResult['success']) {
                $failedSections[] = [
                    'class' => $fullClassName,
                    'students' => $section->student_count,
                    'reason' => $venueResult['message']
                ];
                continue;
            }

            // Create exam for this section
            $examData = [
                'unit_id' => $validatedData['unit_id'],
                'semester_id' => $validatedData['semester_id'],
                'class_id' => $section->class_id, // Individual section ID
                'program_id' => $program->id,
                'school_id' => $program->school_id,
                'date' => $validatedData['date'],
                'day' => $validatedData['day'],
                'start_time' => $validatedData['start_time'],
                'end_time' => $validatedData['end_time'],
                'venue' => $venueResult['venue'],
                'location' => $venueResult['location'] ?? 'Main Campus',
                'no' => $section->student_count, // Individual count
                'chief_invigilator' => $validatedData['chief_invigilator'],
            ];

            $createdExam = ExamTimetable::create($examData);
            
            $scheduledExams[] = [
                'exam_id' => $createdExam->id,
                'class' => $fullClassName,
                'students' => $section->student_count,
                'venue' => $venueResult['venue'],
            ];

            \Log::info('✅ Exam scheduled', [
                'class' => $fullClassName,
                'students' => $section->student_count,
                'venue' => $venueResult['venue']
            ]);
        }

        if (!empty($scheduledExams)) {
            $message = "✅ Scheduled " . count($scheduledExams) . " section(s):\n\n";
            
            foreach ($scheduledExams as $exam) {
                $message .= "• {$exam['class']}: {$exam['students']} students → {$exam['venue']}\n";
            }

            if (!empty($failedSections)) {
                $message .= "\n⚠️ Failed:\n";
                foreach ($failedSections as $failed) {
                    $message .= "• {$failed['class']}: {$failed['reason']}\n";
                }
            }

            return redirect()
                ->route('schools.' . strtolower($schoolCode) . '.programs.exam-timetables.index', $program)
                ->with('success', $message);
        } else {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to schedule any sections'])
                ->withInput();
        }

    } catch (\Exception $e) {
        \Log::error('Error:', ['message' => $e->getMessage()]);
        return redirect()->back()
            ->withErrors(['error' => $e->getMessage()])
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
            // ✅ Get student count for THIS specific class
            $studentCount = DB::table('enrollments')
                ->where('unit_id', $validatedData['unit_id'])
                ->where('semester_id', $validatedData['semester_id'])
                ->where('class_id', $validatedData['class_id'])
                ->where('status', 'enrolled')
                ->distinct('student_code')
                ->count('student_code');

            if ($studentCount === 0) {
                return redirect()->back()
                    ->withErrors(['error' => 'No students enrolled for this class in this unit'])
                    ->withInput();
            }

            // Override user input with actual enrollment count
            $validatedData['no'] = $studentCount;

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
                    return redirect()->back()
                        ->withErrors(['venue' => $venueResult['message']])
                        ->withInput();
                }
            }

            if (empty($validatedData['location'])) {
                $validatedData['location'] = $validatedData['venue'] === 'Remote' ? 'Online' : 'Main Campus';
            }

            $updateData = [
                'unit_id' => $validatedData['unit_id'],
                'semester_id' => $validatedData['semester_id'],
                'class_id' => $validatedData['class_id'],
                'program_id' => $program->id,
                'school_id' => $program->school_id,
                'date' => $validatedData['date'],
                'day' => $validatedData['day'],
                'start_time' => $validatedData['start_time'],
                'end_time' => $validatedData['end_time'],
                'venue' => $validatedData['venue'],
                'location' => $validatedData['location'],
                'no' => $validatedData['no'],
                'chief_invigilator' => $validatedData['chief_invigilator'],
            ];

            $examTimetable->update($updateData);

            return redirect()
                ->route('schools.' . strtolower($schoolCode) . '.programs.exam-timetables.index', $program)
                ->with('success', "Exam timetable updated successfully for {$program->name}!");

        } catch (\Exception $e) {
            \Log::error('Error updating program exam timetable: ' . $e->getMessage());

            return redirect()->back()
                ->withErrors(['error' => 'Failed to update exam timetable: ' . $e->getMessage()])
                ->withInput();
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
 * ✅ FULLY FIXED: Bulk schedule exams with proper weekday gap enforcement
 * 
 * Key Features:
 * 1. Counts WEEKDAYS (not calendar days) for gap calculation
 * 2. Gap applies PER CLASS (different classes can have exams on consecutive days)
 * 3. Utilizes ALL weekdays (Mon-Fri) while respecting preparation time
 * 4. Intelligent venue assignment with capacity tracking
 * 5. Real-time conflict detection
 */
public function bulkScheduleExams(Program $program, Request $request, $schoolCode)
{
    \Log::info('=== BULK EXAM SCHEDULING STARTED ===', [
        'program_id' => $program->id,
        'program_name' => $program->name,
        'timestamp' => now()->toDateTimeString()
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

        // Load selected exam rooms
        $selectedExamroomIds = $validated['selected_examrooms'] ?? [];

        $availableRooms = Examroom::whereIn('id', $selectedExamroomIds)
            ->orderBy('capacity', 'asc')
            ->get();

        if ($availableRooms->isEmpty()) {
            throw new \Exception('No exam rooms selected or available');
        }

        \Log::info('📍 Scheduling configuration', [
            'gap_days' => $gapDays,
            'gap_type' => 'WEEKDAYS (excluding weekends)',
            'excluded_days' => $excludedDays,
            'max_exams_per_day' => $maxExamsPerDay,
            'available_rooms' => $availableRooms->count(),
            'date_range' => "{$validated['start_date']} to {$validated['end_date']}"
        ]);

        // ✅ Track last exam date PER CLASS (Carbon objects)
        $classLastExamDate = [];
        
        // Track venue capacity in 3D structure
        $venueCapacityUsage = [];
        
        // Map selected_class_units by unit_id
        $selectedUnitsMap = [];
        foreach ($validated['selected_class_units'] as $unitData) {
            $selectedUnitsMap[$unitData['unit_id']] = $unitData;
        }

        // Track exams per day
        $examsPerDay = [];

        // Loop through units
        foreach ($unitsByClass as $unitId => $unitRecords) {
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
            
            \Log::info("📝 Processing unit for scheduling", [
                'unit_code' => $firstUnit['unit_code'],
                'unit_name' => $firstUnit['unit_name'],
                'classes_involved' => $selectedClassIds,
                'assigned_time_slot' => "{$assignedStartTime} - {$assignedEndTime}",
                'slot_number' => $slotNumber,
                'total_students' => $totalStudents,
                'lecturer' => $chiefInvigilator
            ]);

            // ✅ CRITICAL: Calculate earliest allowed date using WEEKDAY gap
            $currentDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
            $earliestAllowedDate = $currentDate->copy();
            
            // Check EACH class involved in this unit
            foreach ($selectedClassIds as $classId) {
                if (isset($classLastExamDate[$classId])) {
                    // ✅ FIX: Use weekday gap calculation
                    $classMinDate = $this->addWeekdays(
                        $classLastExamDate[$classId], 
                        $gapDays
                    );
                    
                    \Log::debug("  Class {$classId} gap check", [
                        'last_exam' => $classLastExamDate[$classId]->format('Y-m-d l'),
                        'gap_required' => "{$gapDays} weekdays",
                        'min_date' => $classMinDate->format('Y-m-d l')
                    ]);
                    
                    if ($classMinDate->gt($earliestAllowedDate)) {
                        $earliestAllowedDate = $classMinDate;
                    }
                }
            }
            
            \Log::info("  ⏰ Earliest allowed date: {$earliestAllowedDate->format('Y-m-d l')}");
            
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

                // Skip weekends automatically
                if ($attemptDate->isWeekend()) {
                    $attemptDate->addDay();
                    continue;
                }

                $examDate = $attemptDate->format('Y-m-d');
                $examDay = $attemptDate->format('l');
                
                // Check max exams per day
                $examsOnThisDay = $examsPerDay[$examDate] ?? 0;
                if ($examsOnThisDay >= $maxExamsPerDay) {
                    $attemptDate->addDay();
                    continue;
                }
                
                // ✅ CRITICAL CHECK: Verify WEEKDAY gap for ALL classes
                $dateViolatesGap = false;
                foreach ($selectedClassIds as $classId) {
                    if (isset($classLastExamDate[$classId])) {
                        $weekdaysSinceLastExam = $this->countWeekdaysBetween(
                            $classLastExamDate[$classId],
                            $attemptDate
                        );
                        
                        if ($weekdaysSinceLastExam < $gapDays) {
                            $dateViolatesGap = true;
                            \Log::debug("  ❌ {$examDate} violates weekday gap for class {$classId}", [
                                'weekdays_since_last' => $weekdaysSinceLastExam,
                                'required' => $gapDays,
                                'last_exam' => $classLastExamDate[$classId]->format('Y-m-d l')
                            ]);
                            break;
                        }
                    }
                }
                
                if ($dateViolatesGap) {
                    $attemptDate->addDay();
                    continue;
                }
                
                // Generate time slot key
                $timeSlotKey = "{$assignedStartTime}-{$assignedEndTime}";
                $examStartTime = Carbon::parse("{$examDate} {$assignedStartTime}");
                $examEndTime = Carbon::parse("{$examDate} {$assignedEndTime}");

                // Find available venue
                $venueResult = $this->findVenueWithRemainingCapacity(
                    $availableRooms,
                    $totalStudents,
                    $examDate,
                    $timeSlotKey,
                    $venueCapacityUsage
                );

                if (!$venueResult['success']) {
                    $failureReasons[] = "Date {$examDate}: {$venueResult['message']}";
                    $attemptDate->addDay();
                    continue;
                }

                // Check conflicts
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
                        $conflictReasons[] = "Class {$classId} conflict with {$classConflict->unit_code}";
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
                    $failureReasons[] = "Date {$examDate}: " . implode(', ', $conflictReasons);
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
                
                // ✅ Update last exam date for ALL classes
                foreach ($selectedClassIds as $classId) {
                    $classLastExamDate[$classId] = clone $attemptDate;
                }
                
                // Update counters
                if (!isset($examsPerDay[$examDate])) {
                    $examsPerDay[$examDate] = 0;
                }
                $examsPerDay[$examDate]++;
                
                // Update venue capacity
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
                    'day' => $examDay,
                    'time' => "{$examStartTime->format('H:i')} - {$examEndTime->format('H:i')}",
                    'slot_number' => $slotNumber,
                    'venue' => $venueResult['venue'],
                    'lecturer' => $chiefInvigilator
                ];

                $scheduled = true;

                \Log::info("✅ Exam scheduled", [
                    'unit' => $firstUnit['unit_code'],
                    'date' => $examDate,
                    'day' => $examDay,
                    'venue' => $venueResult['venue'],
                    'students' => $totalStudents,
                    'attempts' => $attempts
                ]);
            }

            if (!$scheduled) {
                $reason = !empty($failureReasons) 
                    ? implode('; ', array_slice($failureReasons, -3))
                    : 'Could not find suitable slot within date range';
                
                \Log::warning('❌ Failed to schedule unit', [
                    'unit_code' => $firstUnit['unit_code'],
                    'reason' => $reason,
                    'attempts' => $attempts
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
            'gap_days' => $gapDays . ' weekdays',
            'exams_per_day' => $examsPerDay
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
                'gap_days_used' => $gapDays . ' weekdays',
                'exams_per_day' => $examsPerDay
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
 * ✅ Count weekdays between two dates (excluding weekends)
 * 
 * @param Carbon $startDate Starting date
 * @param Carbon $endDate Ending date
 * @return int Number of weekdays between the dates
 */
private function countWeekdaysBetween(Carbon $startDate, Carbon $endDate): int
{
    $weekdays = 0;
    $current = $startDate->copy()->addDay(); // Start from next day
    
    while ($current->lt($endDate)) {
        // Count only Monday-Friday (dayOfWeek: 1-5)
        if ($current->isWeekday()) {
            $weekdays++;
        }
        $current->addDay();
    }
    
    return $weekdays;
}

/**
 * ✅ Add N weekdays to a date (skipping weekends)
 * 
 * @param Carbon $date Starting date
 * @param int $weekdaysToAdd Number of weekdays to add
 * @return Carbon New date after adding weekdays
 */
private function addWeekdays(Carbon $date, int $weekdaysToAdd): Carbon
{
    $result = $date->copy();
    $added = 0;
    
    while ($added < $weekdaysToAdd) {
        $result->addDay();
        
        // Only count weekdays
        if ($result->isWeekday()) {
            $added++;
        }
    }
    
    return $result;
}

/**
 * Find venue with lowest utilization for better distribution
 */
/**
 * ✅ CORRECTED: Find venue with remaining capacity for bulk scheduling
 * NO SPLITTING - must fit ALL students in ONE venue
 */
private function findVenueWithRemainingCapacity($examrooms, $requiredCapacity, $examDate, $timeSlotKey, &$venueCapacityUsage)
{
    \Log::info('🔍 Searching for venue in bulk schedule', [
        'required_capacity' => $requiredCapacity,
        'date' => $examDate,
        'time_slot' => $timeSlotKey,
        'available_rooms_count' => count($examrooms)
    ]);

    $suitableVenues = [];
    
    foreach ($examrooms as $room) {
        // ✅ FIX 1: Skip rooms that are physically too small
        if ($room->capacity < $requiredCapacity) {
            \Log::debug("  ⛔ {$room->name} physically too small", [
                'capacity' => $room->capacity,
                'required' => $requiredCapacity
            ]);
            continue;
        }
        
        // Get current usage for this venue/date/time
        $usedCapacity = $venueCapacityUsage[$room->name][$examDate][$timeSlotKey] ?? 0;
        $remainingCapacity = $room->capacity - $usedCapacity;
        
        \Log::debug("  📊 Checking venue: {$room->name}", [
            'total_capacity' => $room->capacity,
            'used_capacity' => $usedCapacity,
            'remaining_capacity' => $remainingCapacity,
            'required' => $requiredCapacity,
            'can_fit_all' => $remainingCapacity >= $requiredCapacity ? 'YES ✅' : 'NO ❌'
        ]);
        
        // ✅ FIX 2: ONLY accept if remaining capacity can fit ALL students
        if ($remainingCapacity >= $requiredCapacity) {
            $utilization = ($usedCapacity / $room->capacity) * 100;
            $utilizationAfter = (($usedCapacity + $requiredCapacity) / $room->capacity) * 100;
            
            $suitableVenues[] = [
                'room' => $room,
                'total_capacity' => $room->capacity,
                'used_capacity' => $usedCapacity,
                'remaining_capacity' => $remainingCapacity,
                'utilization_before' => $utilization,
                'utilization_after' => $utilizationAfter,
                'space_left_after' => $remainingCapacity - $requiredCapacity
            ];
            
            \Log::debug("    ✅ {$room->name} is suitable", [
                'utilization_before' => round($utilization, 2) . '%',
                'utilization_after' => round($utilizationAfter, 2) . '%',
                'space_remaining_after' => $remainingCapacity - $requiredCapacity
            ]);
        }
    }
    
    // ✅ FIX 3: If no suitable venue, return detailed error
    if (empty($suitableVenues)) {
        // Find largest available capacity
        $largestAvailable = 0;
        $largestRoom = null;
        
        foreach ($examrooms as $room) {
            $usedCapacity = $venueCapacityUsage[$room->name][$examDate][$timeSlotKey] ?? 0;
            $remainingCapacity = $room->capacity - $usedCapacity;
            
            if ($remainingCapacity > $largestAvailable) {
                $largestAvailable = $remainingCapacity;
                $largestRoom = $room;
            }
        }
        
        \Log::warning('❌ No single venue found with sufficient capacity', [
            'required_capacity' => $requiredCapacity,
            'largest_available' => $largestAvailable,
            'largest_room' => $largestRoom ? $largestRoom->name : 'None',
            'date' => $examDate,
            'time_slot' => $timeSlotKey,
            'shortfall' => $requiredCapacity - $largestAvailable
        ]);
        
        return [
            'success' => false,
            'message' => "❌ No venue with capacity for {$requiredCapacity} students at {$timeSlotKey}.\n" .
                        "Largest available: " . ($largestRoom ? "{$largestRoom->name} ({$largestAvailable} seats)" : "None") .
                        "\nShortfall: " . ($requiredCapacity - $largestAvailable) . " seats",
            'required' => $requiredCapacity,
            'largest_available' => $largestAvailable
        ];
    }
    
    // ✅ FIX 4: Sort by efficiency (prefer smallest room that fits)
    usort($suitableVenues, function($a, $b) {
        // Prefer room with least wasted space
        return $a['space_left_after'] <=> $b['space_left_after'];
    });
    
    $bestVenue = $suitableVenues[0];
    
    \Log::info('✅ Venue selected for bulk schedule', [
        'venue' => $bestVenue['room']->name,
        'capacity_allocated' => $requiredCapacity,
        'utilization_after' => round($bestVenue['utilization_after'], 2) . '%',
        'remaining_after' => $bestVenue['space_left_after']
    ]);
    
    return [
        'success' => true,
        'venue' => $bestVenue['room']->name,
        'location' => $bestVenue['room']->location,
        'total_capacity' => $bestVenue['room']->capacity,
        'capacity_used' => $bestVenue['used_capacity'] + $requiredCapacity,
        'remaining_capacity' => $bestVenue['space_left_after']
    ];
}
    /**
     * Update venue capacity usage (3D tracking: venue -> date -> time_slot)
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
    }

    private function assignSmartVenue($studentCount, $date, $startTime, $endTime, $excludeExamId = null)
    {
        \Log::info('🔍 Smart venue assignment requested', [
            'student_count' => $studentCount,
            'date' => $date,
            'time' => "{$startTime} - {$endTime}"
        ]);

        try {
            // Get all active exam rooms sorted by capacity (smallest first for efficiency)
            $examrooms = Examroom::where('is_active', true)
                ->orderBy('capacity', 'asc')
                ->get();
            
            if ($examrooms->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No exam rooms configured in the system'
                ];
            }

            $examStartTime = Carbon::parse($date . ' ' . $startTime);
            $examEndTime = Carbon::parse($date . ' ' . $endTime);

            $suitableVenues = [];
            
            foreach ($examrooms as $room) {
                // ✅ STRICT RULE: Room must fit ALL students
                if ($room->capacity < $studentCount) {
                    \Log::debug("  ⛔ {$room->name} too small", [
                        'capacity' => $room->capacity,
                        'required' => $studentCount
                    ]);
                    continue;
                }

                // Check existing exams in this venue at this time
                $existingExamsQuery = ExamTimetable::where('venue', $room->name)
                    ->where('date', $date);
                
                if ($excludeExamId) {
                    $existingExamsQuery->where('id', '!=', $excludeExamId);
                }
                
                $existingExams = $existingExamsQuery->get();

                // Calculate occupied capacity at this time
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

                \Log::debug("  📊 {$room->name} analysis", [
                    'total_capacity' => $room->capacity,
                    'occupied' => $occupiedCapacity,
                    'remaining' => $remainingCapacity,
                    'required' => $studentCount,
                    'can_fit' => $remainingCapacity >= $studentCount ? 'YES ✅' : 'NO ❌'
                ]);

                // ✅ STRICT RULE: Must have enough remaining capacity for ALL students
                if ($remainingCapacity >= $studentCount) {
                    $utilizationAfter = (($occupiedCapacity + $studentCount) / $room->capacity) * 100;
                    
                    $suitableVenues[] = [
                        'room' => $room,
                        'total_capacity' => $room->capacity,
                        'occupied_capacity' => $occupiedCapacity,
                        'remaining_capacity' => $remainingCapacity,
                        'utilization_after' => $utilizationAfter,
                        'space_left_after' => $remainingCapacity - $studentCount
                    ];
                }
            }

            // ✅ No suitable venue found
            if (empty($suitableVenues)) {
                $largestRoom = $examrooms->sortByDesc('capacity')->first();
                
                return [
                    'success' => false,
                    'message' => "❌ No single exam room available with capacity for {$studentCount} students at {$startTime}-{$endTime}.\n\n" .
                            "Largest room: {$largestRoom->name} ({$largestRoom->capacity} seats).\n\n" .
                            "💡 Suggestions:\n" .
                            "• Choose a different time slot\n" .
                            "• Select a different date",
                    'required_capacity' => $studentCount
                ];
            }

            // ✅ Sort by efficiency (prefer smallest room that fits = less waste)
            usort($suitableVenues, function($a, $b) {
                return $a['space_left_after'] <=> $b['space_left_after'];
            });

            $bestVenue = $suitableVenues[0];

            \Log::info('✅ Venue assigned successfully', [
                'venue' => $bestVenue['room']->name,
                'location' => $bestVenue['room']->location,
                'students_accommodated' => $studentCount,
                'remaining_after' => $bestVenue['space_left_after'],
                'utilization' => round($bestVenue['utilization_after'], 1) . '%'
            ]);

            return [
                'success' => true,
                'venue' => $bestVenue['room']->name,
                'location' => $bestVenue['room']->location,
                'capacity' => $bestVenue['room']->capacity,
                'remaining_capacity' => $bestVenue['space_left_after'],
                'utilization' => round($bestVenue['utilization_after'], 1),
                'message' => "✅ Assigned {$bestVenue['room']->name} ({$studentCount}/{$bestVenue['room']->capacity} seats)"
            ];

        } catch (\Exception $e) {
            \Log::error('❌ Error in smart venue assignment', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'System error during venue assignment: ' . $e->getMessage()
            ];
        }
    }
// ============================================================
// 1. UPDATE downloadPDF() METHOD (around line 700)
// ============================================================

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

        // ✅ ADD THIS: Convert logo to base64
        $logoPath = public_path('images/strathmore.png');
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoBase64 = 'data:image/png;base64,' . $logoData;
        }

        $pdf = Pdf::loadView('examtimetables.pdf', [
            'examTimetables' => $examTimetables,
            'title' => 'Exam Timetable',
            'generatedAt' => now()->format('Y-m-d H:i:s'),
            'logoBase64' => $logoBase64,  // ✅ ADD THIS LINE
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


// ============================================================
// 2. UPDATE downloadStudentTimetable() METHOD (around line 800)
// ============================================================

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

        // ✅ ADD THIS: Convert logo to base64
        $logoPath = public_path('images/strathmore.png');
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoBase64 = 'data:image/png;base64,' . $logoData;
        }

        $data = [
            'examTimetables' => $examTimetables,
            'student' => $user,
            'currentSemester' => $selectedSemester,
            'title' => 'Student Exam Timetable',
            'generatedAt' => now()->format('F j, Y \a\t g:i A'),
            'studentName' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'studentId' => $user->student_id ?? $user->code ?? $user->id,
            'logoBase64' => $logoBase64,  // ✅ ADD THIS LINE
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


// ============================================================
// 3. UPDATE downloadProgramExamTimetablePDF() METHOD (around line 1200)
// ============================================================

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

        // ✅ ADD THIS: Convert logo to base64
        $logoPath = public_path('images/strathmore.png');
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoBase64 = 'data:image/png;base64,' . $logoData;
        }

        $pdf = Pdf::loadView('examtimetables.pdf', [
            'examTimetables' => $examTimetables,
            'title' => $program->name . ' - Exam Timetable',
            'program' => $program,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
            'logoBase64' => $logoBase64,  // ✅ ADD THIS LINE
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->download($program->code . '-exam-timetable.pdf');
    } catch (\Exception $e) {
        Log::error('Failed to generate program exam timetable PDF: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
    }
}

/**
     * Download student exam timetable PDF
     */
    public function downloadStudentExamTimetablePDF(Request $request)
    {
        $user = auth()->user();

        try {
            $enrollments = Enrollment::where('student_code', $user->code)
                ->where('status', 'enrolled')
                ->with(['semester', 'unit', 'class'])
                ->get();

            if ($enrollments->isEmpty()) {
                return redirect()->back()->with('error', 'No enrollments found.');
            }

            $query = ExamTimetable::query()
                ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
                ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
                ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
                ->whereIn('exam_timetables.unit_id', $enrollments->pluck('unit_id'))
                ->whereIn('exam_timetables.semester_id', $enrollments->pluck('semester_id'))
                ->select(
                    'exam_timetables.*',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'classes.name as class_name',
                    'classes.section as class_section', // ✅ Include section
                    'exam_timetables.group_name', // ✅ Include group_name
                    DB::raw('COALESCE(REPLACE(classes.name, " ", "-"), CONCAT("CLASS-", classes.id)) as class_code'),
                    'semesters.name as semester_name'
                )
                ->orderBy('exam_timetables.date')
                ->orderBy('exam_timetables.start_time');

            if ($request->has('semester_id') && $request->semester_id) {
                $query->where('exam_timetables.semester_id', $request->semester_id);
            }

            $examTimetables = $query->get();

            // Convert logo to base64
            $logoPath = public_path('images/strathmore.png');
            $logoBase64 = '';
            if (file_exists($logoPath)) {
                $logoData = base64_encode(file_get_contents($logoPath));
                $logoBase64 = 'data:image/png;base64,' . $logoData;
            }

            $data = [
                'examTimetables' => $examTimetables,
                'student' => $user,
                'currentSemester' => $request->semester_id 
                    ? Semester::find($request->semester_id) 
                    : $enrollments->first()->semester,
                'title' => 'Student Exam Timetable',
                'generatedAt' => now()->format('F j, Y \a\t g:i A'),
                'studentName' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'studentId' => $user->student_id ?? $user->code ?? $user->id,
                'logoBase64' => $logoBase64,
            ];

            $pdf = PDF::loadView('examtimetables.student', $data);
            $pdf->setPaper('a4', 'portrait');

            $studentId = $user->student_id ?? $user->code ?? $user->id;
            $filename = "exam-timetable-{$studentId}-" . now()->format('Y-m-d') . ".pdf";

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Failed to generate student exam timetable PDF', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }


    // ============================================================
    // 4. UPDATE downloadAllPDF() METHOD (around line 1700)
    // ============================================================

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

    // ✅ ADD THIS: Convert logo to base64
    $logoPath = public_path('images/strathmore.png');
    $logoBase64 = '';
    if (file_exists($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoBase64 = 'data:image/png;base64,' . $logoData;
    }

    // Prepare data matching your PDF view
    $data = [
        'title' => 'UNIVERSITY EXAMINATION TIMETABLE',
        'generatedAt' => now()->format('F d, Y \a\t h:i A'),
        'examTimetables' => $transformedExams,
        'logoBase64' => $logoBase64,  // ✅ ADD THIS LINE
    ];

    // Generate PDF
    $pdf = PDF::loadView('examtimetables.pdf', $data)
        ->setPaper('A4', 'landscape');

    // Download
    return $pdf->download('All_Exam_Timetables_' . now()->format('Y-m-d') . '.pdf');
}

}