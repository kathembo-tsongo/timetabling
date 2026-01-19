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
use App\Services\FailedExamLogger;
use App\Models\FailedExamSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;


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

    public function index(Request $request, Program $program, $schoolCode)
    {
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        $query = ExamTimetable::query()
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
            ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
            ->where('exam_timetables.program_id', $program->id);

        // ✅ CRITICAL: Select ALL columns explicitly to avoid pagination issues
        $examTimetables = $query->select(
            'exam_timetables.id',
            'exam_timetables.semester_id',
            'exam_timetables.class_id',
            'exam_timetables.group_name',  // ✅ Section column
            'exam_timetables.unit_id',
            'exam_timetables.day',
            'exam_timetables.date',
            'exam_timetables.start_time',
            'exam_timetables.end_time',
            'exam_timetables.venue',
            'exam_timetables.location',
            'exam_timetables.no',  // ✅ Student count
            'exam_timetables.chief_invigilator',
            'exam_timetables.program_id',
            'exam_timetables.school_id',
            'exam_timetables.created_at',
            'exam_timetables.updated_at',
            // Joined columns
            'units.name as unit_name',
            'units.code as unit_code',
            'classes.name as class_name',
            'classes.section as class_section',  // ✅ Class section
            DB::raw('COALESCE(REPLACE(classes.name, " ", "-"), CONCAT("CLASS-", classes.id)) as class_code'),
            'semesters.name as semester_name'
        )
        ->orderBy('exam_timetables.date')
        ->orderBy('exam_timetables.start_time')
        ->paginate($request->get('per_page', 15));
        // Add lecturer names
        foreach ($examTimetables as $exam) {
            $lecturerCode = DB::table('enrollments')
                ->where('unit_id', $exam->unit_id)
                ->where('group_id', $exam->class_id)
                ->where('semester_id', $exam->semester_id)
                ->value('lecturer_code');
                
            if ($lecturerCode) {
                $exam->lecturer_code = $lecturerCode;
                $exam->lecturer_name = $this->getLecturerName($lecturerCode);
            }
        }


            \Log::info('Data being sent to frontend:', [
        'first_exam_full' => $examTimetables->first() ? $examTimetables->first()->toArray() : null,
        'pagination_type' => get_class($examTimetables),
    ]);

    return inertia('ExamTimetable/Index', [
        'examTimetables' => [
            'data' => $examTimetables->items(),
            'links' => $examTimetables->linkCollection(),
            'meta' => [
                'current_page' => $examTimetables->currentPage(),
                'from' => $examTimetables->firstItem(),
                'last_page' => $examTimetables->lastPage(),
                'per_page' => $examTimetables->perPage(),
                'to' => $examTimetables->lastItem(),
                'total' => $examTimetables->total(),
            ]
        ],
        'program' => $program,
        'semesters' => Semester::all(),
        'schoolCode' => $schoolCode,
        'classrooms' => Examroom::where('is_active', true)->orderBy('name')->get(),
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


// ✅ CORRECTED: Fix for elective exam scheduling
// Replace in ExamTimetableController.php

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

        // ✅ CASE 1: ELECTIVES/COMMON UNITS - Group by Unit + Lecturer ONLY
        if ($isCommonUnits) {
            Log::info('📚 Handling ELECTIVE/COMMON UNITS program - Grouping by Unit + Lecturer');

            // Get all unique unit+lecturer combinations with total student count
            $unitLecturerGroups = DB::table('enrollments')
                ->join('units', 'enrollments.unit_id', '=', 'units.id')
                ->where('units.program_id', $programId)
                ->where('enrollments.semester_id', $semesterId)
                ->where('enrollments.status', 'enrolled')
                ->select(
                    'units.id as unit_id',
                    'units.code as unit_code',
                    'units.name as unit_name',
                    'units.credit_hours',
                    'enrollments.lecturer_code',
                    DB::raw('COUNT(DISTINCT enrollments.student_code) as total_students'),
                    DB::raw('COUNT(DISTINCT enrollments.group_id) as sections_count'),
                    DB::raw('MIN(enrollments.group_id) as first_class_id') // ✅ Get first class_id as representative
                )
                ->groupBy(
                    'units.id',
                    'units.code',
                    'units.name',
                    'units.credit_hours',
                    'enrollments.lecturer_code'
                )
                ->having('total_students', '>', 0)
                ->orderBy('units.code')
                ->get();

            Log::info('Elective unit-lecturer groups found', [
                'groups_count' => $unitLecturerGroups->count(),
                'sample' => $unitLecturerGroups->take(3)->map(fn($g) => [
                    'unit' => $g->unit_code,
                    'lecturer' => $g->lecturer_code,
                    'total_students' => $g->total_students,
                    'sections' => $g->sections_count,
                    'representative_class' => $g->first_class_id
                ])->toArray()
            ]);

            // Format the response - ONE entry per unit-lecturer combination
            $formattedUnits = $unitLecturerGroups->map(function($group) use ($semesterId) {
                // Get lecturer name
                $lecturerName = 'Not Assigned';
                if ($group->lecturer_code) {
                    $lecturer = DB::table('users')
                        ->where('code', $group->lecturer_code)
                        ->first();
                    
                    if ($lecturer) {
                        $lecturerName = trim(($lecturer->first_name ?? '') . ' ' . ($lecturer->last_name ?? ''));
                    }
                }

                // Get list of sections (classes) taking this unit
                $sectionsInfo = DB::table('enrollments')
                    ->join('classes', 'enrollments.group_id', '=', 'classes.id')
                    ->where('enrollments.unit_id', $group->unit_id)
                    ->where('enrollments.semester_id', $semesterId)
                    ->where('enrollments.lecturer_code', $group->lecturer_code)
                    ->where('enrollments.status', 'enrolled')
                    ->select(
                        'classes.id as class_id',
                        'classes.name as class_name',
                        'classes.section as class_section',
                        DB::raw('COUNT(DISTINCT enrollments.student_code) as students_in_section')
                    )
                    ->groupBy('classes.id', 'classes.name', 'classes.section')
                    ->get();

                $sectionsList = $sectionsInfo->map(function($sec) {
                    return $sec->class_name . 
                           ($sec->class_section ? " (Sec {$sec->class_section})" : '') . 
                           " [{$sec->students_in_section} students]";
                })->toArray();

                return [
                    'id' => $group->unit_id,
                    'code' => $group->unit_code,
                    'name' => $group->unit_name,
                    'credit_hours' => $group->credit_hours ?? 3,
                    
                    // ✅ CRITICAL FIX: Use first class_id as representative
                    'class_id' => (int)$group->first_class_id,
                    'class_name' => 'All Sections Combined',
                    'class_section' => 'ELECTIVE',
                    
                    // ✅ Total student count across ALL sections
                    'student_count' => (int)$group->total_students,
                    
                    // Lecturer info
                    'lecturer_name' => $lecturerName,
                    'lecturer_code' => $group->lecturer_code ?? '',
                    
                    // ✅ Show which sections are included
                    'sections_included' => $sectionsInfo->count(),
                    'sections_list' => $sectionsList,
                    'sections_details' => $sectionsInfo->toArray(), // ✅ Full details for backend
                    
                    // ✅ Display label for UI
                    'display_label' => sprintf(
                        '%s - %s | %s | %d students from %d section%s',
                        $group->unit_code,
                        $group->unit_name,
                        $lecturerName,
                        $group->total_students,
                        $sectionsInfo->count(),
                        $sectionsInfo->count() > 1 ? 's' : ''
                    ),
                    
                    // ✅ Flag this as elective
                    '_is_elective' => true
                ];
            })->values();

            return response()->json($formattedUnits->all());
        }

        // ✅ CASE 2: REGULAR PROGRAM - Rest remains same...
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
                '_is_elective' => false
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
    ->where('exam_timetables.program_id', $program->id)
    ->select([
        'exam_timetables.*',
        'units.name as unit_name',
        'units.code as unit_code',
        'classes.name as class_name',
        'classes.section as class_section',  // ✅ This is the one we need!
        DB::raw('COALESCE(classes.section, exam_timetables.group_name) as section_display'),  // ✅ Fallback logic
        DB::raw('COALESCE(REPLACE(classes.name, " ", "-"), CONCAT("CLASS-", classes.id)) as class_code'),
        'semesters.name as semester_name'
    ])
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
            'exam_timetables.program_id',
            'exam_timetables.school_id',
            'exam_timetables.created_at',
            'exam_timetables.updated_at',
            
            // ✅ ADD THESE TWO LINES - THIS IS THE FIX!
            'exam_timetables.group_name',  // Section from exam_timetables table
            'classes.section as class_section',  // Section from classes table (backup)
            
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
              ->orWhere('units.name', 'like', "%{$search}%")
              ->orWhere('exam_timetables.venue', 'like', "%{$search}%")
              ->orWhere('exam_timetables.chief_invigilator', 'like', "%{$search}%");
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
        'venue' => 'required|string|exists:examrooms,name',  // ✅ User selects venue
        'location' => 'nullable|string|max:255',
        'chief_invigilator' => 'required|string|max:255',
        'no' => 'required|integer|min:1',
    ]);

    try {
        // ✅ Get ALL sections for this unit
        $sectionsWithStudents = DB::table('enrollments')
            ->join('classes', 'enrollments.group_id', '=', 'classes.id')
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

        // ✅ USE THE USER-SELECTED VENUE (from validation)
        $selectedVenue = $validatedData['venue'];
        $selectedLocation = $validatedData['location'] ?? 'Main Campus';

        // ✅ CHECK: Does the venue have enough capacity for ALL sections combined?
        $totalStudents = $sectionsWithStudents->sum('student_count');
        $venueCapacity = DB::table('examrooms')
            ->where('name', $selectedVenue)
            ->value('capacity');

        if ($venueCapacity < $totalStudents) {
            return redirect()->back()
                ->withErrors([
                    'venue' => "Selected venue {$selectedVenue} (capacity: {$venueCapacity}) cannot accommodate {$totalStudents} students across all sections."
                ])
                ->withInput();
        }

        // ✅ Schedule EACH section separately with SAME venue
        $scheduledExams = [];
        $failedSections = [];

        foreach ($sectionsWithStudents as $section) {
            $fullClassName = trim($section->class_name . ' ' . ($section->class_section ?? ''));
            
            \Log::info("📝 Scheduling section: {$fullClassName}", [
                'class_id' => $section->class_id,
                'students' => $section->student_count
            ]);

            // ✅ Create exam for this section using the SELECTED venue
            $examData = [
                'unit_id' => $validatedData['unit_id'],
                'semester_id' => $validatedData['semester_id'],
                'class_id' => $section->class_id,
                'program_id' => $program->id,
                'school_id' => $program->school_id,
                'group_name' => $section->class_section ?? 'N/A',  // ✅ Section info
                'date' => $validatedData['date'],
                'day' => $validatedData['day'],
                'start_time' => $validatedData['start_time'],
                'end_time' => $validatedData['end_time'],
                'venue' => $selectedVenue,  // ✅ Use selected venue
                'location' => $selectedLocation,
                'no' => $section->student_count,
                'chief_invigilator' => $validatedData['chief_invigilator'],
            ];

            $createdExam = ExamTimetable::create($examData);
            
            $scheduledExams[] = [
                'exam_id' => $createdExam->id,
                'class' => $fullClassName,
                'students' => $section->student_count,
                'venue' => $selectedVenue,
            ];

            \Log::info('✅ Exam scheduled', [
                'class' => $fullClassName,
                'students' => $section->student_count,
                'venue' => $selectedVenue
            ]);
        }

        if (!empty($scheduledExams)) {
            $message = "✅ Scheduled " . count($scheduledExams) . " section(s) in {$selectedVenue}:\n\n";
            
            foreach ($scheduledExams as $exam) {
                $message .= "• {$exam['class']}: {$exam['students']} students\n";
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
 * ✅ NEW: Group sections that share the same unit and lecturer
 */
private function groupSectionsByUnitAndLecturer($selections)
{
    $groups = [];

    foreach ($selections as $selection) {
        $unitCode = $selection['unit_code'];
        $lecturer = $selection['lecturer'] ?? 'NO_LECTURER';
        
        // Create unique key for unit + lecturer combination
        $groupKey = $unitCode . '___' . $lecturer;

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'unit_code' => $unitCode,
                'unit_name' => $selection['unit_name'],
                'unit_id' => $selection['unit_id'],
                'lecturer' => $lecturer === 'NO_LECTURER' ? null : $lecturer,
                'sections' => [],
                'total_students' => 0,
            ];
        }

        $groups[$groupKey]['sections'][] = $selection;
        $groups[$groupKey]['total_students'] += $selection['student_count'];
    }

    return $groups;
}


/**
 * ✅ FIXED: Bulk schedule exams with proper elective handling and lecturer assignment
 */
public function bulkScheduleExams(Request $request, Program $program, $schoolCode)
{
    try {
        $validated = $request->validate([
            'semester_id' => 'required|integer',
            'school_id' => 'required|integer',
            'program_id' => 'required|integer',
            'selected_class_units' => 'required|array|min:1',
            'selected_class_units.*.class_id' => 'nullable|integer',
            'selected_class_units.*.unit_id' => 'required|integer',
            'selected_class_units.*.student_count' => 'required|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'exam_duration_hours' => 'required|integer|min:1|max:8',
            'start_time' => 'required',
            'excluded_days' => 'array',
            'max_exams_per_day' => 'required|integer|min:1',
            'selected_examrooms' => 'required|array|min:1',
            'break_minutes' => 'required|integer|min:15',
        ]);

        Log::info('🎓 Starting INTELLIGENT bulk exam scheduling', [
            'total_selections' => count($validated['selected_class_units']),
            'date_range' => $validated['start_date'] . ' to ' . $validated['end_date'],
        ]);

        $scheduled = [];
        $conflicts = [];
        
        $selectedRooms = DB::table('examrooms')
            ->whereIn('id', $validated['selected_examrooms'])
            ->get();

             // ✅ Generate time slots for random selection
            $timeSlots = $this->generateTimeSlots(
                $validated['start_time'],
                $validated['exam_duration_hours'],
                $validated['break_minutes'],
                4 // Generate 4 time slots
            );

            Log::info('🕐 Time slots generated for random assignment', [
                'total_slots' => count($timeSlots),
                'slots' => array_map(function($slot) {
                    return [
                        'slot' => $slot['slot_number'],
                        'time' => $slot['start_time'] . ' - ' . $slot['end_time']
                    ];
                }, $timeSlots)
            ]);

        // ✅ STEP 1: Enrich ALL selections with complete lecturer data
        $validSelections = collect($validated['selected_class_units'])
            ->map(function($selection) use ($validated) {
                // ✅ CRITICAL: Get lecturer from unit_assignments table (this is where ALL lecturers are stored)
                $lecturerCode = null;
                
                // Method 1: Try with both unit_id + class_id (for regular classes)
                if (isset($selection['class_id']) && $selection['class_id']) {
                    $lecturerCode = DB::table('unit_assignments')
                        ->where('unit_id', $selection['unit_id'])
                        ->where('semester_id', $validated['semester_id'])
                        ->where('class_id', $selection['class_id'])
                        ->value('lecturer_code');
                    
                    Log::debug('Lecturer lookup attempt 1 (unit_assignments with class_id)', [
                        'unit_id' => $selection['unit_id'],
                        'class_id' => $selection['class_id'],
                        'semester_id' => $validated['semester_id'],
                        'found' => $lecturerCode ? 'YES' : 'NO',
                        'lecturer_code' => $lecturerCode
                    ]);
                }
                
                // Method 2: Try with just unit_id + semester_id (for electives or when class_id match fails)
                if (!$lecturerCode) {
                    $lecturerCode = DB::table('unit_assignments')
                        ->where('unit_id', $selection['unit_id'])
                        ->where('semester_id', $validated['semester_id'])
                        ->value('lecturer_code');
                    
                    Log::debug('Lecturer lookup attempt 2 (unit_assignments without class_id)', [
                        'unit_id' => $selection['unit_id'],
                        'semester_id' => $validated['semester_id'],
                        'found' => $lecturerCode ? 'YES' : 'NO',
                        'lecturer_code' => $lecturerCode
                    ]);
                }

                // Get lecturer full name
                $lecturerName = 'Not Assigned';
                
                if ($lecturerCode) {
                    $lecturer = DB::table('users')
                        ->where('code', $lecturerCode)
                        ->first();
                    
                    if ($lecturer) {
                        $lecturerName = trim(($lecturer->first_name ?? '') . ' ' . ($lecturer->last_name ?? ''));
                    } else {
                        Log::warning('Lecturer code found but user not found', [
                            'lecturer_code' => $lecturerCode,
                            'unit_id' => $selection['unit_id']
                        ]);
                    }
                }
                
                // Get unit info
                $unit = DB::table('units')->where('id', $selection['unit_id'])->first();
                
                // Log final result
                Log::info('✅ Lecturer assignment resolved', [
                    'unit_id' => $selection['unit_id'],
                    'unit_code' => $unit->code ?? 'UNKNOWN',
                    'class_id' => $selection['class_id'] ?? 'null',
                    'lecturer_code' => $lecturerCode ?? 'NOT FOUND',
                    'lecturer_name' => $lecturerName,
                ]);
                
                // Determine if this is an elective
                $isElective = !isset($selection['class_id']) || $selection['class_id'] == 0 || $selection['class_id'] === null;

                // ✅ For electives, get the first actual class_id from enrollments
                $actualClassId = null; // ✅ Default to NULL
                
                if (!$isElective && $selection['class_id']) {
                    // Regular class - use provided class_id
                    $actualClassId = $selection['class_id'];
                } else {
                    // Elective - try to get a representative class_id
                    $actualClassId = DB::table('enrollments')
                        ->where('unit_id', $selection['unit_id'])
                        ->where('semester_id', $validated['semester_id'])
                        ->where('status', 'enrolled')
                        ->value('group_id'); // or class_id depending on your FK
                    
                    // ✅ CRITICAL: If still no class found, keep as NULL
                    $actualClassId = $actualClassId ?: null;
                }

                // Get unit info
                $unit = DB::table('units')->where('id', $selection['unit_id'])->first();
                
                // Get class info (if available)
                $class = null;
                if ($actualClassId) {
                    $class = DB::table('classes')->where('id', $actualClassId)->first();
                }

                return [
                    'unit_id' => $selection['unit_id'],
                    'unit_code' => $unit->code ?? 'UNKNOWN',
                    'unit_name' => $unit->name ?? 'Unknown Unit',
                    'class_id' => $actualClassId ?: null, // ✅ ENSURE NULL not empty string
                    'class_name' => $isElective ? 'Electives' : ($class->name ?? 'Unknown'),
                    'class_code' => $isElective ? 'ELECTIVE' : ($class->name ? str_replace(' ', '-', $class->name) : 'CLASS-' . $actualClassId),
                    'class_section' => $isElective ? 'ELECTIVE' : ($class->section ?? 'N/A'),
                    'student_count' => $selection['student_count'],
                    'lecturer_code' => $lecturerCode ?? 'N/A', // ✅ Now properly set
                    'lecturer' => $lecturerName, // ✅ Now properly set
                    '_is_elective' => $isElective,
                ];
            });

        Log::info('📊 Enriched selections', [
            'total' => $validSelections->count(),
            'sample_lecturer_info' => $validSelections->take(3)->map(fn($s) => [
                'unit' => $s['unit_code'],
                'lecturer' => $s['lecturer'],
                'lecturer_code' => $s['lecturer_code'],
                'is_elective' => $s['_is_elective']
            ])->toArray()
        ]);

        // Separate electives from regular classes
        $regularExams = $validSelections->filter(fn($s) => !$s['_is_elective']);
        $electiveExams = $validSelections->filter(fn($s) => $s['_is_elective']);

        Log::info('📊 Exam type breakdown', [
            'regular_exams' => $regularExams->count(),
            'elective_exams' => $electiveExams->count(),
        ]);

        // ✅ STEP 2: Analyze workload ONLY for regular classes
        $classWorkloads = [];
        if ($regularExams->isNotEmpty()) {
            $classWorkloads = $this->analyzeClassWorkloads(
                $regularExams->toArray(),
                $validated['semester_id']
            );
        }

        // ✅ STEP 3: Generate available dates
        $availableDates = $this->generateAvailableDates(
            $validated['start_date'],
            $validated['end_date'],
            $validated['excluded_days'] ?? []
        );

        // ✅ STEP 4: Split dates into weeks
        $week1Dates = [];
        $week2Dates = [];
        
        $startCarbon = Carbon::parse($validated['start_date']);
        $week1End = $startCarbon->copy()->addDays(6);
        
        foreach ($availableDates as $date) {
            $dateCarbon = Carbon::parse($date);
            if ($dateCarbon->lte($week1End)) {
                $week1Dates[] = $date;
            } else {
                $week2Dates[] = $date;
            }
        }

        // ✅ STEP 5: Sort - regular exams first, then electives
        $sortedSelections = $regularExams
            ->sortBy([
                ['class_id', 'asc'],
                ['unit_id', 'asc']
            ])
            ->concat($electiveExams)
            ->values();

        // ✅ STEP 6: Initialize tracking
        $classScheduleTracking = [];
        foreach ($classWorkloads as $classId => $workload) {
            $classScheduleTracking[$classId] = [
                'policy' => $workload['policy'],
                'total_units' => $workload['total_units'],
                'pattern' => $workload['pattern'],
                'exams_scheduled' => 0,
                'scheduled_dates' => []
            ];
        }

        $venueCapacityUsage = [];
        $usedDates = [];

        // ✅ STEP 7: Schedule each exam
        foreach ($sortedSelections as $selection) {
            $studentCount = $selection['student_count'];
            $isElective = $selection['_is_elective'];

            Log::info('🎯 Processing exam', [
                'type' => $isElective ? 'ELECTIVE' : 'REGULAR',
                'class' => $selection['class_name'],
                'section' => $selection['class_section'] ?? 'N/A',
                'unit' => $selection['unit_code'],
                'students' => $studentCount,
                'lecturer' => $selection['lecturer'], // ✅ Now shows correctly
                'lecturer_code' => $selection['lecturer_code'], // ✅ Now shows correctly
            ]);

            // ✅ Date selection
            if ($isElective) {
                $targetDate = $this->selectDateForElective($availableDates, $usedDates);
            } else {
                $targetDate = $this->selectDateWithSpacing(
                    collect([$selection]),
                    $classScheduleTracking,
                    $week1Dates,
                    $week2Dates
                );
            }

            if (!$targetDate) {
                $conflicts[] = [
                    'unit' => $selection['unit_code'],
                    'class' => $selection['class_name'],
                    'section' => $selection['class_section'] ?? 'N/A',
                    'reason' => 'No available date'
                ];
                continue;
            }

            // ✅ CRITICAL FIX: Randomly select time slot for THIS exam
            $assignedSlot = $timeSlots[array_rand($timeSlots)];
            $startTime = $assignedSlot['start_time'];
            $endTime = $assignedSlot['end_time'];
            $timeSlotKey = "{$startTime}-{$endTime}";

            Log::info('🎲 Random time slot assigned', [
                'unit' => $selection['unit_code'],
                'slot_number' => $assignedSlot['slot_number'],
                'time' => $timeSlotKey,
                'date' => $targetDate
            ]);

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

            // ✅ Check conflicts (skip class conflict check for electives)
            if (!$isElective && $selection['class_id']) {
                $hasConflict = $this->checkSchedulingConflicts(
                    $validated['semester_id'],
                    $selection['class_id'],
                    $selection['unit_id'],
                    $targetDate,
                    $startTime,
                    $endTime,
                    $selection['lecturer_code']
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
            }

            // ✅ Create exam with CORRECT lecturer assignment
            $examData = [
                'semester_id' => $validated['semester_id'],
                'class_id' => $selection['class_id'] ?: null, // ✅ CRITICAL: NULL not empty string
                'unit_id' => $selection['unit_id'],
                'program_id' => $validated['program_id'],
                'school_id' => $validated['school_id'],
                'date' => $targetDate,
                'day' => Carbon::parse($targetDate)->format('l'),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'venue' => $venueResult['venue'],
                'location' => $venueResult['location'] ?? 'Phase 2',
                'group_name' => $selection['class_section'],
                'no' => $studentCount,
                'chief_invigilator' => $selection['lecturer'], // ✅ FIXED: Now shows actual lecturer name
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $examId = DB::table('exam_timetables')->insertGetId($examData);

            // ✅ Update tracking
            if (!$isElective && isset($classScheduleTracking[$selection['class_id']])) {
                $classScheduleTracking[$selection['class_id']]['scheduled_dates'][] = $targetDate;
                $classScheduleTracking[$selection['class_id']]['exams_scheduled']++;
            }

            $usedDates[] = $targetDate;

            Log::info('✅ Exam created', [
                'exam_id' => $examId,
                'type' => $isElective ? 'ELECTIVE' : 'REGULAR',
                'date' => $targetDate,
                'lecturer' => $selection['lecturer'], // ✅ Confirms correct lecturer
            ]);

            $scheduled[] = [
                'id' => $examId,
                'unit_code' => $selection['unit_code'],
                'unit_name' => $selection['unit_name'],
                'class_name' => $selection['class_name'],
                'class_section' => $selection['class_section'],
                'student_count' => $studentCount,
                'lecturer' => $selection['lecturer'], // ✅ Include in response
                'date' => $targetDate,
                'time' => "$startTime - $endTime",
                'venue' => $venueResult['venue']
            ];

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
            'message' => count($scheduled) . ' exams scheduled successfully',
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
 * ✅ Helper: Select date for elective (simple round-robin)
 */
private function selectDateForElective(array $availableDates, array $usedDates): ?string
{
    $dateCounts = array_count_values($usedDates);
    
    $leastUsed = null;
    $minCount = PHP_INT_MAX;
    
    foreach ($availableDates as $date) {
        $count = $dateCounts[$date] ?? 0;
        if ($count < $minCount) {
            $minCount = $count;
            $leastUsed = $date;
        }
    }
    
    return $leastUsed;
}

/**
 * ✅ INTELLIGENT: Analyze workload and create optimal scheduling patterns
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
        
        // ✅ INTELLIGENT PATTERN SELECTION based on workload
        $pattern = $this->selectOptimalPattern($totalUnits);
        
        $classWorkloads[$classId] = [
            'class_id' => $classId,
            'class_name' => $firstSelection['class_name'],
            'class_section' => $firstSelection['class_section'] ?? 'N/A',
            'total_units' => $totalUnits,
            'policy' => 'PATTERN_BASED',
            'pattern' => $pattern,
        ];
    }
    
    return $classWorkloads;
}

/**
 * ✅ FIXED: Select optimal exam distribution pattern based on workload
 */
private function selectOptimalPattern($totalUnits)
{
    // Patterns are day-of-week based: 0=Mon, 1=Tue, 2=Wed, 3=Thu, 4=Fri
    
    if ($totalUnits >= 10) {
        return [
            'week1' => [0, 1, 3, 4],      // Mon, Tue, Thu, Fri
            'week2' => [0, 2, 4, 0],      // Mon, Wed, Fri, Mon
            'name' => 'VERY_HEAVY'
        ];
    } elseif ($totalUnits >= 7) {
        return [
            'week1' => [0, 1, 3, 4],      // Mon, Tue, Thu, Fri (4 exams)
            'week2' => [0, 2, 4],         // Mon, Wed, Fri (3 exams)
            'name' => 'HEAVY'
        ];
    } elseif ($totalUnits >= 5) {
        return [
            'week1' => [0, 2, 4],         // Mon, Wed, Fri (3 exams)
            'week2' => [0, 2, 4],         // Mon, Wed, Fri (3 exams)
            'name' => 'MODERATE'
        ];
    } elseif ($totalUnits >= 3) {
        return [
            'week1' => [0, 3],            // Mon, Thu (2 exams)
            'week2' => [1, 4],            // Tue, Fri (2 exams)
            'name' => 'LIGHT'
        ];
    } else {
        return [
            'week1' => [0],               // Mon (1 exam)
            'week2' => [3],               // Thu (1 exam)
            'name' => 'MINIMAL'
        ];
    }
}

/**
 * ✅ FIXED: Pattern-based date selection with proper week boundary handling
 */
private function selectDateWithSpacing(
    $sections,
    &$classScheduleTracking,
    $week1Dates,
    $week2Dates
) {
    \Log::info('📅 Pattern-based date selection', [
        'week1_dates' => $week1Dates,
        'week2_dates' => $week2Dates
    ]);
    
    foreach ($sections as $section) {
        $classId = $section['class_id'];
        
        if (!isset($classScheduleTracking[$classId])) {
            \Log::warning("⚠️ No tracking for class {$classId}");
            continue;
        }
        
        $tracking = $classScheduleTracking[$classId];
        $pattern = $tracking['pattern'];
        $examsScheduled = $tracking['exams_scheduled'];
        
        \Log::info("📋 Class pattern info", [
            'class' => $section['class_name'],
            'pattern_name' => $pattern['name'],
            'exams_scheduled' => $examsScheduled,
            'total_units' => $tracking['total_units'],
            'week1_pattern' => array_map([$this, 'getDayName'], $pattern['week1']),
            'week2_pattern' => array_map([$this, 'getDayName'], $pattern['week2'])
        ]);
        
        // ✅ FIX: Determine which week and which slot within that week
        $week1Count = count($pattern['week1']);
        
        if ($examsScheduled < $week1Count) {
            // We're still in Week 1
            $weekPattern = $pattern['week1'];
            $patternIndex = $examsScheduled;
            $targetDayOfWeek = $weekPattern[$patternIndex];
            $searchDates = $week1Dates;
            $weekName = 'Week 1';
        } else {
            // We're in Week 2
            $week2Index = $examsScheduled - $week1Count;
            $weekPattern = $pattern['week2'];
            
            // Handle pattern cycling
            if ($week2Index >= count($weekPattern)) {
                $week2Index = $week2Index % count($weekPattern);
                \Log::info("🔄 Pattern cycling", ['week2_index' => $week2Index]);
            }
            
            $patternIndex = $week2Index;
            $targetDayOfWeek = $weekPattern[$patternIndex];
            $searchDates = $week2Dates;
            $weekName = 'Week 2';
        }
        
        \Log::info("🎯 Searching for target day", [
            'week' => $weekName,
            'pattern_index' => $patternIndex,
            'target_day_of_week' => $this->getDayName($targetDayOfWeek),
            'exams_already_scheduled' => $examsScheduled
        ]);
        
        // Find the date matching the target day
        foreach ($searchDates as $candidateDate) {
            $candidateCarbon = Carbon::parse($candidateDate);
            $candidateDayOfWeek = $candidateCarbon->dayOfWeekIso - 1;
            
            if ($candidateDayOfWeek === $targetDayOfWeek && 
                !in_array($candidateDate, $tracking['scheduled_dates'])) {
                
                \Log::info("✅ Found matching date", [
                    'date' => $candidateDate,
                    'day' => $candidateCarbon->format('l')
                ]);
                
                return $candidateDate;
            }
        }
        
        // Fallback: find ANY available date
        \Log::warning("⚠️ Pattern slot occupied, searching for fallback");
        
        foreach ($searchDates as $candidateDate) {
            if (!in_array($candidateDate, $tracking['scheduled_dates'])) {
                \Log::info("✅ Found fallback date", ['date' => $candidateDate]);
                return $candidateDate;
            }
        }
        
        // Try opposite week
        $oppositeDates = ($weekName === 'Week 1') ? $week2Dates : $week1Dates;
        foreach ($oppositeDates as $candidateDate) {
            if (!in_array($candidateDate, $tracking['scheduled_dates'])) {
                \Log::info("✅ Found fallback in opposite week", ['date' => $candidateDate]);
                return $candidateDate;
            }
        }
    }
    
    return null;
}

/**
 * ✅ Helper to convert day number to name
 */
private function getDayName($dayOfWeek)
{
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    return $days[$dayOfWeek] ?? 'Unknown';
}

/**
 * ✅ UPDATED: Verify venues can accommodate all students (used for validation only)
 */
private function findVenuesForGroup(
    $selectedRooms,
    $totalStudents,
    $date,
    $timeSlotKey,
    &$venueCapacityUsage
) {
    Log::info('🔍 Verifying venue capacity for group', [
        'total_students' => $totalStudents,
        'date' => $date,
        'time_slot' => $timeSlotKey
    ]);

    $availableVenues = [];
    
    foreach ($selectedRooms as $room) {
        $venueName = $room->name;
        $venueCapacity = $room->capacity;
        
        // Check current usage
        $usedCapacity = $venueCapacityUsage[$venueName][$date][$timeSlotKey] ?? 0;
        $availableCapacity = $venueCapacity - $usedCapacity;
        
        if ($availableCapacity > 0) {
            $availableVenues[] = [
                'id' => $room->id,
                'name' => $venueName,
                'location' => $room->location ?? 'Phase 2',
                'total_capacity' => $venueCapacity,
                'remaining_capacity' => $availableCapacity,
            ];
        }
    }
    
    if (empty($availableVenues)) {
        return [
            'success' => false,
            'message' => "No venues available at {$timeSlotKey} on {$date}",
        ];
    }
    
    // ✅ Just return available venues for later allocation
    return [
        'success' => true,
        'venues' => $availableVenues,
        'total_venues_available' => count($availableVenues),
    ];
}

private function selectDateWithSpacingForGroup(
    $sections,
    &$classScheduleTracking,
    $week1Dates,
    $week2Dates
) {
    $allDates = array_merge($week1Dates, $week2Dates);
    
    // Get all class IDs in this group
    $classIds = array_unique(array_column($sections, 'class_id'));
    
    // Try each date until we find one that works for ALL classes
    foreach ($allDates as $candidateDate) {
        $dateWorksForAll = true;
        
        foreach ($classIds as $classId) {
            if (!isset($classScheduleTracking[$classId])) {
                continue; // Class not tracked yet, date is OK
            }
            
            $scheduledDates = $classScheduleTracking[$classId]['scheduled_dates'];
            
            // Check if this class already has an exam on this date
            if (in_array($candidateDate, $scheduledDates)) {
                $dateWorksForAll = false;
                Log::debug("❌ Date {$candidateDate} already used by class {$classId}");
                break;
            }
            
            // Check minimum spacing (at least 1 day between exams)
            if (!empty($scheduledDates)) {
                $lastDate = Carbon::parse(end($scheduledDates));
                $currentDate = Carbon::parse($candidateDate);
                $daysBetween = $lastDate->diffInDays($currentDate);
                
                if ($daysBetween < 1) {
                    $dateWorksForAll = false;
                    Log::debug("❌ Date {$candidateDate} too close to last exam for class {$classId}");
                    break;
                }
            }
        }
        
        if ($dateWorksForAll) {
            Log::info("✅ Selected date: {$candidateDate} for group");
            return $candidateDate;
        }
    }
    
    Log::warning("⚠️ No suitable date found for group");
    return null;
}


/**
 * ✅ FIXED: Log scheduling failure to database with correct field mapping
 */
private function logSchedulingFailure(
    array $validated,
    array $section,
    string $reason,
    ?string $attemptedDate,
    ?string $attemptedTime
) {
    try {
        // ✅ Use batch_id from validated data (already set in bulkScheduleExams)
        $batchId = $validated['batch_id'];
        
        // Parse time slot if provided
        $startTime = null;
        $endTime = null;
        if ($attemptedTime) {
            $timeParts = explode('-', $attemptedTime);
            $startTime = trim($timeParts[0] ?? null);
            $endTime = trim($timeParts[1] ?? null);
        }

        // ✅ Prepare class_names as TEXT (comma-separated for display)
        $classNames = $section['class_name'] ?? 'Unknown Class';
        if (isset($section['class_section']) && $section['class_section']) {
            $classNames .= ' (Section: ' . $section['class_section'] . ')';
        }

        // ✅ Create the record with correct field names
        FailedExamSchedule::create([
            // ✅ REQUIRED (NOT NULL in DB)
            'batch_id'      => $batchId,
            'semester_id'   => $validated['semester_id'],
            'unit_id'       => $section['unit_id'],
            'unit_code'     => $section['unit_code'] ?? 'UNKNOWN',
            'unit_name'     => $section['unit_name'] ?? 'Unknown Unit',
            'class_ids'     => json_encode([$section['class_id']]), // ✅ JSON array
            'class_names'   => $classNames,                          // ✅ TEXT field
            'student_count' => $section['student_count'] ?? 0,

            // ✅ OPTIONAL (nullable in DB)
            'program_id'    => $validated['program_id'] ?? null,
            'school_id'     => $validated['school_id'] ?? null,

            // ✅ ATTEMPTED SLOT (separate time fields)
            'attempted_date'        => $attemptedDate,
            'attempted_start_time'  => $startTime,
            'attempted_end_time'    => $endTime,

            // ✅ FAILURE DETAILS
            'failure_reason' => $reason,  // ✅ Singular, not plural
            'status'         => 'pending',

            // ✅ TIMESTAMPS
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('📝 Successfully logged scheduling failure', [
            'batch_id' => $batchId,
            'unit' => $section['unit_code'] ?? 'UNKNOWN',
            'class' => $section['class_name'] ?? 'Unknown',
            'reason' => $reason
        ]);

    } catch (\Exception $e) {
        Log::error('❌ Failed to log scheduling failure to database', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'section' => $section,
            'reason' => $reason,
            'batch_id' => $validated['batch_id'] ?? 'MISSING'
        ]);
    }
}
/**
 * Generate time slots for exam scheduling
 */
private function generateTimeSlots($startTime, $examDurationHours, $breakMinutes, $maxSlots = 4)
{
    $slots = [];
    
    // Parse start time (handle both HH:MM and HH:MM:SS formats)
    $startTimeClean = substr($startTime, 0, 5);
    list($hours, $minutes) = explode(':', $startTimeClean);
    $currentMinutes = ($hours * 60) + $minutes;
    
    for ($i = 0; $i < $maxSlots; $i++) {
        // Calculate start time for this slot
        $startH = floor($currentMinutes / 60);
        $startM = $currentMinutes % 60;
        $start = sprintf('%02d:%02d', $startH, $startM);
        
        // Calculate end time (start + exam duration)
        $endMinutes = $currentMinutes + ($examDurationHours * 60);
        $endH = floor($endMinutes / 60);
        $endM = $endMinutes % 60;
        $end = sprintf('%02d:%02d', $endH, $endM);
        
        $slots[] = [
            'slot_number' => $i + 1,
            'start_time' => $start,
            'end_time' => $end
        ];
        
        // Move to next slot (end time + break)
        $currentMinutes = $endMinutes + $breakMinutes;
    }
    
    return $slots;
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
        $examrooms = Examroom::where('is_active', true)
            ->orderBy('capacity', 'asc')
            ->get();
        
        if ($examrooms->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No exam rooms configured in the system'
            ];
        }

        // ✅ FIX: Handle both 12-hour (with AM/PM) and 24-hour formats
        $startTimeClean = trim($startTime);
        $endTimeClean = trim($endTime);
        
        // Check if time contains AM/PM
        if (stripos($startTimeClean, 'AM') !== false || stripos($startTimeClean, 'PM') !== false) {
            // Parse 12-hour format with AM/PM
            $examStartTime = Carbon::createFromFormat('Y-m-d h:i A', $date . ' ' . $startTimeClean);
            $examEndTime = Carbon::createFromFormat('Y-m-d h:i A', $date . ' ' . $endTimeClean);
        } else {
            // Parse 24-hour format (existing logic)
            $startTimeClean = substr($startTime, 0, 5);
            $endTimeClean = substr($endTime, 0, 5);
            $examStartTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $startTimeClean);
            $examEndTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $endTimeClean);
        }

        \Log::info('🕐 Parsed times', [
            'original_start' => $startTime,
            'original_end' => $endTime,
            'exam_start' => $examStartTime->format('Y-m-d H:i:s'),
            'exam_end' => $examEndTime->format('Y-m-d H:i:s')
        ]);

        $suitableVenues = [];
        
        foreach ($examrooms as $room) {
            if ($room->capacity < $studentCount) {
                \Log::debug("  ⛔ {$room->name} too small", [
                    'capacity' => $room->capacity,
                    'required' => $studentCount
                ]);
                continue;
            }

            $existingExamsQuery = ExamTimetable::where('venue', $room->name)
                ->where('date', $date);
            
            if ($excludeExamId) {
                $existingExamsQuery->where('id', '!=', $excludeExamId);
            }
            
            $existingExams = $existingExamsQuery->get();

            $occupiedCapacity = 0;
            foreach ($existingExams as $exam) {
                // ✅ FIX: Handle AM/PM format in existing exams too
                $examStartStr = trim($exam->start_time);
                $examEndStr = trim($exam->end_time);
                
                if (stripos($examStartStr, 'AM') !== false || stripos($examStartStr, 'PM') !== false) {
                    $existingStart = Carbon::createFromFormat('Y-m-d h:i A', $exam->date . ' ' . $examStartStr);
                    $existingEnd = Carbon::createFromFormat('Y-m-d h:i A', $exam->date . ' ' . $examEndStr);
                } else {
                    $examStartClean = substr($examStartStr, 0, 5);
                    $examEndClean = substr($examEndStr, 0, 5);
                    $existingStart = Carbon::createFromFormat('Y-m-d H:i', $exam->date . ' ' . $examStartClean);
                    $existingEnd = Carbon::createFromFormat('Y-m-d H:i', $exam->date . ' ' . $examEndClean);
                }

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

        if (empty($suitableVenues)) {
            $largestRoom = $examrooms->sortByDesc('capacity')->first();
            
            return [
                'success' => false,
                'message' => "❌ No single exam room available with capacity for {$studentCount} students.\n\n" .
                        "Largest room: {$largestRoom->name} ({$largestRoom->capacity} seats).\n\n" .
                        "💡 Suggestions:\n" .
                        "• Choose a different time slot\n" .
                        "• Select a different date",
                'required_capacity' => $studentCount
            ];
        }

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
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
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
                'classes.name as class_name',
                'exam_timetables.group_name',  // ✅ ADD THIS
                'exam_timetables.no'            // ✅ ADD THIS
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

        return $pdf->download('examtimetables.pdf');
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
    // ✅ CRITICAL: Select ALL columns needed for PDF
    $examTimetables = ExamTimetable::query()
        ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
        ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
        ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
        ->where('units.program_id', $program->id)
        ->select(
            // ✅ Exam timetable core fields
            'exam_timetables.id',
            'exam_timetables.date',
            'exam_timetables.day',
            'exam_timetables.start_time',
            'exam_timetables.end_time',
            'exam_timetables.venue',
            'exam_timetables.location',
            'exam_timetables.no',                    // ✅ Student count
            'exam_timetables.chief_invigilator',
            
            // ✅ CRITICAL: Section data
            'exam_timetables.group_name',            // ✅ Section from exam_timetables
            'classes.section as class_section',      // ✅ Section from classes (backup)
            
            // ✅ Other joined data
            'units.name as unit_name',
            'units.code as unit_code',
            'classes.name as class_name',
            DB::raw('COALESCE(REPLACE(classes.name, " ", "-"), CONCAT("CLASS-", classes.id)) as class_code'),
            'semesters.name as semester_name'
        )
        ->orderBy('exam_timetables.date')
        ->orderBy('exam_timetables.start_time')
        ->get();

    // ✅ DEBUG: Log data being sent to PDF
    \Log::info('PDF Generation Data Check:', [
        'total_exams' => $examTimetables->count(),
        'first_exam' => $examTimetables->first() ? [
            'id' => $examTimetables->first()->id,
            'unit_code' => $examTimetables->first()->unit_code,
            'class_name' => $examTimetables->first()->class_name,
            'group_name' => $examTimetables->first()->group_name,
            'class_section' => $examTimetables->first()->class_section,
            'no' => $examTimetables->first()->no,
        ] : 'No exams'
    ]);

    // ✅ Convert logo to base64
    $logoPath = public_path('images/strathmore.png');
    $logoBase64 = '';
    if (file_exists($logoPath)) {
        $logoData = base64_encode(file_get_contents($logoPath));
        $logoBase64 = 'data:image/png;base64,' . $logoData;
    }

    $pdf = PDF::loadView('examtimetables.pdf', [
        'examTimetables' => $examTimetables,
        'title' => $program->name . ' - Exam Timetable',
        'generatedAt' => now()->format('F d, Y H:i:s'),
        'logoBase64' => $logoBase64,
    ]);

    $pdf->setPaper('a4', 'landscape'); // ✅ Landscape for more columns

    return $pdf->download('exam-timetable-' . $program->code . '-' . now()->format('Y-m-d') . '.pdf');
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

// donwload PDF by the Exam office from his Dashboard
public function downloadAllPDF()
{
    \Log::info('=== downloadAllPDF called ===');
    
    // ✅ FIXED QUERY - Add group_name and no columns
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
            'exam_timetables.no',                    // ✅ ADD THIS - Student count
            'exam_timetables.chief_invigilator',
            'exam_timetables.group_name',            // ✅ ADD THIS - Section name
            'classes.section as class_section',      // ✅ Backup from classes table
            'units.name as unit_name',
            'units.code as unit_code',
            'classes.name as class_name',
            DB::raw('COALESCE(REPLACE(classes.name, " ", "-"), CONCAT("CLASS-", classes.id)) as class_code'),
            'semesters.name as semester_name'
        )
        ->orderBy('exam_timetables.date')
        ->orderBy('exam_timetables.start_time')
        ->get();

    // ✅ ADD DEBUG LOG
    \Log::info('downloadAllPDF - Data Check:', [
        'total_exams' => $examTimetables->count(),
        'first_exam' => $examTimetables->first() ? [
            'group_name' => $examTimetables->first()->group_name,
            'no' => $examTimetables->first()->no,
            'class_section' => $examTimetables->first()->class_section,
            'unit_code' => $examTimetables->first()->unit_code
        ] : 'No exams found'
    ]);

    // ✅ Pass correct variables to the blade template
    $pdf = PDF::loadView('examtimetables.pdf', [
        'examTimetables' => $examTimetables,
        'title' => 'University-Wide Exam Schedule',
        'generatedAt' => now()->format('F d, Y g:i A'),  // ✅ Match blade variable name
        'logoBase64' => null  // ✅ Optional: Add logo if you have one
    ])->setPaper('a4', 'landscape');

    return $pdf->download('university_exam_timetable_' . now()->format('Y_m_d') . '.pdf');
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
 * ✅ UPDATED: Check for scheduling conflicts
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
public function downloadExcel()
{
    try {
        Log::info('📊 Starting Excel export');

        // Fetch all exam timetables with relationships
        $examTimetables = DB::table('exam_timetables')
            ->join('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id') // ✅ leftJoin for electives
            ->join('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
            ->join('programs', 'exam_timetables.program_id', '=', 'programs.id')
            ->join('schools', 'exam_timetables.school_id', '=', 'schools.id')
            ->select(
                'exam_timetables.*',
                'units.code as unit_code',
                'units.name as unit_name',
                'classes.name as class_name',
                'classes.section as class_section',
                'semesters.name as semester_name',
                'programs.name as program_name',
                'programs.code as program_code',
                'schools.name as school_name',
                'schools.code as school_code'
            )
            ->orderBy('exam_timetables.date')
            ->orderBy('exam_timetables.start_time')
            ->get();

        Log::info('📊 Exam timetables fetched', [
            'count' => $examTimetables->count(),
            'sample' => $examTimetables->first()
        ]);

        if ($examTimetables->isEmpty()) {
            Log::warning('⚠️ No exam timetables found for export');
            return back()->with('error', 'No exam timetables available to export');
        }

        // Create new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Exam Timetables');

        // Set header row
        $headers = [
            'ID',
            'Date',
            'Day',
            'Start Time',
            'End Time',
            'Unit Code',
            'Unit Name',
            'Class',
            'Section',
            'Students',
            'Venue',
            'Location',
            'Chief Invigilator',           
        ];

        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . '1', $header);
            $column++;
        }

        // Style header row
        $sheet->getStyle('A1:P1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Set row height for header
        $sheet->getRowDimension('1')->setRowHeight(25);

        // Add data rows
        $row = 2;
        foreach ($examTimetables as $exam) {
            Log::debug('Adding row', ['row' => $row, 'unit' => $exam->unit_code]);

            $sheet->setCellValue('A' . $row, $exam->id);
            $sheet->setCellValue('B' . $row, $exam->date);
            $sheet->setCellValue('C' . $row, $exam->day);
            $sheet->setCellValue('D' . $row, substr($exam->start_time, 0, 5));
            $sheet->setCellValue('E' . $row, substr($exam->end_time, 0, 5));
            $sheet->setCellValue('F' . $row, $exam->unit_code);
            $sheet->setCellValue('G' . $row, $exam->unit_name);
            
            // ✅ Handle electives - use group_name from exam_timetables table
            $className = $exam->class_name ?? 'Electives';
            $classSection = $exam->class_section ?? $exam->group_name ?? 'ELECTIVE';
            
            $sheet->setCellValue('H' . $row, $className);
            $sheet->setCellValue('I' . $row, $classSection);
            $sheet->setCellValue('J' . $row, $exam->no);
            $sheet->setCellValue('K' . $row, $exam->venue);
            $sheet->setCellValue('L' . $row, $exam->location ?? '');
            $sheet->setCellValue('M' . $row, $exam->chief_invigilator);
           

            // Apply borders to data rows
            $sheet->getStyle('A' . $row . ':P' . $row)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ]);

            // Alternate row colors
            if ($row % 2 == 0) {
                $sheet->getStyle('A' . $row . ':P' . $row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F2F2F2']
                    ]
                ]);
            }

            $row++;
        }

        Log::info('✅ Excel data rows added', ['total_rows' => $row - 2]);

        // Auto-size columns
        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Freeze header row
        $sheet->freezePane('A2');

        // Create filename
        $filename = 'University_Exam_Timetables_' . now()->format('Y-m-d_His') . '.xlsx';

        Log::info('💾 Generating Excel file', ['filename' => $filename]);

        // Create writer and save to temporary file first (better for debugging)
        $writer = new Xlsx($spreadsheet);
        
        // Output directly to browser
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        header('Cache-Control: max-age=1');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
        
        $writer->save('php://output');
        
        Log::info('✅ Excel file generated and sent');
        
        exit;

    } catch (\Exception $e) {
        Log::error('❌ Excel export failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return back()->with('error', 'Failed to generate Excel file: ' . $e->getMessage());
    }
}


}