<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Unit;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\School;
use App\Models\Program;
use Inertia\Inertia;

class StudentController extends Controller
{
    /**
     * Constructor - Apply auth middleware
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Check if user has student access (Spatie compatible)
     */
    private function checkStudentAccess($user)
    {
        // Check if user has Student role
        if ($user->hasRole('Student')) {
            return true;
        }

        // Alternative: Check if user code is a student code
        if (isset($user->code) && (
            preg_match('/^[0-9]+$/', $user->code) || // Numeric codes are students
            str_contains(strtoupper($user->code), 'STU')
        )) {
            return true;
        }

        Log::warning('Student access denied', [
            'user_id' => $user->id,
            'user_code' => $user->code,
            'roles' => $user->getRoleNames()->toArray()
        ]);

        return false;
    }

    /**
     * Student Dashboard
     */
    public function studentDashboard(Request $request)
    {
        $user = $request->user();

        // Check student access
        if (!$this->checkStudentAccess($user)) {
            abort(403, 'Access denied. You do not have student privileges.');
        }

        try {
            // Get student's actual enrolled semester
            $allEnrollments = Enrollment::where('student_code', $user->code)->get();

            if ($allEnrollments->isNotEmpty()) {
                // Use semester from most recent enrollment
                $latestSemesterId = $allEnrollments->sortByDesc('created_at')->first()->semester_id;
                $currentSemester = Semester::find($latestSemesterId);
                
                Log::info('Dashboard using student enrolled semester', [
                    'student_code' => $user->code,
                    'semester_id' => $latestSemesterId,
                    'semester_name' => $currentSemester->name ?? 'Unknown'
                ]);
            } else {
                // Fallback: Use active semester if student has no enrollments yet
                $currentSemester = Semester::where('is_active', true)->first();
                if (!$currentSemester) {
                    $currentSemester = Semester::latest()->first();
                }
                
                Log::info('Dashboard using fallback active semester (no enrollments)', [
                    'student_code' => $user->code,
                    'semester_name' => $currentSemester->name ?? 'N/A'
                ]);
            }

            $enrolledUnits = collect();
            $upcomingExams = collect();

            if ($currentSemester && $user->code) {
                // Get student's enrolled units
                $enrolledUnits = Enrollment::where('student_code', $user->code)
                    ->where('semester_id', $currentSemester->id)
                    ->where('status', 'enrolled')
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

                // ✅ FIXED: Changed 'exam_timetable' to 'exam_timetables'
                if (!empty($enrolledUnitIds)) {
                    try {
                        $upcomingExams = DB::table('exam_timetables')
                            ->join('units', 'exam_timetables.unit_id', '=', 'units.id')
                            ->whereIn('exam_timetables.unit_id', $enrolledUnitIds)
                            ->where('exam_timetables.semester_id', $currentSemester->id)
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
                                    'venue' => $exam->venue ?? 'TBA',
                                    'unit' => [
                                        'code' => $exam->unit_code,
                                        'name' => $exam->unit_name
                                    ]
                                ];
                            });
                    } catch (\Exception $e) {
                        Log::error('Error fetching exam timetable in dashboard', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            return Inertia::render('Student/Dashboard', [
                'enrolledUnits' => $enrolledUnits,
                'upcomingExams' => $upcomingExams,
                'currentSemester' => $currentSemester ? [
                    'id' => $currentSemester->id,
                    'name' => $currentSemester->name,
                    'year' => $currentSemester->year ?? date('Y'),
                    'is_active' => $currentSemester->is_active
                ] : null
            ]);

        } catch (\Exception $e) {
            Log::error('Error in student dashboard', [
                'user_code' => $user->code,
                'error' => $e->getMessage()
            ]);

            return Inertia::render('Student/Dashboard', [
                'enrolledUnits' => [],
                'upcomingExams' => [],
                'currentSemester' => null,
                'error' => 'An error occurred while loading the dashboard.'
            ]);
        }
    }

    /**
     * Show available units for enrollment
     */
    public function showAvailableUnits(Request $request)
    {
        $user = $request->user();

        if (!$this->checkStudentAccess($user)) {
            abort(403, 'Access denied. You do not have student privileges.');
        }

        try {
            Log::info('=== ENROLLMENTS PAGE LOAD ===', [
                'student_code' => $user->code,
                'student_id' => $user->id
            ]);

            $allEnrollments = Enrollment::where('student_code', $user->code)->get();
            
            Log::info('All enrollments fetched', [
                'count' => $allEnrollments->count(),
                'semester_ids' => $allEnrollments->pluck('semester_id')->unique()->toArray()
            ]);
            
            $selectedSemesterId = $request->input('semester_id');
            
            if ($selectedSemesterId) {
                $currentSemester = Semester::find($selectedSemesterId);
                Log::info('User selected semester', ['semester_id' => $selectedSemesterId]);
            } else if ($allEnrollments->isNotEmpty()) {
                $latestSemesterId = $allEnrollments->sortByDesc('created_at')->first()->semester_id;
                $currentSemester = Semester::find($latestSemesterId);
                Log::info('Auto-detected semester from enrollments', [
                    'semester_id' => $latestSemesterId,
                    'semester_name' => $currentSemester->name ?? 'Unknown'
                ]);
            } else {
                $currentSemester = Semester::where('is_active', true)->latest('id')->first();
                Log::info('Using default active semester', [
                    'semester_id' => $currentSemester->id ?? 'none'
                ]);
            }

            $currentEnrollments = Enrollment::with(['unit', 'class', 'semester'])
                ->where('student_code', $user->code)
                ->where('semester_id', $currentSemester->id ?? 0)
                ->get();

            Log::info('Filtered enrollments', [
                'semester_id' => $currentSemester->id ?? 'none',
                'enrollment_count' => $currentEnrollments->count()
            ]);

            $semesters = Semester::where('is_active', true)->orderBy('name')->get();
            $studentSemesterIds = $allEnrollments->pluck('semester_id')->unique()->values()->toArray();
            
            $uniqueUnits = $currentEnrollments->pluck('unit_id')->unique()->count();
            $enrolledCount = $currentEnrollments->where('status', 'enrolled')->count();

            $stats = [
                'enrolled_units' => $enrolledCount,
                'unique_units' => $uniqueUnits,
                'total_enrollments' => $currentEnrollments->count(),
                'all_semesters_count' => $allEnrollments->count()
            ];

            return Inertia::render('Student/Enrollments', [
                'enrollments' => [
                    'data' => $currentEnrollments->map(function($enrollment) {
                        return [
                            'id' => $enrollment->id,
                            'unit' => [
                                'code' => $enrollment->unit->code ?? 'N/A',
                                'name' => $enrollment->unit->name ?? 'N/A',
                            ],
                            'semester' => [
                                'name' => $enrollment->semester->name ?? 'N/A',
                            ],
                            'group' => [
                                'name' => $enrollment->group_id ?? 'N/A',
                            ],
                            'class' => $enrollment->class ? [
                                'name' => $enrollment->class->name,
                                'section' => $enrollment->class->section
                            ] : null,
                            'status' => $enrollment->status,
                            'created_at' => $enrollment->created_at
                        ];
                    })->toArray()
                ],
                'currentSemester' => $currentSemester ? [
                    'id' => $currentSemester->id,
                    'name' => $currentSemester->name
                ] : null,
                'semesters' => $semesters,
                'selectedSemesterId' => $currentSemester ? $currentSemester->id : null,
                'schools' => School::orderBy('name')->get(),
                'programs' => Program::orderBy('name')->get(),
                'classes' => [],
                'student' => [
                    'id' => $user->id,
                    'code' => $user->code,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name ?? 'Student',
                    'email' => $user->email
                ],
                'stats' => $stats,
                'userRoles' => [
                    'isStudent' => true,
                    'isAdmin' => false,
                    'isLecturer' => false
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERROR IN ENROLLMENTS ===', [
                'user_code' => $user->code ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render('Student/Enrollments', [
                'error' => 'An error occurred while loading enrollments: ' . $e->getMessage(),
                'enrollments' => ['data' => []],
                'currentSemester' => null,
                'semesters' => Semester::where('is_active', true)->get(),
                'selectedSemesterId' => null,
                'schools' => School::orderBy('name')->get(),
                'programs' => Program::orderBy('name')->get(),
                'classes' => [],
                'student' => [
                    'id' => $user->id ?? null,
                    'code' => $user->code ?? null,
                    'name' => 'Student',
                    'email' => $user->email ?? null
                ],
                'stats' => [
                    'enrolled_units' => 0,
                    'unique_units' => 0,
                    'total_enrollments' => 0,
                    'all_semesters_count' => 0
                ],
                'userRoles' => [
                    'isStudent' => true,
                    'isAdmin' => false,
                    'isLecturer' => false
                ]
            ]);
        }
    }

    /**
     * Student's class timetable
     */
    public function myTimetable(Request $request)
    {
        $user = $request->user();

        if (!$this->checkStudentAccess($user)) {
            abort(403, 'Access denied. You do not have student privileges.');
        }

        try {
            $allEnrollments = Enrollment::where('student_code', $user->code)->get();
            
            $selectedSemesterId = $request->input('semester_id');
            
            if ($selectedSemesterId) {
                $currentSemester = Semester::find($selectedSemesterId);
            } else if ($allEnrollments->isNotEmpty()) {
                $latestSemesterId = $allEnrollments->sortByDesc('created_at')->first()->semester_id;
                $currentSemester = Semester::find($latestSemesterId);
            } else {
                $currentSemester = Semester::where('is_active', true)->latest('id')->first();
            }

            $selectedSemesterId = $currentSemester->id ?? null;

            $enrollment = Enrollment::where('student_code', $user->code)
                ->where('semester_id', $selectedSemesterId)
                ->first();
            $studentClassId = $enrollment->class_id ?? null;

            $enrolledUnits = Enrollment::where('student_code', $user->code)
                ->where('semester_id', $selectedSemesterId)
                ->where('status', 'enrolled')
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

            $semesters = Semester::where('is_active', true)->orderBy('name')->get();

            return Inertia::render('Student/ClassTimetable', [
                'classTimetables' => ['data' => $classTimetables],
                'enrolledUnits' => $enrolledUnits,
                'currentSemester' => $currentSemester,
                'semesters' => $semesters,
                'selectedSemesterId' => (int)$selectedSemesterId,
                'studentInfo' => [
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name,
                    'code' => $user->code,
                    'class_name' => $classInfo->name ?? null,
                    'section' => $classInfo->section ?? null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in myTimetable', [
                'user_code' => $user->code,
                'error' => $e->getMessage()
            ]);

            return Inertia::render('Student/ClassTimetable', [
                'error' => 'An error occurred while loading your timetable.',
                'classTimetables' => ['data' => []],
                'enrolledUnits' => [],
                'currentSemester' => null,
                'semesters' => [],
                'selectedSemesterId' => null,
                'studentInfo' => []
            ]);
        }
    }

    /**
     * Student's exam timetable - FULLY FIXED VERSION
     */
    public function myExams(Request $request)
    {
        $user = $request->user();

        if (!$this->checkStudentAccess($user)) {
            abort(403, 'Access denied. You do not have student privileges.');
        }

        try {
            Log::info('=== STUDENT EXAM TIMETABLE REQUEST ===', [
                'student_code' => $user->code,
                'requested_semester_id' => $request->input('semester_id')
            ]);

            $allEnrollments = Enrollment::where('student_code', $user->code)->get();
            
            Log::info('Total enrollments found', [
                'student_code' => $user->code,
                'total_enrollments' => $allEnrollments->count(),
                'unique_semesters' => $allEnrollments->pluck('semester_id')->unique()->toArray()
            ]);
            
            $selectedSemesterId = $request->input('semester_id');
            
            if ($selectedSemesterId) {
                $currentSemester = Semester::find($selectedSemesterId);
                Log::info('Using selected semester', ['semester_id' => $selectedSemesterId]);
            } else if ($allEnrollments->isNotEmpty()) {
                $latestSemesterId = $allEnrollments->sortByDesc('created_at')->first()->semester_id;
                $currentSemester = Semester::find($latestSemesterId);
                Log::info('Using latest enrollment semester', [
                    'semester_id' => $latestSemesterId,
                    'semester_name' => $currentSemester->name ?? 'Unknown'
                ]);
            } else {
                $currentSemester = Semester::where('is_active', true)->latest('id')->first();
                Log::info('Using active semester (no enrollments)', [
                    'semester_id' => $currentSemester->id ?? null
                ]);
            }

            $enrolledUnits = collect();
            $upcomingExams = collect();
            $allExams = collect();

            if ($currentSemester && $user->code) {
                $enrolledUnits = Enrollment::where('student_code', $user->code)
                    ->where('semester_id', $currentSemester->id)
                    ->where('status', 'enrolled')
                    ->with(['unit.school', 'class'])
                    ->get();

                Log::info('Enrolled units fetched', [
                    'semester_id' => $currentSemester->id,
                    'enrollments_count' => $enrolledUnits->count()
                ]);

                $enrolledUnitIds = $enrolledUnits->pluck('unit_id')->filter()->unique()->toArray();

                Log::info('Processing unit IDs for exams', [
                    'enrolled_unit_ids' => $enrolledUnitIds,
                    'count' => count($enrolledUnitIds)
                ]);

                if (!empty($enrolledUnitIds)) {
                    try {
                        // ✅ FIXED: Changed 'exam_timetable' to 'exam_timetables' throughout
                        $examQuery = DB::table('exam_timetables')
                            ->join('units', 'exam_timetables.unit_id', '=', 'units.id')
                            ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
                            ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
                            ->whereIn('exam_timetables.unit_id', $enrolledUnitIds)
                            ->where('exam_timetables.semester_id', $currentSemester->id)
                            ->select(
                                'exam_timetables.id',
                                'exam_timetables.unit_id',
                                'exam_timetables.semester_id',
                                'exam_timetables.class_id',
                                'exam_timetables.date',
                                'exam_timetables.day',
                                'exam_timetables.start_time',
                                'exam_timetables.end_time',
                                'exam_timetables.venue',
                                'exam_timetables.location',
                                'exam_timetables.no',
                                'exam_timetables.chief_invigilator',
                                'units.code as unit_code',
                                'units.name as unit_name',
                                'classes.name as class_name',
                                'classes.section as class_section',
                                'semesters.name as semester_name'
                            )
                            ->orderBy('exam_timetables.date')
                            ->orderBy('exam_timetables.start_time');

                        Log::info('Executing exam query', [
                            'semester_id' => $currentSemester->id,
                            'unit_ids' => $enrolledUnitIds
                        ]);

                        $allExams = $examQuery->get();

                        Log::info('Exam query completed', [
                            'total_exams_found' => $allExams->count(),
                            'sample_exam' => $allExams->first() ? [
                                'id' => $allExams->first()->id,
                                'unit_code' => $allExams->first()->unit_code,
                                'date' => $allExams->first()->date,
                                'venue' => $allExams->first()->venue
                            ] : null
                        ]);

                        $today = now()->toDateString();
                        $upcomingExams = $allExams->filter(function($exam) use ($today) {
                            return $exam->date >= $today;
                        })->values();

                        Log::info('Exams separated by date', [
                            'today' => $today,
                            'total_exams' => $allExams->count(),
                            'upcoming_exams' => $upcomingExams->count()
                        ]);

                    } catch (\Exception $e) {
                        Log::error('Error fetching exam timetable', [
                            'student_code' => $user->code,
                            'semester_id' => $currentSemester->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
            }

            $semesters = Semester::where('is_active', true)->orderBy('name')->get();
            $studentSemesterIds = $allEnrollments->pluck('semester_id')->unique()->values()->toArray();

            // ✅ Format enrolled units to match React component expectations
            $formattedEnrolledUnits = $enrolledUnits->map(function($enrollment) {
                $unit = $enrollment->unit;
                $class = $enrollment->class;
                
                return [
                    'id' => $enrollment->id,
                    'unit' => [
                        'id' => $unit->id ?? null,
                        'code' => $unit->code ?? 'N/A',
                        'name' => $unit->name ?? 'N/A',
                    ],
                    'school' => $unit && $unit->school ? [
                        'name' => $unit->school->name
                    ] : null,
                    'class' => $class ? [
                        'name' => $class->name,
                        'section' => $class->section
                    ] : null
                ];
            })->values()->toArray();

            Log::info('Final data prepared for frontend', [
                'enrolled_units_count' => count($formattedEnrolledUnits),
                'all_exams_count' => $allExams->count(),
                'upcoming_exams_count' => $upcomingExams->count()
            ]);

            return Inertia::render('Student/ExamTimetable', [
                'upcomingExams' => $upcomingExams->values()->toArray(),
                'allExams' => $allExams->values()->toArray(),
                'enrolledUnits' => $formattedEnrolledUnits,
                'currentSemester' => $currentSemester ? [
                    'id' => $currentSemester->id,
                    'name' => $currentSemester->name
                ] : null,
                'semesters' => $semesters,
                'selectedSemesterId' => $currentSemester ? (int)$currentSemester->id : null,
                'studentSemesterIds' => $studentSemesterIds,
                'stats' => [
                    'total_exams' => $allExams->count(),
                    'upcoming_exams' => $upcomingExams->count(),
                    'enrolled_units' => count($formattedEnrolledUnits)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('=== CRITICAL ERROR IN myExams ===', [
                'user_code' => $user->code ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render('Student/ExamTimetable', [
                'error' => 'An error occurred while loading exam schedule.',
                'upcomingExams' => [],
                'allExams' => [],
                'enrolledUnits' => [],
                'currentSemester' => null,
                'semesters' => Semester::where('is_active', true)->orderBy('name')->get(),
                'selectedSemesterId' => null,
                'studentSemesterIds' => [],
                'stats' => [
                    'total_exams' => 0,
                    'upcoming_exams' => 0,
                    'enrolled_units' => 0
                ]
            ]);
        }
    }

    /**
     * Student profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        if (!$this->checkStudentAccess($user)) {
            abort(403, 'Access denied. You do not have student privileges.');
        }

        try {
            $student = User::with(['school', 'program'])
                ->where('id', $user->id)
                ->first();

            return Inertia::render('Student/Profile', [
                'student' => $student
            ]);

        } catch (\Exception $e) {
            Log::error('Error in profile', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return Inertia::render('Student/Profile', [
                'error' => 'An error occurred while loading your profile.',
                'student' => null
            ]);
        }
    }

    /**
     * Enroll student in units
     */
    public function enrollInUnit(Request $request)
    {
        $user = $request->user();

        if (!$this->checkStudentAccess($user)) {
            abort(403, 'Access denied. You do not have student privileges.');
        }

        try {
            $validated = $request->validate([
                'student_code' => 'required|string',
                'school_id' => 'required|exists:schools,id',
                'program_id' => 'required|exists:programs,id',
                'class_id' => 'required|exists:classes,id',
                'semester_id' => 'required|exists:semesters,id',
                'unit_ids' => 'required|array|min:1',
                'unit_ids.*' => 'required|exists:units,id',
                'status' => 'sometimes|in:enrolled,dropped,completed'
            ]);

            if ($validated['student_code'] !== $user->code) {
                return back()->withErrors(['error' => 'Student code mismatch']);
            }

            $enrolledCount = 0;
            $errors = [];

            DB::beginTransaction();

            foreach ($validated['unit_ids'] as $unitId) {
                try {
                    $existingEnrollment = Enrollment::where('student_code', $validated['student_code'])
                        ->where('unit_id', $unitId)
                        ->where('semester_id', $validated['semester_id'])
                        ->first();

                    if ($existingEnrollment) {
                        continue;
                    }

                    Enrollment::create([
                        'student_code' => $validated['student_code'],
                        'unit_id' => $unitId,
                        'class_id' => $validated['class_id'],
                        'semester_id' => $validated['semester_id'],
                        'group_id' => $validated['class_id'],
                        'status' => $validated['status'] ?? 'enrolled',
                        'enrolled_at' => now()
                    ]);

                    $enrolledCount++;

                } catch (\Exception $e) {
                    Log::error('Failed to enroll in unit', [
                        'unit_id' => $unitId,
                        'error' => $e->getMessage()
                    ]);
                    $errors[] = "Failed to enroll in unit ID: $unitId";
                }
            }

            DB::commit();

            if ($enrolledCount > 0) {
                return redirect()->route('student.enrollments')
                    ->with('success', "Successfully enrolled in $enrolledCount unit(s)");
            } else if (!empty($errors)) {
                return back()->withErrors(['error' => implode(', ', $errors)]);
            } else {
                return back()->withErrors(['error' => 'You are already enrolled in all selected units']);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Enrollment error', [
                'user_code' => $user->code,
                'error' => $e->getMessage()
            ]);
            return back()->withErrors(['error' => 'An error occurred during enrollment: ' . $e->getMessage()]);
        }
    }

    /**
     * Drop a unit enrollment
     */
    public function dropUnit(Enrollment $enrollment)
    {
        $user = request()->user();

        if (!$this->checkStudentAccess($user)) {
            abort(403, 'Access denied. You do not have student privileges.');
        }

        try {
            if ($enrollment->student_code !== $user->code) {
                abort(403, 'You cannot drop another student\'s enrollment');
            }

            $enrollment->update(['status' => 'dropped']);

            return redirect()->route('student.enrollments')
                ->with('success', 'Unit dropped successfully');

        } catch (\Exception $e) {
            Log::error('Error dropping unit', [
                'enrollment_id' => $enrollment->id,
                'error' => $e->getMessage()
            ]);
            return back()->withErrors(['error' => 'Failed to drop unit: ' . $e->getMessage()]);
        }
    }
}