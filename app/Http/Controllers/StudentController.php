<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Unit;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\UnitAssignment;
use App\Models\School;
use App\Models\Program;
use Inertia\Inertia;

class StudentController extends Controller
{
    /**
     * Show available units for student enrollment (MAIN ENROLLMENT PAGE)
     */
    public function showAvailableUnits(Request $request)
    {
        $student = auth()->user();
        
        // Get current active semester
        $currentSemester = Semester::where('is_active', true)->first();
        
        // If no active semester, get the latest one
        if (!$currentSemester) {
            $currentSemester = Semester::latest()->first();
        }
        
        // Get all required data for the enrollment modal
        $schools = School::orderBy('name')->get();
        $programs = Program::with('school')->orderBy('name')->get();
        $semesters = Semester::orderBy('name')->get();
        $classes = ClassModel::with(['program', 'semester'])
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('section')
            ->get();
        
        // If still no semester, create a default response with all the modal data
        if (!$currentSemester) {
            $currentSemester = (object) [
                'id' => 0,
                'name' => 'No Active Semester',
                'is_active' => false,
                'year' => date('Y')
            ];
            
            return Inertia::render('Student/Enrollments', [
                'availableUnits' => [],
                'currentEnrollments' => [],
                'currentSemester' => $currentSemester,
                'student' => $student->load(['school', 'program']),
                'stats' => [
                    'enrolled_units' => 0,
                    'total_credits' => 0
                ],
                // Add all data needed for the modal
                'schools' => $schools,
                'programs' => $programs,
                'semesters' => $semesters,
                'classes' => $classes,
                'enrollments' => ['data' => []], 
                'selectedSemesterId' => 0,
                'userRoles' => [
                    'isStudent' => true,
                    'isAdmin' => false,
                    'isLecturer' => false,
                ],
                'flash' => [
                    'error' => 'No active semester found. Please contact administration.'
                ]
            ]);
        }

        // Get student's school and program
        $studentSchoolId = $student->school_id;
        $studentProgramId = $student->program_id;

        // Get available classes for the student's program and current semester
        $availableClasses = ClassModel::with(['program.school'])
            ->where('program_id', $studentProgramId)
            ->where('semester_id', $currentSemester->id)
            ->where('is_active', true)
            ->get();

        // Get units that are assigned to these classes and not already enrolled by student
        $enrolledUnitIds = Enrollment::where('student_code', $student->code)
            ->where('semester_id', $currentSemester->id)
            ->whereIn('status', ['enrolled', 'completed'])
            ->pluck('unit_id')
            ->toArray();

        $availableUnits = collect();
        
        foreach ($availableClasses as $class) {
            // Check if class has capacity
            $currentEnrollments = Enrollment::where('class_id', $class->id)
                ->where('semester_id', $currentSemester->id)
                ->where('status', 'enrolled')
                ->distinct('student_code')
                ->count();

            if ($currentEnrollments >= $class->capacity) {
                continue; // Skip full classes
            }

            // Get units assigned to this class
            $classUnits = Unit::with(['school', 'program'])
                ->whereHas('assignments', function($query) use ($class, $currentSemester) {
                    $query->where('class_id', $class->id)
                          ->where('semester_id', $currentSemester->id)
                          ->where('is_active', true);
                })
                ->whereNotIn('id', $enrolledUnitIds)
                ->where('is_active', true)
                ->get()
                ->map(function($unit) use ($class) {
                    // Add school_name and program_name for frontend compatibility
                    $unit->school_name = $unit->school->name ?? 'Unknown';
                    $unit->program_name = $unit->program->name ?? 'Unknown';
                    $unit->available_class = $class;
                    return $unit;
                });

            $availableUnits = $availableUnits->concat($classUnits);
        }

        // Remove duplicates and group by unit
        $availableUnits = $availableUnits->groupBy('id')->map(function($units) {
            $unit = $units->first();
            $unit->available_classes = $units->pluck('available_class')->map(function($class) {
                // Get current enrollment count for each class
                $currentEnrollments = Enrollment::where('class_id', $class->id)
                    ->where('semester_id', $class->semester_id)
                    ->where('status', 'enrolled')
                    ->distinct('student_code')
                    ->count();
                
                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'section' => $class->section,
                    'display_name' => "{$class->name} Section {$class->section}",
                    'capacity' => $class->capacity,
                    'current_enrollments' => $currentEnrollments,
                    'available_spots' => $class->capacity - $currentEnrollments,
                    'is_full' => $currentEnrollments >= $class->capacity
                ];
            });
            return $unit;
        })->values();

        // Get student's current enrollments for the semester
        $currentEnrollments = Enrollment::with(['unit', 'class', 'semester'])
            ->where('student_code', $student->code)
            ->where('semester_id', $currentSemester->id)
            ->get()
            ->map(function($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'unit' => [
                        'code' => $enrollment->unit->code,
                        'name' => $enrollment->unit->name,
                    ],
                    'semester' => [
                        'name' => $enrollment->semester->name,
                    ],
                    'group' => [
                        'name' => $enrollment->group_id ?: 'N/A'
                    ],
                    'status' => $enrollment->status,
                    'enrollment_date' => $enrollment->enrollment_date,
                ];
            });

        return Inertia::render('Student/Enrollments', [
            'availableUnits' => $availableUnits,
            'currentEnrollments' => $currentEnrollments,
            'currentSemester' => $currentSemester,
            'student' => $student->load(['school', 'program']),
            'stats' => [
                'enrolled_units' => $currentEnrollments->where('status', 'enrolled')->count(),
                'total_credits' => $currentEnrollments->where('status', 'enrolled')->sum(function($enrollment) {
                    return $enrollment->unit->credit_hours ?? 0;
                })
            ],
            // Add all data needed for the modal
            'schools' => $schools,
            'programs' => $programs,
            'semesters' => $semesters,
            'classes' => $classes,
            // Keep compatibility with existing component structure
            'enrollments' => ['data' => $currentEnrollments],
            'selectedSemesterId' => $currentSemester->id,
            'userRoles' => [
                'isStudent' => true,
                'isAdmin' => false,
                'isLecturer' => false,
            ]
        ]);
    }

    /**
     * Legacy method - redirects to main enrollment page
     */
    public function myEnrollments(Request $request)
    {
        return $this->showAvailableUnits($request);
    }

    /**
     * Student self-enrollment
     */
    public function enrollInUnit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'exists:units,id',
            'class_id' => 'required|exists:classes,id',
            'semester_id' => 'required|exists:semesters,id',
            'student_code' => 'required|string'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Please fill all required fields and select at least one unit.');
        }

        try {
            DB::beginTransaction();

            $student = auth()->user();
            $semester = Semester::find($request->semester_id);

            if (!$semester) {
                return redirect()->back()
                    ->with('error', 'Invalid semester selected.');
            }

            // Verify student is eligible for this class
            $class = ClassModel::with(['program.school'])->find($request->class_id);

            // Check if student's program matches class program (if student has program assigned)
            if ($student->program_id && $student->program_id !== $class->program_id) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'You can only enroll in classes for your program.');
            }

            // Check class capacity
            $currentClassEnrollments = Enrollment::where('class_id', $request->class_id)
                ->where('semester_id', $request->semester_id)
                ->where('status', 'enrolled')
                ->distinct('student_code')
                ->count();

            if ($currentClassEnrollments >= $class->capacity) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'This class has reached its maximum capacity.');
            }

            // Check if student is already enrolled in this class for this semester
            $existingClassEnrollment = Enrollment::where('student_code', $student->code)
                ->where('class_id', $request->class_id)
                ->where('semester_id', $request->semester_id)
                ->exists();

            if ($existingClassEnrollment) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'You are already enrolled in this class for the selected semester.');
            }

            $createdCount = 0;
            $skippedCount = 0;
            $skippedUnits = [];

            foreach ($request->unit_ids as $unitId) {
                // Check if student is already enrolled in this unit for this semester
                $existingEnrollment = Enrollment::where('student_code', $student->code)
                    ->where('unit_id', $unitId)
                    ->where('semester_id', $request->semester_id)
                    ->whereIn('status', ['enrolled', 'completed'])
                    ->first();

                if ($existingEnrollment) {
                    $skippedCount++;
                    $unit = Unit::find($unitId);
                    $skippedUnits[] = $unit->name;
                    continue;
                }

                // Verify unit is assigned to this class
                $unitAssignment = UnitAssignment::where('unit_id', $unitId)
                    ->where('class_id', $request->class_id)
                    ->where('semester_id', $request->semester_id)
                    ->where('is_active', true)
                    ->first();

                if (!$unitAssignment) {
                    $skippedCount++;
                    $unit = Unit::find($unitId);
                    $skippedUnits[] = $unit->name . ' (not assigned to this class)';
                    continue;
                }

                // Get unit details for additional fields
                $unit = Unit::find($unitId);

                // Create enrollment
                Enrollment::create([
                    'student_code' => $student->code,
                    'lecturer_code' => $unitAssignment->lecturer_code ?? '',
                    'group_id' => '',
                    'unit_id' => $unitId,
                    'class_id' => $request->class_id,
                    'semester_id' => $request->semester_id,
                    'program_id' => $class->program_id,
                    'school_id' => $student->school_id ?? $class->program->school_id ?? $unit->school_id,
                    'status' => 'enrolled',
                    'enrollment_date' => now()
                ]);

                $createdCount++;
            }

            // Update class student count
            $this->updateClassStudentCount($request->class_id, $request->semester_id);

            DB::commit();

            if ($createdCount === 0) {
                return redirect()->back()
                    ->with('error', 'No enrollments were created. All selected units were either already enrolled or not available.');
            }

            $message = "{$createdCount} enrollments created successfully for {$class->name} Section {$class->section}.";
            if ($skippedCount > 0) {
                $message .= " {$skippedCount} units were skipped: " . implode(', ', $skippedUnits);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Student enrollment error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to enroll. Please try again or contact support.');
        }
    }

    /**
     * Drop a unit (student self-drop)
     */
    public function dropUnit(Request $request, $enrollmentId)
    {
        try {
            DB::beginTransaction();

            $student = auth()->user();
            $enrollment = Enrollment::where('id', $enrollmentId)
                ->where('student_code', $student->code)
                ->where('status', 'enrolled')
                ->first();

            if (!$enrollment) {
                return redirect()->back()
                    ->with('error', 'Enrollment not found or already processed.');
            }

            // Check if it's still within drop period
            $enrollmentDate = Carbon::parse($enrollment->enrollment_date);
            $daysSinceEnrollment = $enrollmentDate->diffInDays(now());
            
            $dropDeadlineDays = 14;
            if ($daysSinceEnrollment > $dropDeadlineDays) {
                return redirect()->back()
                    ->with('error', "Drop period has expired. You can only drop units within {$dropDeadlineDays} days of enrollment.");
            }

            $enrollment->update(['status' => 'dropped']);

            // Update class student count
            $this->updateClassStudentCount($enrollment->class_id, $enrollment->semester_id);

            DB::commit();

            return redirect()->back()
                ->with('success', 'Unit dropped successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Student drop error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to drop unit. Please contact support.');
        }
    }

    /**
     * Get available classes for a specific unit
     */
    public function getAvailableClassesForUnit(Request $request, $unitId)
    {
        $request->merge(['unit_id' => $unitId]);
        
        $request->validate([
            'unit_id' => 'required|exists:units,id'
        ]);

        $student = auth()->user();
        $currentSemester = Semester::where('is_active', true)->first();

        if (!$currentSemester) {
            return response()->json(['error' => 'No active semester'], 400);
        }

        try {
            $classes = ClassModel::whereHas('assignments', function($query) use ($request, $currentSemester) {
                $query->where('unit_id', $request->unit_id)
                      ->where('semester_id', $currentSemester->id)
                      ->where('is_active', true);
            })
            ->where('program_id', $student->program_id)
            ->where('is_active', true)
            ->get()
            ->map(function($class) use ($currentSemester) {
                $currentEnrollments = Enrollment::where('class_id', $class->id)
                    ->where('semester_id', $currentSemester->id)
                    ->where('status', 'enrolled')
                    ->distinct('student_code')
                    ->count();

                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'section' => $class->section,
                    'display_name' => "{$class->name} Section {$class->section}",
                    'capacity' => $class->capacity,
                    'current_enrollments' => $currentEnrollments,
                    'available_spots' => $class->capacity - $currentEnrollments,
                    'is_full' => $currentEnrollments >= $class->capacity
                ];
            })
            ->filter(function($class) {
                return !$class['is_full'];
            })
            ->values();

            return response()->json($classes);

        } catch (\Exception $e) {
            Log::error('Error fetching available classes: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch classes'], 500);
        }
    }

    /**
     * Helper method to update class student count
     */
    private function updateClassStudentCount($classId, $semesterId)
    {
        try {
            $studentCount = Enrollment::where('class_id', $classId)
                ->where('semester_id', $semesterId)
                ->where('status', 'enrolled')
                ->distinct('student_code')
                ->count();

            ClassModel::where('id', $classId)
                ->update(['students_count' => $studentCount]);
                
        } catch (\Exception $e) {
            Log::error('Error updating class student count: ' . $e->getMessage());
        }
    }

    // Keep all your existing methods below (studentDashboard, myTimetable, myExams, profile)
    
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

            $enrolledUnitIds = $enrolledUnits->pluck('id')->toArray();
            
            if (!empty($enrolledUnitIds)) {
                try {
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
                    Log::info('Exam timetables table not found or error occurred', ['error' => $e->getMessage()]);
                    $upcomingExams = collect();
                }
            }
        }

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
        
        $currentSemester = Semester::where('is_active', true)->first();
        if (!$currentSemester) {
            $currentSemester = Semester::latest()->first();
        }
        
        $selectedSemesterId = $request->input('semester_id', $currentSemester->id ?? null);
        
        $enrollment = Enrollment::where('student_code', $user->code)
            ->where('semester_id', $selectedSemesterId)
            ->first();
        $studentClassId = $enrollment->class_id ?? null;
        
        $enrolledUnits = Enrollment::where('student_code', $user->code)
            ->where('semester_id', $selectedSemesterId)
            ->with(['unit'])
            ->get();
        
        $enrolledUnitIds = $enrolledUnits->pluck('unit.id')->filter()->toArray();
        
        $classInfo = null;
        if ($studentClassId) {
            $classInfo = DB::table('classes')->where('id', $studentClassId)->first();
        }
        
        $classTimetables = collect();
        
        if (!empty($enrolledUnitIds) && $studentClassId) {
            $classTimetables = DB::table('class_timetable')
                ->leftJoin('units', 'class_timetable.unit_id', '=', 'units.id')
                ->leftJoin('users', 'users.code', '=', 'class_timetable.lecturer')
                ->leftJoin('classes', 'class_timetable.class_id', '=', 'classes.id')
                ->whereIn('class_timetable.unit_id', $enrolledUnitIds)
                ->where('class_timetable.semester_id', $selectedSemesterId)
                ->where('class_timetable.class_id', $studentClassId)
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

    public function myExams(Request $request)
    {
        $user = $request->user();
        
        $currentSemester = Semester::where('is_active', true)->first();
        if (!$currentSemester) {
            $currentSemester = Semester::latest()->first();
        }
        
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

    public function profile(Request $request)
    {
        $user = $request->user();
        
        $student = User::with(['faculty', 'enrollments'])
            ->where('id', $user->id)
            ->first();
            
        return Inertia::render('Student/Profile', [
            'student' => $student,
        ]);
    }
}