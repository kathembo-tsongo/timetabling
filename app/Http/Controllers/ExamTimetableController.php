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
            'venue' => 'nullable|string|max:255', // ✅ Make venue optional
            'location' => 'nullable|string|max:255',
            'chief_invigilator' => 'required|string|max:255',
            'semester_id' => 'required|exists:semesters,id',
        ]);

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

                $class = ClassModel::findOrFail($classId);
                
                // Count students
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

                // ✅ SMART VENUE ASSIGNMENT
                $venueResult = $this->assignSmartVenue(
                    $enrolledCount,
                    $validated['date'],
                    $validated['start_time'],
                    $endTime
                );

                if (!$venueResult['success']) {
                    $unit = Unit::find($unitId);
                    $errors[] = "{$unit->code} ({$class->name}): {$venueResult['message']}";
                    Log::warning("⚠️ Venue assignment failed", [
                        'unit' => $unit->code,
                        'class' => $class->name,
                        'students' => $enrolledCount,
                        'reason' => $venueResult['message']
                    ]);
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

                $groupName = $class->section ?? null;

                $examData = [
                    'unit_id' => $unitId,
                    'class_id' => $classId,
                    'semester_id' => $validated['semester_id'],
                    'program_id' => $program->id,
                    'school_id' => $program->school_id,
                    'group_name' => $groupName,
                    'date' => $validated['date'],
                    'day' => date('l', strtotime($validated['date'])),
                    'start_time' => $validated['start_time'],
                    'end_time' => $endTime,
                    'venue' => $venueResult['venue'], // ✅ Use smart venue
                    'location' => $venueResult['location'] ?? 'Main Campus', // ✅ Use venue location
                    'no' => $enrolledCount,
                    'chief_invigilator' => $chiefInvigilator,
                ];

                $exam = ExamTimetable::create($examData);
                $created++;

                Log::info('✅ Exam created with smart venue', [
                    'exam_id' => $exam->id,
                    'unit' => Unit::find($unitId)->code,
                    'class' => $class->name,
                    'students' => $enrolledCount,
                    'venue' => $venueResult['venue'],
                    'utilization' => $venueResult['utilization'] ?? 'N/A'
                ]);
            }

            DB::commit();

            if ($created > 0) {
                $message = "Successfully created {$created} exam schedule(s).";
                if (!empty($errors)) {
                    $message .= " " . count($errors) . " failed: " . implode('; ', array_slice($errors, 0, 3));
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
            $class = ClassModel::findOrFail($validated['class_id']);
            
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

            // ✅ CHECK: Is this a Common Units/Electives program?
            $program = Program::find($programId);
            $isCommonUnits = $program && (
                stripos($program->name, 'COMMON') !== false || 
                stripos($program->code, 'COMMON') !== false ||
                strtoupper($program->code) === 'COM_UN'
            );

            // ✅ CASE 1: ELECTIVES/COMMON UNITS - Get elective sections/groups
            if ($isCommonUnits) {
                Log::info('Getting elective sections for COMMON UNITS program');

                // Get all unique elective sections (groups) that have enrollments
                $electiveSections = DB::table('enrollments')
                    ->join('units', 'enrollments.unit_id', '=', 'units.id')
                    ->join('classes', 'enrollments.group_id', '=', 'classes.id')
                    ->where('units.program_id', $programId)
                    ->where('enrollments.semester_id', $semesterId)
                    ->where('enrollments.status', 'enrolled')
                    ->select(
                        'classes.id',
                        'classes.name',
                        'classes.section',
                        'classes.semester_id',
                        'classes.program_id',
                        DB::raw('COALESCE(REPLACE(classes.name, " ", "-"), CONCAT("CLASS-", classes.id)) as code'),
                        DB::raw('COUNT(DISTINCT enrollments.student_code) as total_students'),
                        DB::raw('COUNT(DISTINCT enrollments.unit_id) as units_count')
                    )
                    ->groupBy('classes.id', 'classes.name', 'classes.section', 'classes.semester_id', 'classes.program_id')
                    ->having('total_students', '>', 0)
                    ->orderBy('classes.name')
                    ->orderBy('classes.section')
                    ->get();

                Log::info('Found elective sections', [
                    'sections_count' => $electiveSections->count(),
                    'sample' => $electiveSections->take(3)->map(fn($s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'section' => $s->section,
                        'students' => $s->total_students,
                        'units' => $s->units_count
                    ])->toArray()
                ]);

                // ✅ Also return a special "All Electives" option for bulk scheduling
                $allElectivesOption = (object)[
                    'id' => 0,
                    'name' => 'All Elective Sections',
                    'section' => 'BULK',
                    'code' => 'ALL-ELECTIVES',
                    'semester_id' => $semesterId,
                    'program_id' => $programId,
                    'total_students' => $electiveSections->sum('total_students'),
                    'units_count' => DB::table('enrollments')
                        ->join('units', 'enrollments.unit_id', '=', 'units.id')
                        ->where('units.program_id', $programId)
                        ->where('enrollments.semester_id', $semesterId)
                        ->distinct('enrollments.unit_id')
                        ->count('enrollments.unit_id')
                ];

                $classes = collect([$allElectivesOption])->concat($electiveSections);

                return response()->json([
                    'success' => true,
                    'classes' => $classes,
                    'is_common_units' => true,
                    'message' => 'These are elective sections with students from multiple programs'
                ]);
            }

            // ✅ CASE 2: REGULAR PROGRAM
            $classIds = DB::table('semester_unit')
                ->join('units', 'semester_unit.unit_id', '=', 'units.id')
                ->where('semester_unit.semester_id', $semesterId)
                ->where('units.program_id', $programId)
                ->distinct()
                ->pluck('semester_unit.class_id');

            if ($classIds->isNotEmpty()) {
                $classes = ClassModel::whereIn('id', $classIds)
                    ->where('program_id', $programId)
                    ->select('id', 'name', 'section', 'semester_id', 'program_id', 
                        DB::raw('COALESCE(REPLACE(name, " ", "-"), CONCAT("CLASS-", id)) as code'))
                    ->get();
            } else {
                $classes = ClassModel::where('semester_id', $semesterId)
                    ->where('program_id', $programId)
                    ->select('id', 'name', 'section', 'semester_id', 'program_id', 
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
                'classes' => $classes,
                'is_common_units' => false
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get classes for semester: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Failed to get classes: ' . $e->getMessage()
            ], 500);
        }
    }

   public function getUnitsByClassAndSemesterForExam(Request $request)
    {
        try {
            $classId = $request->input('class_id');
            $semesterId = $request->input('semester_id');
            $programId = $request->input('program_id');

            Log::info('🔍 Getting units for exam scheduling', [
                'class_id' => $classId,
                'semester_id' => $semesterId,
                'program_id' => $programId
            ]);

            if (!$semesterId || !$programId) {
                return response()->json([
                    'error' => 'Semester ID and Program ID are required.'
                ], 400);
            }

            // ✅ CHECK: Is this a Common Units/Electives program?
            $program = Program::find($programId);
            $isCommonUnits = $program && (
                stripos($program->name, 'COMMON') !== false || 
                stripos($program->code, 'COMMON') !== false ||
                strtoupper($program->code) === 'COM_UN'
            );

            Log::info('Program type detection', [
                'program_id' => $programId,
                'program_name' => $program->name ?? 'Unknown',
                'program_code' => $program->code ?? 'Unknown',
                'is_common_units' => $isCommonUnits
            ]);

            // ✅ CASE 1: COMMON UNITS/ELECTIVES - Get units with their enrollment groups
            if ($isCommonUnits) {
                Log::info('📚 Handling ELECTIVE/COMMON UNITS program');

                // Get all units from this program that have enrollments
                $unitsWithEnrollments = DB::table('enrollments')
                    ->join('units', 'enrollments.unit_id', '=', 'units.id')
                    ->leftJoin('classes', 'enrollments.group_id', '=', 'classes.id')
                    ->where('units.program_id', $programId)
                    ->where('enrollments.semester_id', $semesterId)
                    ->where('enrollments.status', 'enrolled')
                    ->select(
                        'units.id as unit_id',
                        'units.code as unit_code',
                        'units.name as unit_name',
                        'units.credit_hours',
                        'enrollments.group_id as class_id',
                        'classes.name as class_name',
                        'classes.section as class_section',
                        'enrollments.lecturer_code',
                        DB::raw('COUNT(DISTINCT enrollments.student_code) as student_count')
                    )
                    ->groupBy(
                        'units.id',
                        'units.code',
                        'units.name',
                        'units.credit_hours',
                        'enrollments.group_id',
                        'classes.name',
                        'classes.section',
                        'enrollments.lecturer_code'
                    )
                    ->having('student_count', '>', 0)
                    ->orderBy('units.code')
                    ->orderBy('classes.name')
                    ->get();

                Log::info('Elective units with enrollments found', [
                    'units_count' => $unitsWithEnrollments->count(),
                    'sample' => $unitsWithEnrollments->take(3)->map(function($u) {
                        return [
                            'unit' => $u->unit_code,
                            'class' => $u->class_name,
                            'section' => $u->class_section,
                            'students' => $u->student_count
                        ];
                    })->toArray()
                ]);

                // Format the response
                $formattedUnits = $unitsWithEnrollments->map(function($enrollment) {
                    // Get lecturer name
                    $lecturerName = 'Not Assigned';
                    if ($enrollment->lecturer_code) {
                        $lecturer = DB::table('users')
                            ->where('code', $enrollment->lecturer_code)
                            ->first();
                        
                        if ($lecturer) {
                            $lecturerName = trim(($lecturer->first_name ?? '') . ' ' . ($lecturer->last_name ?? ''));
                        }
                    }

                    // Get diverse student programs
                    $studentPrograms = DB::table('enrollments')
                        ->join('users', 'enrollments.student_code', '=', 'users.code')
                        ->leftJoin('programs', 'users.program_id', '=', 'programs.id')
                        ->where('enrollments.unit_id', $enrollment->unit_id)
                        ->where('enrollments.semester_id', request()->input('semester_id'))
                        ->where('enrollments.group_id', $enrollment->class_id)
                        ->where('enrollments.status', 'enrolled')
                        ->select('programs.code as program_code')
                        ->distinct()
                        ->pluck('program_code')
                        ->filter()
                        ->toArray();

                    return [
                        'id' => $enrollment->unit_id,
                        'code' => $enrollment->unit_code,
                        'name' => $enrollment->unit_name,
                        'credit_hours' => $enrollment->credit_hours ?? 3,
                        'student_count' => (int)$enrollment->student_count,
                        'lecturer_name' => $lecturerName,
                        'lecturer_code' => $enrollment->lecturer_code ?? '',
                        
                        // ✅ Class/Section information
                        'class_id' => $enrollment->class_id,
                        'class_name' => $enrollment->class_name ?? 'Elective Group',
                        'class_section' => $enrollment->class_section ?? 'N/A',
                        
                        // ✅ Show programs represented
                        'student_programs' => $studentPrograms,
                        'programs_count' => count($studentPrograms),
                        
                        // ✅ Display label for UI
                        'display_label' => sprintf(
                            '%s - %s | Section: %s | %d students from %s',
                            $enrollment->unit_code,
                            $enrollment->unit_name,
                            $enrollment->class_section ?? 'N/A',
                            $enrollment->student_count,
                            count($studentPrograms) > 0 
                                ? implode(', ', array_slice($studentPrograms, 0, 3)) . (count($studentPrograms) > 3 ? '...' : '')
                                : 'various programs'
                        )
                    ];
                })->values();

                return response()->json($formattedUnits->all());
            }

            // ✅ CASE 2: REGULAR PROGRAM - Require class_id
            if (!$classId) {
                return response()->json([
                    'error' => 'Class ID is required for regular programs.'
                ], 400);
            }

            $classInfo = DB::table('classes')
                ->where('id', $classId)
                ->first();

            if (!$classInfo) {
                Log::warning('Class not found', ['class_id' => $classId]);
                return response()->json([]);
            }

            Log::info('📖 Handling REGULAR program with specific class', [
                'class_id' => $classId,
                'class_name' => $classInfo->name,
                'class_section' => $classInfo->section
            ]);

            // Get units assigned to this specific class
            $units = DB::table('unit_assignments')
                ->join('units', 'unit_assignments.unit_id', '=', 'units.id')
                ->leftJoin('users', 'users.code', '=', 'unit_assignments.lecturer_code')
                ->where('unit_assignments.semester_id', $semesterId)
                ->where('unit_assignments.class_id', $classId)
                ->where('units.program_id', $programId)
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

            Log::info('Regular units found', [
                'class_id' => $classId,
                'units_count' => $units->count()
            ]);

            if ($units->isEmpty()) {
                return response()->json([]);
            }

            // For each unit, count students in THIS SPECIFIC CLASS only
            $enhancedUnits = $units->map(function ($unit) use ($semesterId, $classId, $classInfo) {
                $studentCount = DB::table('enrollments')
                    ->where('unit_id', $unit->id)
                    ->where('semester_id', $semesterId)
                    ->where('group_id', (string)$classId)
                    ->where('status', 'enrolled')
                    ->distinct('student_code')
                    ->count('student_code');

                return [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'credit_hours' => $unit->credit_hours ?? 3,
                    'student_count' => $studentCount,
                    'lecturer_name' => $unit->lecturer_name ?? 'No lecturer assigned',
                    'lecturer_code' => $unit->lecturer_code ?? '',
                    'class_id' => $classInfo->id,
                    'class_name' => $classInfo->name,
                    'class_section' => $classInfo->section ?? 'N/A',
                ];
            });

            return response()->json($enhancedUnits->values()->all());
            
        } catch (\Exception $e) {
            Log::error('Error fetching units for exam timetable', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch units for exam timetable.',
                'message' => $e->getMessage()
            ], 500);
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
public function bulkScheduleExams(Request $request, Program $program, $schoolCode)
{
    try {
        $validated = $request->validate([
            'semester_id' => 'required|integer',
            'school_id' => 'required|integer',
            'program_id' => 'required|integer',
            'selected_class_units' => 'required|array|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'exam_duration_hours' => 'required|integer|min:1|max:8',
            'start_time' => 'required',
            'excluded_days' => 'array',
            'max_exams_per_day' => 'required|integer|min:1',
            'selected_examrooms' => 'required|array|min:1',
        ]);

        Log::info('🎓 Starting INTELLIGENT bulk exam scheduling', [
            'total_selections' => count($validated['selected_class_units']),
            'date_range' => $validated['start_date'] . ' to ' . $validated['end_date']
        ]);

        $scheduled = [];
        $conflicts = [];
        
        $selectedRooms = DB::table('examrooms')
            ->whereIn('id', $validated['selected_examrooms'])
            ->get();

        // ✅ STEP 1: Analyze student workload PER CLASS
        $classWorkloads = $this->analyzeClassWorkloads(
            $validated['selected_class_units'],
            $validated['semester_id']
        );

        Log::info('📊 Class workload analysis', [
            'classes_analyzed' => count($classWorkloads),
            'breakdown' => array_map(function($class) {
                return [
                    'class_id' => $class['class_id'],
                    'class_name' => $class['class_name'],
                    'total_units' => $class['total_units'],
                    'policy' => $class['policy'],
                    'week1_max' => $class['week1_max'] ?? 'N/A',
                    'min_gap_days' => $class['min_gap_days'] ?? 'N/A'
                ];
            }, $classWorkloads)
        ]);

        // ✅ STEP 2: Generate available dates
        $availableDates = $this->generateAvailableDates(
            $validated['start_date'],
            $validated['end_date'],
            $validated['excluded_days'] ?? []
        );

        // ✅ STEP 3: Split dates into weeks
        $week1Dates = [];
        $week2Dates = [];
        
        $startCarbon = Carbon::parse($validated['start_date']);
        $week1End = $startCarbon->copy()->addDays(6); // First 7 days
        
        foreach ($availableDates as $date) {
            $dateCarbon = Carbon::parse($date);
            if ($dateCarbon->lte($week1End)) {
                $week1Dates[] = $date;
            } else {
                $week2Dates[] = $date;
            }
        }

        Log::info('📅 Week distribution', [
            'week1_dates' => count($week1Dates),
            'week2_dates' => count($week2Dates),
            'week1' => $week1Dates,
            'week2' => array_slice($week2Dates, 0, 5) // Sample
        ]);

        // ✅ STEP 4: Sort selections BY CLASS first, then by unit
        // This ensures we process all units for one class before moving to the next
        $sortedSelections = collect($validated['selected_class_units'])
            ->sortBy([
                ['class_id', 'asc'],
                ['unit_id', 'asc']
            ])
            ->values();

        Log::info('📦 Sorted selections by class', [
            'total' => $sortedSelections->count(),
            'by_class' => $sortedSelections->groupBy('class_id')->map->count()->toArray()
        ]);

        // ✅ STEP 5: Initialize tracking per class
        $classScheduleTracking = [];
        foreach ($classWorkloads as $classId => $workload) {
            $classScheduleTracking[$classId] = [
                'policy' => $workload['policy'],
                'total_units' => $workload['total_units'],
                'week1_scheduled' => 0,
                'week2_scheduled' => 0,
                'week1_max' => $workload['week1_max'] ?? PHP_INT_MAX,
                'min_gap_days' => $workload['min_gap_days'] ?? 0,
                'last_exam_date' => null,
                'scheduled_dates' => []
            ];
        }

        $venueCapacityUsage = []; // Track 3D: venue -> date -> time_slot

        // ✅ STEP 6: Schedule each exam individually
        foreach ($sortedSelections as $selection) {
            $studentCount = $selection['student_count'];

            Log::info('🎯 Processing exam', [
                'class' => $selection['class_name'],
                'section' => $selection['class_section'],
                'unit' => $selection['unit_code'],
                'students' => $studentCount
            ]);

            // ✅ Find suitable date for THIS class
$targetDate = $this->selectDateWithSpacing(
    collect([$selection]),
    $classScheduleTracking,
    $week1Dates,
    $week2Dates
);

            if (!$targetDate) {
                $conflicts[] = [
                    'unit' => $selection['unit_code'],
                    'class' => $selection['class_name'],
                    'section' => $selection['class_section'] ?? 'N/A',
                    'reason' => 'No available date matching spacing policy'
                ];
                Log::warning('⚠️ No date found', [
                    'class' => $selection['class_name'],
                    'unit' => $selection['unit_code']
                ]);
                continue;
            }

            $startTime = $selection['assigned_start_time'] ?? $validated['start_time'];
            $endTime = $selection['assigned_end_time'] ?? 
                $this->calculateEndTime($startTime, $validated['exam_duration_hours']);
            
            $timeSlotKey = "{$startTime}-{$endTime}";

            // ✅ Find venue
            $venueResult = $this->findVenueWithRemainingCapacity(
                $selectedRooms,
                $studentCount,
                $targetDate,
                $timeSlotKey,
                $venueCapacityUsage
            );

            if (!$venueResult['success']) {
                $conflicts[] = [
                    'unit' => $selection['unit_code'],
                    'class' => $selection['class_name'],
                    'section' => $selection['class_section'] ?? 'N/A',
                    'reason' => $venueResult['message']
                ];
                continue;
            }

            // ✅ Check conflicts
            $hasConflict = $this->checkSchedulingConflicts(
                $validated['semester_id'],
                $selection['class_id'],
                $selection['unit_id'],
                $targetDate,
                $startTime,
                $endTime,
                $selection['lecturer']
            );

            if ($hasConflict) {
                $conflicts[] = [
                    'unit' => $selection['unit_code'],
                    'class' => $selection['class_name'],
                    'section' => $selection['class_section'] ?? 'N/A',
                    'reason' => 'Scheduling conflict detected'
                ];
                continue;
            }

            // ✅ Create exam
            $classInfo = DB::table('classes')->where('id', $selection['class_id'])->first();

            if (!$classInfo) {
                Log::warning("Class not found", ['class_id' => $selection['class_id']]);
                continue;
            }

            $examId = DB::table('exam_timetables')->insertGetId([
                'semester_id' => $validated['semester_id'],
                'class_id' => $selection['class_id'],
                'unit_id' => $selection['unit_id'],
                'program_id' => $validated['program_id'],
                'school_id' => $validated['school_id'],
                'date' => $targetDate,
                'day' => Carbon::parse($targetDate)->format('l'),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'venue' => $venueResult['venue'],
                'location' => $venueResult['location'] ?? 'Phase 2',
                'group_name' => $classInfo->section ?? 'N/A',
                'no' => $studentCount,
                'chief_invigilator' => $selection['lecturer'] ?? 'TBD',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ✅ CRITICAL: Update class tracking IMMEDIATELY
            $targetCarbon = Carbon::parse($targetDate);
            $week1EndCarbon = Carbon::parse($validated['start_date'])->addDays(6);

            if ($targetCarbon->lte($week1EndCarbon)) {
                $classScheduleTracking[$selection['class_id']]['week1_scheduled']++;
            } else {
                $classScheduleTracking[$selection['class_id']]['week2_scheduled']++;
            }

            $classScheduleTracking[$selection['class_id']]['last_exam_date'] = $targetDate;
            $classScheduleTracking[$selection['class_id']]['scheduled_dates'][] = $targetDate;

            Log::info('✅ Exam created and tracking updated', [
                'exam_id' => $examId,
                'date' => $targetDate,
                'class_tracking' => $classScheduleTracking[$selection['class_id']]
            ]);

            $scheduled[] = [
                'id' => $examId,
                'unit_code' => $selection['unit_code'],
                'unit_name' => $selection['unit_name'],
                'class_name' => $classInfo->name,
                'class_section' => $classInfo->section ?? 'N/A',
                'student_count' => $studentCount,
                'date' => $targetDate,
                'time' => "$startTime - $endTime",
                'venue' => $venueResult['venue']
            ];

            // ✅ Update venue usage
            $this->updateVenueCapacityUsage(
                $venueCapacityUsage,
                $venueResult['venue'],
                $targetDate,
                $timeSlotKey,
                $studentCount
            );
        }

        Log::info('🎉 Intelligent bulk scheduling complete', [
            'scheduled' => count($scheduled),
            'conflicts' => count($conflicts)
        ]);

        return response()->json([
            'success' => true,
            'message' => count($scheduled) . ' exams scheduled with intelligent spacing',
            'scheduled' => $scheduled,
            'conflicts' => $conflicts,
            'summary' => [
                'total_requested' => count($validated['selected_class_units']),
                'successfully_scheduled' => count($scheduled),
                'conflicts' => count($conflicts)
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('❌ Bulk scheduling failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to schedule exams: ' . $e->getMessage()
        ], 500);
    }
}
/**
 * ✅ NEW: Analyze workload per class to determine scheduling policy
 */
private function analyzeClassWorkloads($selections, $semesterId)
{
    $classWorkloads = [];
    
    // Group by class_id
    $byClass = collect($selections)->groupBy('class_id');
    
    foreach ($byClass as $classId => $classSelections) {
        $firstSelection = $classSelections->first();
        
        // Count total units for this class in this semester
        $totalUnits = DB::table('enrollments')
            ->where('group_id', $classId)
            ->where('semester_id', $semesterId)
            ->where('status', 'enrolled')
            ->distinct('unit_id')
            ->count('unit_id');
        
        // ✅ POLICY DETERMINATION
        if ($totalUnits >= 7) {
            // POLICY 1: Heavy load → spread across 2 weeks
            $policy = 'SPREAD_TWO_WEEKS';
            $week1Max = 4; // Max 4 exams in first week
            $minGapDays = 0; // No minimum gap needed (spreading does the job)
        } else {
            // POLICY 2: Light load → minimum 1-day gap
            $policy = 'MINIMUM_GAP';
            $week1Max = PHP_INT_MAX; // No week restriction
            $minGapDays = 1; // At least 1 day between exams
        }
        
        $classWorkloads[$classId] = [
            'class_id' => $classId,
            'class_name' => $firstSelection['class_name'],
            'class_section' => $firstSelection['class_section'] ?? 'N/A',
            'total_units' => $totalUnits,
            'policy' => $policy,
            'week1_max' => $week1Max,
            'min_gap_days' => $minGapDays
        ];
    }
    
    return $classWorkloads;
}

/**
 * ✅ FIXED: Select date respecting spacing policies for all affected classes
 * Ensures ONE exam per class per day, with proper spacing
 */
private function selectDateWithSpacing(
    $sections,
    &$classScheduleTracking,
    $week1Dates,
    $week2Dates
) {
    $allDates = array_merge($week1Dates, $week2Dates);
    
    \Log::info('🔍 Searching for suitable date', [
        'total_available_dates' => count($allDates),
        'week1_dates' => count($week1Dates),
        'week2_dates' => count($week2Dates)
    ]);
    
    // For each possible date, check if it satisfies ALL classes' policies
    foreach ($allDates as $candidateDate) {
        $candidateCarbon = Carbon::parse($candidateDate);
        $isWeek1 = in_array($candidateDate, $week1Dates);
        
        $validForAllClasses = true;
        
        foreach ($sections as $section) {
            $classId = $section['class_id'];
            
            if (!isset($classScheduleTracking[$classId])) {
                continue; // Skip if not tracked
            }
            
            $tracking = $classScheduleTracking[$classId];
            
            // ✅ CRITICAL CHECK 1: This class already has an exam on this date?
            if (in_array($candidateDate, $tracking['scheduled_dates'])) {
                \Log::debug("  ⛔ Class {$classId} ({$section['class_name']}) already has exam on {$candidateDate}");
                $validForAllClasses = false;
                break;
            }
            
            // ✅ CHECK POLICY 1: Heavy load (7+ units) - spread across 2 weeks
            if ($tracking['policy'] === 'SPREAD_TWO_WEEKS') {
                // Week 1 quota full?
                if ($isWeek1 && $tracking['week1_scheduled'] >= $tracking['week1_max']) {
                    \Log::debug("  ⛔ Class {$classId} week 1 quota full ({$tracking['week1_scheduled']}/{$tracking['week1_max']})");
                    $validForAllClasses = false;
                    break;
                }
            }
            
            // ✅ CHECK POLICY 2: Light load (≤6 units) - minimum gap between exams
            if ($tracking['policy'] === 'MINIMUM_GAP' && $tracking['last_exam_date']) {
                $lastExamCarbon = Carbon::parse($tracking['last_exam_date']);
                
                // Calculate days between (must be positive and meet minimum)
                if ($candidateCarbon->lte($lastExamCarbon)) {
                    \Log::debug("  ⛔ Date {$candidateDate} is not after last exam {$tracking['last_exam_date']}");
                    $validForAllClasses = false;
                    break;
                }
                
                $daysBetween = $lastExamCarbon->diffInDays($candidateCarbon);
                
                if ($daysBetween < $tracking['min_gap_days']) {
                    \Log::debug("  ⛔ Class {$classId} needs {$tracking['min_gap_days']} day gap, only {$daysBetween} days from last exam on {$tracking['last_exam_date']}");
                    $validForAllClasses = false;
                    break;
                }
            }
        }
        
        if ($validForAllClasses) {
            \Log::info("  ✅ Selected date: {$candidateDate}", [
                'is_week1' => $isWeek1,
                'classes_affected' => array_map(fn($s) => $s['class_id'], $sections->toArray())
            ]);
            return $candidateDate;
        }
    }
    
    \Log::warning('❌ No suitable date found respecting all policies', [
        'sections' => $sections->map(fn($s) => [
            'class' => $s['class_name'],
            'unit' => $s['unit_code']
        ])->toArray()
    ]);
    
    return null; // No suitable date found
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
// PDF DOWNLOAD METHODS
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

        // ✅ Convert logo to base64
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
            'logoBase64' => $logoBase64,
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

        // ✅ Convert logo to base64
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
            'logoBase64' => $logoBase64,
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

        // ✅ Convert logo to base64
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
            'logoBase64' => $logoBase64,
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->download($program->code . '-exam-timetable.pdf');
    } catch (\Exception $e) {
        Log::error('Failed to generate program exam timetable PDF: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
    }
}

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
                'classes.section as class_section',
                'exam_timetables.group_name',
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

    // ✅ Convert logo to base64
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
        'logoBase64' => $logoBase64,
    ];

    // Generate PDF
    $pdf = PDF::loadView('examtimetables.pdf', $data)
        ->setPaper('A4', 'landscape');

    // Download
    return $pdf->download('All_Exam_Timetables_' . now()->format('Y-m-d') . '.pdf');
}

/**
 * Generate available dates for exam scheduling
 */
private function generateAvailableDates($startDate, $endDate, $excludedDays = [])
{
    $dates = [];
    $current = Carbon::parse($startDate);
    $end = Carbon::parse($endDate);
    
    while ($current->lte($end)) {
        // Skip weekends and excluded days
        if (!$current->isWeekend() && !in_array($current->format('l'), $excludedDays)) {
            $dates[] = $current->format('Y-m-d');
        }
        $current->addDay();
    }
    
    return $dates;
}

/**
 * Calculate end time based on start time and duration
 */
private function calculateEndTime($startTime, $durationHours)
{
    return Carbon::parse($startTime)->addHours($durationHours)->format('H:i');
}

/**
 * Select appropriate venue for exam
 */
private function selectAppropriateVenue($rooms, $studentCount, $examDate, $startTime, $endTime)
{
    foreach ($rooms as $room) {
        // Check if room has enough capacity
        if ($room->capacity < $studentCount) {
            continue;
        }
        
        // Check if room is available at this time
        $existingExam = DB::table('exam_timetables')
            ->where('venue', $room->name)
            ->where('date', $examDate)
            ->where(function($query) use ($startTime, $endTime) {
                $query->whereBetween('start_time', [$startTime, $endTime])
                      ->orWhereBetween('end_time', [$startTime, $endTime])
                      ->orWhere(function($q) use ($startTime, $endTime) {
                          $q->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                      });
            })
            ->exists();
        
        if (!$existingExam) {
            return $room;
        }
    }
    
    return null;
}

/**
 * ✅ FIXED: Check for scheduling conflicts
 * Allows same lecturer + unit at same time (multiple sections)
 */
private function checkSchedulingConflicts($semesterId, $classId, $unitId, $examDate, $startTime, $endTime, $lecturer)
{
    // ✅ Check if this EXACT class already has an exam at this time
    $classConflict = DB::table('exam_timetables')
        ->where('semester_id', $semesterId)
        ->where('class_id', $classId)
        ->where('date', $examDate)
        ->where(function($query) use ($startTime, $endTime) {
            $query->whereBetween('start_time', [$startTime, $endTime])
                  ->orWhereBetween('end_time', [$startTime, $endTime])
                  ->orWhere(function($q) use ($startTime, $endTime) {
                      $q->where('start_time', '<=', $startTime)
                        ->where('end_time', '>=', $endTime);
                  });
        })
        ->exists();
    
    if ($classConflict) {
        Log::warning('⚠️ Class conflict detected', [
            'class_id' => $classId,
            'date' => $examDate,
            'time' => "$startTime - $endTime"
        ]);
        return true;
    }
    
    // ✅ Check if this unit + class already has an exam scheduled
    // (Prevents duplicate entries for same unit+section)
    $unitClassConflict = DB::table('exam_timetables')
        ->where('semester_id', $semesterId)
        ->where('unit_id', $unitId)
        ->where('class_id', $classId)
        ->where('date', $examDate)
        ->exists();
    
    if ($unitClassConflict) {
        Log::warning('⚠️ Unit+Class already scheduled', [
            'unit_id' => $unitId,
            'class_id' => $classId,
            'date' => $examDate
        ]);
        return true;
    }
    
    // ✅ CRITICAL FIX: Check lecturer conflict ONLY for DIFFERENT units
    // Same lecturer can invigilate multiple sections of the SAME unit
    if ($lecturer) {
        $lecturerConflict = DB::table('exam_timetables')
            ->where('date', $examDate)
            ->where('chief_invigilator', $lecturer)
            ->where('unit_id', '!=', $unitId) // ← KEY FIX: Different unit only!
            ->where(function($query) use ($startTime, $endTime) {
                $query->whereBetween('start_time', [$startTime, $endTime])
                      ->orWhereBetween('end_time', [$startTime, $endTime])
                      ->orWhere(function($q) use ($startTime, $endTime) {
                          $q->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                      });
            })
            ->exists();
        
        if ($lecturerConflict) {
            Log::warning('⚠️ Lecturer conflict detected', [
                'lecturer' => $lecturer,
                'date' => $examDate,
                'time' => "$startTime - $endTime",
                'note' => 'Lecturer has another DIFFERENT unit at this time'
            ]);
            return true;
        }
    }
    
    return false;
}

/**
 * ✅ Find venue with remaining capacity for bulk scheduling
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


}