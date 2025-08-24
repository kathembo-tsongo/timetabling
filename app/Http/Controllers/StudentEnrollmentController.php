<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Unit;
use App\Models\Semester;
use App\Models\ClassModel;
use App\Models\Group;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class StudentEnrollmentController extends Controller
{
    protected $enrollmentService;

    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->middleware('auth');
        $this->enrollmentService = $enrollmentService;
    }

    /**
     * Show the enrollment form with all required data.
     */
    public function showEnrollmentForm(Request $request)
    {
        $student = Auth::user();
        
        Log::info('Student accessing enrollment form', [
            'student_code' => $student->code,
            'student_id' => $student->id
        ]);
        
        // Get current enrollments with relationships
        $enrollments = Enrollment::with(['unit', 'group', 'group.class'])
            ->where('student_code', $student->code)
            ->get();

        // Debug: Check what semesters exist in database
        $allSemesters = Semester::all();
        Log::info('All semesters in database', [
            'count' => $allSemesters->count(),
            'semesters' => $allSemesters->toArray()
        ]);

        // Get all semesters (remove is_active filter for debugging)
        $semesters = Semester::select('id', 'name')
            ->orderBy('name')
            ->get();
            
        Log::info('Filtered semesters for student', [
            'count' => $semesters->count(),
            'semesters' => $semesters->toArray()
        ]);
            
        $classes = ClassModel::with('semester')
            ->select('id', 'name', 'semester_id')
            ->orderBy('name')
            ->get();
            
        Log::info('Classes data', [
            'count' => $classes->count(),
            'classes' => $classes->toArray()
        ]);
            
        $groups = Group::with(['class' => function($query) {
                $query->select('id', 'name');
            }])
            ->select('id', 'name', 'class_id', 'capacity')
            ->orderBy('name')
            ->get();
            
        Log::info('Groups data', [
            'count' => $groups->count(),
            'groups' => $groups->toArray()
        ]);
            
        $units = Unit::with(['program', 'school'])
            ->where('is_active', true)
            ->select('id', 'name', 'code', 'program_id', 'school_id')
            ->orderBy('name')
            ->get();

        Log::info('Units data', [
            'count' => $units->count(),
            'units_sample' => $units->take(3)->toArray()
        ]);

        // Format student data to match frontend expectations
        $studentData = [
            'id' => $student->id,
            'code' => $student->code,
            'first_name' => $student->first_name ?? $student->name ?? 'Student',
            'last_name' => $student->last_name ?? '',
            'email' => $student->email
        ];

        $responseData = [
            'student' => $studentData,
            'enrollments' => $enrollments,
            'semesters' => $semesters,
            'classes' => $classes,
            'groups' => $groups,
            'units' => $units,
        ];

        Log::info('Final response data structure', [
            'student_code' => $student->code,
            'semesters_count' => $semesters->count(),
            'classes_count' => $classes->count(),
            'groups_count' => $groups->count(),
            'units_count' => $units->count(),
            'current_enrollments' => $enrollments->count(),
            'response_keys' => array_keys($responseData)
        ]);

        return Inertia::render('Student/Enroll', $responseData);
    }
    /**
     * Process student enrollment (matches the working admin pattern).
     */
    public function enroll(Request $request)
    {
        $studentCode = Auth::user()->code;

        Log::info('Student self-enrollment attempt', [
            'student_code' => $studentCode,
            'request_data' => $request->all()
        ]);

        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
        ], [
            'unit_ids.required' => 'Please select at least one unit to enroll in.',
            'unit_ids.min' => 'Please select at least one unit to enroll in.',
        ]);

        $group = Group::findOrFail($validated['group_id']);
        $class = $group->class;
        
        if (!$class) {
            return redirect()->back()->withErrors([
                'group_id' => 'Selected group does not belong to a valid class.'
            ]);
        }

        // CAPACITY CHECK (same as admin enrollment)
        $currentEnrollmentCount = Enrollment::where('group_id', $group->id)
            ->distinct('student_code')
            ->count('student_code');

        $studentAlreadyInGroup = Enrollment::where('group_id', $group->id)
            ->where('student_code', $studentCode)
            ->exists();

        if (!$studentAlreadyInGroup && $currentEnrollmentCount >= $group->capacity) {
            Log::warning('Student enrollment blocked - group full', [
                'student_code' => $studentCode,
                'group_id' => $group->id,
                'capacity' => $group->capacity,
                'current_count' => $currentEnrollmentCount
            ]);
            
            return redirect()->back()->withErrors([
                'group_id' => "This group is already full. Capacity: {$group->capacity}, Current enrollments: {$currentEnrollmentCount}",
            ]);
        }

        // Check for existing enrollments
        $existingEnrollments = Enrollment::where('group_id', $group->id)
            ->where('student_code', $studentCode)
            ->where('semester_id', $validated['semester_id'])
            ->pluck('unit_id')
            ->toArray();

        if (!empty($existingEnrollments)) {
            $conflictingUnits = array_intersect($validated['unit_ids'], $existingEnrollments);
            if (!empty($conflictingUnits)) {
                $unitNames = Unit::whereIn('id', $conflictingUnits)->pluck('name')->toArray();
                return redirect()->back()->withErrors([
                    'unit_ids' => 'You are already enrolled in: ' . implode(', ', $unitNames),
                ]);
            }
        }

        try {
            $enrollmentCount = 0;
            $enrolledUnits = [];

            foreach ($validated['unit_ids'] as $unitId) {
                $unit = Unit::with(['program', 'school'])->find($unitId);

                if (!$unit) {
                    Log::warning('Unit not found during enrollment', ['unit_id' => $unitId]);
                    continue;
                }

                // Check if already enrolled in this specific unit
                $exists = Enrollment::where('student_code', $studentCode)
                    ->where('unit_id', $unitId)
                    ->where('semester_id', $validated['semester_id'])
                    ->exists();

                if ($exists) {
                    Log::info('Student already enrolled in unit, skipping', [
                        'student_code' => $studentCode,
                        'unit_id' => $unitId
                    ]);
                    continue;
                }

                $enrollment = Enrollment::create([
                    'student_code' => $studentCode,
                    'group_id' => $group->id,
                    'unit_id' => $unitId,
                    'semester_id' => $validated['semester_id'],
                    'program_id' => $unit->program_id ?? $class->program_id ?? null,
                    'school_id' => $unit->school_id ?? $class->school_id ?? null,
                ]);

                $enrollmentCount++;
                $enrolledUnits[] = $unit->name;

                Log::info('Student enrollment created successfully', [
                    'enrollment_id' => $enrollment->id,
                    'student_code' => $studentCode,
                    'unit_name' => $unit->name,
                    'group_id' => $group->id
                ]);
            }

            if ($enrollmentCount > 0) {
                return redirect()->back()->with('success', 
                    "Successfully enrolled in {$enrollmentCount} unit(s): " . implode(', ', $enrolledUnits)
                );
            } else {
                return redirect()->back()->withErrors([
                    'error' => 'No new enrollments were created. You may already be enrolled in the selected units.'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Student enrollment failed', [
                'error' => $e->getMessage(),
                'student_code' => $studentCode,
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()->withErrors([
                'error' => 'Failed to enroll in units: ' . $e->getMessage()
            ]);
        }
    }

 /**
     * View current enrollments - SPATIE COMPATIBLE
     */
    public function viewEnrollments(Request $request)
    {
        try {
            $user = Auth::user();
            
            // SPATIE: Check if user has permission (optional additional check)
            if (!$user->can('view-enrollments')) {
                Log::warning('User lacks view-enrollments permission', [
                    'user_id' => $user->id,
                    'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray()
                ]);
                
                return redirect()->back()->withErrors([
                    'error' => 'You do not have permission to view enrollments.'
                ]);
            }
            
            Log::info('Student accessing enrollments (Spatie)', [
                'user_id' => $user->id,
                'user_code' => $user->code,
                'roles' => $user->getRoleNames()->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray()
            ]);
            
            // Get active semester or selected semester
            $semesterId = $request->input('semester_id');
            if (!$semesterId) {
                $activeSemester = Semester::where('is_active', true)->first();
                $semesterId = $activeSemester ? $activeSemester->id : null;
            }
            
            // Get enrollments for the student
            $enrollments = Enrollment::where('student_code', $user->code)
                ->when($semesterId, function ($query) use ($semesterId) {
                    return $query->where('semester_id', $semesterId);
                })
                ->with([
                    'unit' => function ($query) {
                        $query->select('id', 'name', 'code');
                    }, 
                    'semester' => function ($query) {
                        $query->select('id', 'name');
                    },
                    'group' => function ($query) {
                        $query->select('id', 'name', 'capacity', 'class_id');
                    },
                    'group.class' => function ($query) {
                        $query->select('id', 'name');
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->get();
                
            Log::info('Enrollments found for student', [
                'student_code' => $user->code,
                'enrollments_count' => $enrollments->count(),
                'semester_id' => $semesterId
            ]);
                
            // Get all semesters
            $semesters = Semester::where(function ($query) use ($user) {
                    $query->whereIn('id', function ($subQuery) use ($user) {
                        $subQuery->select('semester_id')
                            ->from('enrollments')
                            ->where('student_code', $user->code);
                    });
                })
                ->orWhere('is_active', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
            
            // Format enrollments data
            $formattedEnrollments = $enrollments->map(function($enrollment) {
                return [
                    'id' => $enrollment->id,
                    'unit' => [
                        'id' => $enrollment->unit->id ?? null,
                        'name' => $enrollment->unit->name ?? 'Unknown Unit',
                        'code' => $enrollment->unit->code ?? null,
                    ],
                    'semester' => [
                        'id' => $enrollment->semester->id ?? null,
                        'name' => $enrollment->semester->name ?? 'Unknown Semester',
                    ],
                    'group' => [
                        'id' => $enrollment->group->id ?? null,
                        'name' => $enrollment->group->name ?? 'Unknown Group',
                        'capacity' => $enrollment->group->capacity ?? 0,
                        'class' => [
                            'id' => $enrollment->group->class->id ?? null,
                            'name' => $enrollment->group->class->name ?? 'Unknown Class',
                        ]
                    ],
                    'created_at' => $enrollment->created_at,
                ];
            });
            
            $responseData = [
                'enrollments' => $formattedEnrollments,
                'semesters' => $semesters,
                'selectedSemester' => $semesterId,
                'user' => [
                    'code' => $user->code,
                    'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name ?? 'Student',
                    'email' => $user->email
                ]
            ];
            
            return Inertia::render('Student/Enrollments', $responseData);
            
        } catch (\Exception $e) {
            Log::error('Error in viewEnrollments (Spatie): ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()->withErrors([
                'error' => 'Failed to load enrollments. Please try again.'
            ]);
        }
    }

    public function getUnitsByClassAndSemester(Request $request)
{
    try {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'student_code' => 'sometimes|string',
        ]);

        $studentCode = $validated['student_code'] ?? Auth::user()->code;
        
        Log::info('=== FETCHING UNITS FOR CLASS ===', [
            'semester_id' => $validated['semester_id'],
            'class_id' => $validated['class_id'],
            'student_code' => $studentCode
        ]);

        // Get the class details
        $class = ClassModel::with(['program', 'school'])->find($validated['class_id']);
        
        if (!$class) {
            Log::error('Class not found', ['class_id' => $validated['class_id']]);
            return response()->json([
                'success' => false,
                'error' => 'Class not found',
                'units' => []
            ]);
        }

        Log::info('Class details', [
            'class' => $class->toArray()
        ]);

        // Start with all active units
        $query = Unit::where('is_active', true);

        // If class has a program, filter by program
        if ($class->program_id) {
            $query->where('program_id', $class->program_id);
            Log::info('Filtering by program_id', ['program_id' => $class->program_id]);
        }

        // If class has a school, filter by school  
        if ($class->school_id) {
            $query->where('school_id', $class->school_id);
            Log::info('Filtering by school_id', ['school_id' => $class->school_id]);
        }

        // Exclude units the student is already enrolled in for this semester
        $query->whereNotIn('id', function($subQuery) use ($studentCode, $validated) {
            $subQuery->select('unit_id')
                ->from('enrollments')
                ->where('student_code', $studentCode)
                ->where('semester_id', $validated['semester_id']);
        });

        $units = $query->with(['program', 'school'])
            ->select('id', 'name', 'code', 'program_id', 'school_id')
            ->orderBy('name')
            ->get();

        Log::info('Units found', [
            'total_units' => $units->count(),
            'units' => $units->pluck('name')->toArray()
        ]);

        // If no units found with program/school filter, get all active units
        if ($units->isEmpty()) {
            Log::info('No units found with filters, getting all active units');
            
            $units = Unit::where('is_active', true)
                ->whereNotIn('id', function($subQuery) use ($studentCode, $validated) {
                    $subQuery->select('unit_id')
                        ->from('enrollments')
                        ->where('student_code', $studentCode)
                        ->where('semester_id', $validated['semester_id']);
                })
                ->with(['program', 'school'])
                ->select('id', 'name', 'code', 'program_id', 'school_id')
                ->orderBy('name')
                ->get();
                
            Log::info('All active units found', [
                'total_units' => $units->count()
            ]);
        }

        $formattedUnits = $units->map(function($unit) {
            return [
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code ?? null,
                'program' => $unit->program ? $unit->program->name : null,
                'school' => $unit->school ? $unit->school->name : null,
            ];
        });

        return response()->json([
            'success' => true,
            'units' => $formattedUnits,
            'count' => $units->count(),
            'debug_info' => [
                'class_program_id' => $class->program_id,
                'class_school_id' => $class->school_id,
                'student_code' => $studentCode,
                'semester_id' => $validated['semester_id']
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Error fetching units: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'request' => $request->all()
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'Failed to fetch units: ' . $e->getMessage(),
            'units' => []
        ], 500);
    }
}
}