<?php

namespace App\Http\Controllers;

use App\Models\ClassTimetable;
use App\Models\Unit;
use App\Models\User;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\ClassTimeSlot;
use App\Models\Classroom;
use App\Models\ClassModel;
use App\Models\Group;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class ClassTimetableController extends Controller
{
    // Enhanced scheduling constraints
    private const MAX_PHYSICAL_PER_DAY = 2;
    private const MAX_ONLINE_PER_DAY = 2;
    private const MIN_HOURS_PER_DAY = 2;
    private const MAX_HOURS_PER_DAY = 5;
    private const REQUIRE_MIXED_MODE = true;
    private const AVOID_CONSECUTIVE_SLOTS = true;

    
    /**
     * ✅ REAL DATA: Display a listing of the resource with real group student counts
     */
    public function index(Request $request)
{
    $user = auth()->user();

    \Log::info('Accessing /classtimetable', [
        'user_id' => $user->id,
        'roles' => $user->getRoleNames(),
        'permissions' => $user->getAllPermissions()->pluck('name'),
        'all_params' => $request->all()
    ]);

    if (!$user->can('manage-classtimetables')) {
        abort(403, 'Unauthorized action.');
    }

    $perPage = $request->input('per_page', 100);
    $search = $request->input('search', '');

    // ✅ ENHANCED: Fetch class timetables with comprehensive search capabilities
    $classTimetables = ClassTimetable::query()
        ->leftJoin('units', 'class_timetable.unit_id', '=', 'units.id')
        ->leftJoin('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
        ->leftJoin('classes', 'class_timetable.class_id', '=', 'classes.id')
        ->leftJoin('groups', 'class_timetable.group_id', '=', 'groups.id')
        ->leftJoin('programs', 'class_timetable.program_id', '=', 'programs.id')
        ->leftJoin('schools', 'class_timetable.school_id', '=', 'schools.id')
        // ✅ Join users table using lecturer field from class_timetable
        ->leftJoin('users', 'users.code', '=', 'class_timetable.lecturer')
        ->select(
            'class_timetable.*',
            'units.code as unit_code',
            'units.name as unit_name',
            'semesters.name as semester_name',
            'programs.code as program_code',
            'programs.name as program_name',
            'schools.code as school_code',
            'schools.name as school_name',
            // ✅ ADD THESE MISSING FIELDS:
            DB::raw("CASE 
                WHEN classes.section IS NOT NULL AND classes.year_level IS NOT NULL 
                THEN CONCAT(classes.name, ' - Section ', classes.section, ' (Year ', classes.year_level, ')')
                WHEN classes.section IS NOT NULL 
                THEN CONCAT(classes.name, ' - Section ', classes.section)
                WHEN classes.year_level IS NOT NULL 
                THEN CONCAT(classes.name, ' (Year ', classes.year_level, ')')
                ELSE classes.name 
                END as class_name"),
            'classes.section as class_section',
            'classes.year_level as class_year_level',
            'groups.name as group_name',
            // ✅ Display lecturer full name
            DB::raw("CASE 
                WHEN users.id IS NOT NULL 
                THEN CONCAT(users.first_name, ' ', users.last_name) 
                ELSE class_timetable.lecturer 
                END as lecturer"),
            'class_timetable.lecturer as lecturer_code'
        )
        ->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('class_timetable.day', 'like', "%{$search}%")
                  ->orWhere('units.code', 'like', "%{$search}%")
                  ->orWhere('units.name', 'like', "%{$search}%")
                  ->orWhere('class_timetable.venue', 'like', "%{$search}%")
                  // ✅ ENHANCED: Add comprehensive class and section search
                  ->orWhere('classes.name', 'like', "%{$search}%")
                  ->orWhere('classes.section', 'like', "%{$search}%")
                  ->orWhere('classes.year_level', 'like', "%{$search}%")
                  // ✅ Search combined class display name
                  ->orWhere(DB::raw("CASE 
                      WHEN classes.section IS NOT NULL AND classes.year_level IS NOT NULL 
                      THEN CONCAT(classes.name, ' - Section ', classes.section, ' (Year ', classes.year_level, ')')
                      WHEN classes.section IS NOT NULL 
                      THEN CONCAT(classes.name, ' - Section ', classes.section)
                      WHEN classes.year_level IS NOT NULL 
                      THEN CONCAT(classes.name, ' (Year ', classes.year_level, ')')
                      ELSE classes.name 
                      END"), 'like', "%{$search}%")
                  // ✅ Search programs and schools
                  ->orWhere('programs.code', 'like', "%{$search}%")
                  ->orWhere('programs.name', 'like', "%{$search}%")
                  ->orWhere('schools.code', 'like', "%{$search}%")
                  ->orWhere('schools.name', 'like', "%{$search}%")
                  // ✅ Search groups
                  ->orWhere('groups.name', 'like', "%{$search}%")
                  // ✅ Search lecturers (both code and full name)
                  ->orWhere('users.first_name', 'like', "%{$search}%")
                  ->orWhere('users.last_name', 'like', "%{$search}%")
                  ->orWhere('class_timetable.lecturer', 'like', "%{$search}%")
                  ->orWhere(DB::raw("CONCAT(users.first_name, ' ', users.last_name)"), 'like', "%{$search}%")
                  // ✅ Search teaching mode
                  ->orWhere('class_timetable.teaching_mode', 'like', "%{$search}%")
                  // ✅ Search time ranges
                  ->orWhere('class_timetable.start_time', 'like', "%{$search}%")
                  ->orWhere('class_timetable.end_time', 'like', "%{$search}%");
            });
        })
        ->orderBy('class_timetable.day')
        ->orderBy('class_timetable.start_time')
        ->paginate($perPage);

    // ✅ ENHANCED: Fetch lecturers with full names for dropdown
    $lecturers = User::role('Lecturer')
        ->select('id', 'code', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
        ->get();

    $semesters = Semester::all();
    $classrooms = Classroom::all();
    $classtimeSlots = ClassTimeSlot::all();
    $allUnits = Unit::select('id', 'code', 'name', 'semester_id', 'credit_hours')->get();
    
    // ✅ ENHANCED: Classes with section and year level info
    $classes = ClassModel::select('id', 'name', 'section', 'year_level', 'program_id')
        ->get()
        ->map(function ($class) {
            $displayName = $class->name;
            if ($class->section) {
                $displayName .= ' - Section ' . $class->section;
            }
            if ($class->year_level) {
                $displayName .= ' (Year ' . $class->year_level . ')';
            }
            
            return [
                'id' => $class->id,
                'name' => $class->name,
                'display_name' => $displayName,
                'section' => $class->section,
                'year_level' => $class->year_level,
                'program_id' => $class->program_id
            ];
        });

    // ✅ REAL DATA: Fetch groups with actual student counts from enrollments table
    $groups = Group::select('id', 'name', 'class_id', 'capacity')
        ->get()
        ->map(function ($group) {
            $actualStudentCount = DB::table('enrollments')
                ->where('group_id', $group->id)
                ->distinct('student_code')
                ->count('student_code');

            return [
                'id' => $group->id,
                'name' => $group->name,
                'class_id' => $group->class_id,
                'student_count' => $actualStudentCount,
                'capacity' => $group->capacity
            ];
        });

    $programs = DB::table('programs')->select('id', 'code', 'name')->get();
    $schools = DB::table('schools')->select('id', 'name', 'code')->get();

    return Inertia::render('Admin/Classtimetable/Index', [
        'classTimetables' => $classTimetables,
        'lecturers' => $lecturers,
        'perPage' => $perPage,
        'search' => $search,
        'semesters' => $semesters,
        'classrooms' => $classrooms,
        'classtimeSlots' => $classtimeSlots,
        'units' => $allUnits,
        'enrollments' => [],
        'classes' => $classes,
        'groups' => $groups,
        'programs' => $programs,
        'schools' => $schools,
        'can' => [
            'create' => $user->can('create-classtimetables'),
            'edit' => $user->can('update-classtimetables'),
            'delete' => $user->can('delete-classtimetables'),
            'process' => $user->can('process-classtimetables'),
            'solve_conflicts' => $user->can('solve-class-conflicts'),
            'download' => $user->can('download-classtimetables'),
        ],
    ]);
}

    /**
     * ✅ FIXED: Get groups by class with CORRECT student counts - WORKING VERSION
     */
    public function getGroupsByClass(Request $request)
    {
        try {
            $classId = $request->input('class_id');
            $semesterId = $request->input('semester_id');
            $unitId = $request->input('unit_id');

            \Log::info('🔍 Fetching groups for class (ENHANCED VERSION)', [
                'class_id' => $classId,
                'semester_id' => $semesterId,
                'unit_id' => $unitId,
                'request_method' => $request->method(),
                'request_url' => $request->url()
            ]);

            if (!$classId) {
                \Log::error('❌ Class ID is missing from request');
                return response()->json(['error' => 'Class ID is required.'], 400);
            }

            $groups = Group::where('class_id', $classId)
                ->select('id', 'name', 'class_id', 'capacity')
                ->get();

            \Log::info('📊 Raw groups found', [
                'class_id' => $classId,
                'groups_count' => $groups->count(),
                'groups_data' => $groups->toArray()
            ]);

            if ($groups->isEmpty()) {
                \Log::warning('⚠️ No groups found for class', ['class_id' => $classId]);
                return response()->json([]);
            }

            $groupsWithStudentCounts = $groups->map(function ($group) use ($semesterId, $unitId) {
                $enrollmentQuery = DB::table('enrollments')
                    ->where('group_id', $group->id);

                if ($unitId && $semesterId) {
                    $enrollmentQuery->where('unit_id', $unitId)
                                  ->where('semester_id', $semesterId);
                    $context = "unit {$unitId} in semester {$semesterId}";
                } elseif ($semesterId) {
                    $enrollmentQuery->where('semester_id', $semesterId);
                    $context = "semester {$semesterId}";
                } else {
                    $context = "all enrollments";
                }

                $actualStudentCount = $enrollmentQuery
                    ->distinct('student_code')
                    ->count('student_code');

                \Log::info('👥 Student count calculated', [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'context' => $context,
                    'student_count' => $actualStudentCount,
                    'capacity' => $group->capacity
                ]);

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'class_id' => $group->class_id,
                    'student_count' => $actualStudentCount,
                    'capacity' => $group->capacity,
                    'context' => $context
                ];
            });

            \Log::info('✅ Groups with student counts prepared (ENHANCED)', [
                'class_id' => $classId,
                'total_groups' => $groupsWithStudentCounts->count(),
                'groups_summary' => $groupsWithStudentCounts->map(function($g) {
                    return [
                        'id' => $g['id'],
                        'name' => $g['name'], 
                        'student_count' => $g['student_count']
                    ];
                })->toArray()
            ]);

            return response()->json($groupsWithStudentCounts->values()->toArray());

        } catch (\Exception $e) {
            \Log::error('❌ Error in getGroupsByClass (ENHANCED VERSION)', [
                'class_id' => $request->input('class_id'),
                'semester_id' => $request->input('semester_id'),
                'unit_id' => $request->input('unit_id'),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch groups with student counts.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

      /**
     * ✅ FIXED: API endpoint to get units by class and semester
     */
    // In ClassTimetableController.php - getUnitsByClass method
public function getUnitsByClass(Request $request)
{
    try {
        $classId = $request->input('class_id');
        $semesterId = $request->input('semester_id');

        if (!$classId || !$semesterId) {
            return response()->json(['error' => 'Class ID and Semester ID are required.'], 400);
        }

        // ✅ FIXED: Proper join to get lecturer names
        $units = DB::table('unit_assignments')
            ->join('units', 'unit_assignments.unit_id', '=', 'units.id')
            ->leftJoin('users', 'users.code', '=', 'unit_assignments.lecturer_code') // Join users table
            ->where('unit_assignments.semester_id', $semesterId)
            ->where('unit_assignments.class_id', $classId)
            ->select(
                'units.id',
                'units.code',
                'units.name',
                'units.credit_hours',
                'unit_assignments.lecturer_code',
                // ✅ Get full lecturer name
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

        // If no units found with assignments, try fallback
        if ($units->isEmpty()) {
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

        // ✅ Enhanced units with real enrollment data and lecturer names
        $enhancedUnits = $units->map(function ($unit) use ($semesterId, $classId) {
            $enrollmentCount = DB::table('enrollments')
                ->where('unit_id', $unit->id)
                ->where('semester_id', $semesterId)
                ->where('class_id', $classId)
                ->count();

            return [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'credit_hours' => $unit->credit_hours ?? 3,
                'student_count' => $enrollmentCount,
                'lecturer_name' => $unit->lecturer_name ?? 'No lecturer assigned', // ✅ Full name
                'lecturer_code' => $unit->lecturer_code ?? '',
                'lecturer_first_name' => $unit->lecturer_first_name ?? '',
                'lecturer_last_name' => $unit->lecturer_last_name ?? '',
            ];
        });

        return response()->json($enhancedUnits->values()->all());
        
    } catch (\Exception $e) {
        \Log::error('Error fetching units: ' . $e->getMessage());
        return response()->json(['error' => 'Failed to fetch units.'], 500);
    }
}

    public function getGroupsByClassWithCounts(Request $request)
    {
        try {
            $classId = $request->input('class_id');
            $semesterId = $request->input('semester_id');
            $unitId = $request->input('unit_id');

            if (!$classId) {
                return response()->json(['error' => 'Class ID is required.'], 400);
            }

            \Log::info('Fetching groups with REAL student counts from database', [
                'class_id' => $classId,
                'semester_id' => $semesterId,
                'unit_id' => $unitId
            ]);

            $groups = Group::where('class_id', $classId)
                ->select('id', 'name', 'class_id')
                ->get()
                ->map(function ($group) use ($semesterId, $unitId) {
                    // ✅ REAL DATA: Calculate actual student count from enrollments table
                    $enrollmentQuery = DB::table('enrollments')
                        ->where('group_id', $group->id);

                    // Add filters based on context
                    if ($unitId && $semesterId) {
                        // Most specific: count for this specific unit and semester
                        $enrollmentQuery->where('unit_id', $unitId)
                                      ->where('semester_id', $semesterId);
                        $context = "unit {$unitId} in semester {$semesterId}";
                    } elseif ($semesterId) {
                        // Semester specific: count for this semester only
                        $enrollmentQuery->where('semester_id', $semesterId);
                        $context = "semester {$semesterId}";
                    } else {
                        // General: total count for this group across all semesters
                        $context = "all semesters";
                    }

                    $actualStudentCount = $enrollmentQuery->count();

                    // ✅ DEBUG: Log the actual query and result
                    \Log::info('Real student count calculated', [
                        'group_id' => $group->id,
                        'group_name' => $group->name,
                        'context' => $context,
                        'actual_count' => $actualStudentCount,
                        'query_conditions' => [
                            'group_id' => $group->id,
                            'unit_id' => $unitId,
                            'semester_id' => $semesterId
                        ]
                    ]);

                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'class_id' => $group->class_id,
                        'student_count' => $actualStudentCount, // ✅ REAL DATA from database
                        'context' => $context // For debugging
                    ];
                });

            \Log::info('Groups with REAL student counts retrieved', [
                'class_id' => $classId,
                'groups_count' => $groups->count(),
                'groups_data' => $groups->toArray()
            ]);

            return response()->json($groups);

        } catch (\Exception $e) {
            \Log::error('Error fetching groups with real student counts: ' . $e->getMessage(), [
                'class_id' => $request->input('class_id'),
                'semester_id' => $request->input('semester_id'),
                'unit_id' => $request->input('unit_id'),
                'exception' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Failed to fetch groups with real student counts.'], 500);
        }
    }

    /**
     * ✅ NEW: Debug endpoint to check data relationships
     */
    public function debugClassData(Request $request)
    {
        try {
            $classId = $request->input('class_id', 1); // Default to class 1 for testing

            $debug = [
                'class_id' => $classId,
                'groups_for_class' => Group::where('class_id', $classId)->get()->toArray(),
                'all_groups_sample' => Group::take(10)->get()->toArray(),
                'enrollments_sample' => DB::table('enrollments')
                    ->leftJoin('groups', 'enrollments.group_id', '=', 'groups.id')
                    ->where('groups.class_id', $classId)
                    ->select('enrollments.*', 'groups.name as group_name', 'groups.class_id')
                    ->take(10)
                    ->get()
                    ->toArray(),
                'enrollment_counts_by_group' => DB::table('enrollments')
                    ->leftJoin('groups', 'enrollments.group_id', '=', 'groups.id')
                    ->where('groups.class_id', $classId)
                    ->select(
                        'enrollments.group_id',
                        'groups.name as group_name',
                        'groups.class_id',
                        DB::raw('COUNT(DISTINCT enrollments.student_code) as student_count')
                    )
                    ->groupBy('enrollments.group_id', 'groups.name', 'groups.class_id')
                    ->get()
                    ->toArray(),
                'database_info' => [
                    'total_groups' => Group::count(),
                    'total_enrollments' => DB::table('enrollments')->count(),
                    'groups_with_class_1' => Group::where('class_id', 1)->count(),
                    'groups_with_class_2' => Group::where('class_id', 2)->count(),
                ]
            ];

            return response()->json($debug);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'day' => 'nullable|string',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'group_id' => 'nullable|exists:groups,id',
            'venue' => 'nullable|string',
            'location' => 'nullable|string',
            'no' => 'required|integer|min:1',
            'lecturer' => 'required|string', // This should be the full name
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'teaching_mode' => 'nullable|in:physical,online',
            'program_id' => 'nullable|exists:programs,id',
            'school_id' => 'nullable|exists:schools,id',
            'classtimeslot_id' => 'nullable|integer',
        ]);

        try {
            \Log::info('Creating class timetable with lecturer name:', [
                'lecturer_received' => $request->lecturer,
                'request_data' => $request->all()
            ]);

            $unit = Unit::findOrFail($request->unit_id);
            $class = ClassModel::find($request->class_id);
            $programId = $request->program_id ?: ($class ? $class->program_id : null);
            $schoolId = $request->school_id ?: ($class ? $class->school_id : null);

            // ✅ FIXED: Handle lecturer - check if it's a name or code
            $lecturerToStore = $request->lecturer;
            
            // If the lecturer field contains a full name, try to find the corresponding code
            $lecturer = User::where(DB::raw("CONCAT(first_name, ' ', last_name)"), $request->lecturer)->first();
            if ($lecturer) {
                $lecturerToStore = $lecturer->code; // Store the code in the database
                \Log::info('Found lecturer code for name', [
                    'lecturer_name' => $request->lecturer,
                    'lecturer_code' => $lecturer->code
                ]);
            } else {
                // If not found as a full name, store as is (might be a code or external lecturer)
                \Log::info('Lecturer not found in users table, storing as provided', [
                    'lecturer' => $request->lecturer
                ]);
            }

            // Determine teaching mode based on duration if time slot is provided
            $teachingMode = $request->teaching_mode;
            if ($request->start_time && $request->end_time) {
                $duration = \Carbon\Carbon::parse($request->start_time)
                    ->diffInHours(\Carbon\Carbon::parse($request->end_time));
                $teachingMode = $duration >= 2 ? 'physical' : 'online';
            }

            // Auto-assign venue based on teaching mode
            $venue = $request->venue;
            $location = $request->location;
            
            if (!$venue) {
                if ($teachingMode === 'online') {
                    $venue = 'Remote';
                    $location = 'Online';
                } else {
                    $suitableClassroom = Classroom::where('capacity', '>=', $request->no)
                        ->orderBy('capacity', 'asc')
                        ->first();
                    $venue = $suitableClassroom ? $suitableClassroom->name : 'TBD';
                    $location = $suitableClassroom ? $suitableClassroom->location : 'Physical';
                }
            }

            $classTimetable = ClassTimetable::create([
                'day' => $request->day,
                'unit_id' => $unit->id,
                'semester_id' => $request->semester_id,
                'class_id' => $request->class_id,
                'group_id' => $request->group_id ?: null,
                'venue' => $venue,
                'location' => $location,
                'no' => $request->no,
                'lecturer' => $lecturerToStore, // Store the code, but display will show name
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'teaching_mode' => $teachingMode,
                'program_id' => $programId,
                'school_id' => $schoolId,
            ]);

            \Log::info('Class timetable created successfully with lecturer handling', [
                'timetable_id' => $classTimetable->id,
                'unit_code' => $unit->code,
                'lecturer_stored' => $lecturerToStore,
                'lecturer_received' => $request->lecturer,
                'teaching_mode' => $teachingMode,
                'venue' => $venue,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Class timetable created successfully.',
                    'data' => $classTimetable->fresh()
                ]);
            }

            return redirect()->back()->with('success', 'Class timetable created successfully.');

        } catch (\Exception $e) {
            \Log::error('Failed to create class timetable: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e->getTraceAsString()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create class timetable: ' . $e->getMessage(),
                    'errors' => ['error' => $e->getMessage()]
                ], 500);
            }

            return redirect()->back()
                ->withErrors(['error' => 'Failed to create class timetable: ' . $e->getMessage()])
                ->withInput();
        }
    }
    /**
     * Create a single timetable entry
     */
    private function createSingleTimetable(Request $request, Unit $unit, $programId, $schoolId)
    {
        // ✅ ENHANCED: Implement duration-based teaching mode assignment
        $teachingMode = $this->determineDurationBasedTeachingMode($request->start_time, $request->end_time, $request->teaching_mode);

        // ✅ ENHANCED: Auto-assign venue based on teaching mode
        $venueData = $this->determineVenueBasedOnMode($request->venue, $teachingMode, $request->no);

        // ✅ NEW: Check for conflicts before creating
        $conflictCheck = $this->checkCreateConflicts($request->day, $request->start_time, $request->end_time, $request->lecturer, $venueData['venue'], $teachingMode);
    
        if (!$conflictCheck['success']) {
            \Log::warning('Creation blocked due to conflicts', [
                'conflicts' => $conflictCheck['conflicts']
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Creation blocked due to conflicts: ' . $conflictCheck['message'],
                    'conflicts' => $conflictCheck['conflicts']
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['conflict' => 'Creation blocked due to conflicts: ' . $conflictCheck['message']])
                ->withInput();
        }

        $classTimetable = ClassTimetable::create([
            'day' => $request->day,
            'unit_id' => $unit->id,
            'semester_id' => $request->semester_id,
            'class_id' => $request->class_id,
            'group_id' => $request->group_id ?: null,
            'venue' => $venueData['venue'],
            'location' => $venueData['location'],
            'no' => $request->no,
            'lecturer' => $request->lecturer,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'teaching_mode' => $teachingMode,
            'program_id' => $programId,
            'school_id' => $schoolId,
        ]);

        \Log::info('Single timetable entry created', [
            'timetable_id' => $classTimetable->id,
            'unit_code' => $unit->code,
            'day' => $request->day,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'venue' => $venueData['venue'],
            'teaching_mode' => $teachingMode,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Class timetable created successfully with duration-based teaching mode.',
                'data' => $classTimetable->fresh()
            ]);
        }

        return redirect()->back()->with('success', 'Class timetable created successfully with duration-based teaching mode.');
    }

    /**
     * Create multiple timetable entries based on credit hours
     */
    private function createCreditBasedTimetable(Request $request, Unit $unit, $programId, $schoolId)
    {
        $creditHours = $unit->credit_hours;
        $sessions = $this->getSessionsForCredits($creditHours);
        
        \Log::info('Creating credit-based timetable', [
            'unit_code' => $unit->code,
            'credit_hours' => $creditHours,
            'sessions' => $sessions
        ]);

        $createdTimetables = [];
        $errors = [];

        foreach ($sessions as $sessionIndex => $session) {
            try {
                $sessionResult = $this->createSessionTimetable($request, $unit, $session, $sessionIndex + 1, $programId, $schoolId);
                
                if ($sessionResult['success']) {
                    $createdTimetables[] = $sessionResult['timetable'];
                } else {
                    $errors[] = $sessionResult['message'];
                }
            } catch (\Exception $e) {
                $errors[] = "Session " . ($sessionIndex + 1) . ": " . $e->getMessage();
                \Log::error('Failed to create session timetable', [
                    'session' => $session,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if (count($createdTimetables) === 0) {
            $errorMessage = 'Failed to create any timetable sessions. Errors: ' . implode('; ', $errors);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => ['scheduling' => $errorMessage]
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['scheduling' => $errorMessage])
                ->withInput();
        }

        $successMessage = count($createdTimetables) . " timetable sessions created successfully for {$unit->code} ({$creditHours} credit hours).";
        
        if (count($errors) > 0) {
            $successMessage .= " Note: " . count($errors) . " sessions had issues.";
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'data' => $createdTimetables,
                'errors' => $errors
            ]);
        }

        return redirect()->back()->with('success', $successMessage);
    }

    /**
     * ✅ UPDATED: Create a single session timetable with group teaching mode balancing
     */
    private function createSessionTimetable(Request $request, Unit $unit, array $session, int $sessionNumber, $programId, $schoolId)
    {
        $sessionType = $session['type'];
        $requiredDuration = $session['duration']; // 1 or 2 hours
        
        \Log::info("Creating session {$sessionNumber}", [
            'unit_code' => $unit->code,
            'session_type' => $sessionType,
            'required_duration' => $requiredDuration,
            'group_id' => $request->group_id
        ]);
        
        // ✅ NEW: Check existing teaching modes for this group on each day
        $balancedSessionType = $this->getBalancedTeachingMode($request->group_id, $sessionType);
        
        \Log::info("Teaching mode after balancing", [
            'original_type' => $sessionType,
            'balanced_type' => $balancedSessionType,
            'group_id' => $request->group_id
        ]);
        
        // Get appropriate time slot for this session with the balanced teaching mode
        $timeSlotResult = $this->assignRandomTimeSlot($request->lecturer, '', null, $balancedSessionType, $requiredDuration, $request->group_id);
        
        if (!$timeSlotResult['success']) {
            return [
                'success' => false,
                'message' => "Session {$sessionNumber} ({$balancedSessionType}, {$requiredDuration}h): " . $timeSlotResult['message']
            ];
        }

        $day = $timeSlotResult['day'];
        $startTime = $timeSlotResult['start_time'];
        $endTime = $timeSlotResult['end_time'];
        $actualDuration = $timeSlotResult['duration'] ?? $requiredDuration;

        // ✅ ENHANCED: Determine teaching mode based on duration
        $sessionTeachingMode = $this->determineDurationBasedTeachingMode($startTime, $endTime, $sessionType);

        // ✅ ENHANCED: Get appropriate venue based on teaching mode
        $venueResult = $this->assignRandomVenue(
            $request->no, 
            $day, 
            $startTime, 
            $endTime, 
            $sessionTeachingMode,  // Use duration-based mode
            $request->class_id,
            $request->group_id
        );

        if (!$venueResult['success']) {
            return [
                'success' => false,
                'message' => "Session {$sessionNumber} ({$sessionTeachingMode}, {$requiredDuration}h): " . $venueResult['message']
            ];
        }

        $venue = $venueResult['venue'];
        $location = $venueResult['location'];
        $teachingMode = $sessionTeachingMode; // Use duration-based mode

        // Double-check for conflicts
        $lecturerConflict = ClassTimetable::where('day', $day)
            ->where('lecturer', $request->lecturer)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                });
            })
            ->exists();

        if ($lecturerConflict) {
            return [
                'success' => false,
                'message' => "Session {$sessionNumber} ({$sessionTeachingMode}, {$requiredDuration}h): Lecturer conflict detected for {$day} {$startTime}-{$endTime}"
            ];
        }

        // Create the timetable entry
        $classTimetable = ClassTimetable::create([
            'day' => $day,
            'unit_id' => $unit->id,
            'semester_id' => $request->semester_id,
            'class_id' => $request->class_id,
            'group_id' => $request->group_id ?: null,
            'venue' => $venue,
            'location' => $location,
            'no' => $request->no,
            'lecturer' => $request->lecturer,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'teaching_mode' => $teachingMode,
            'program_id' => $programId,
            'school_id' => $schoolId,
        ]);

        \Log::info("Session {$sessionNumber} created successfully", [
            'timetable_id' => $classTimetable->id,
            'unit_code' => $unit->code,
            'session_type' => $sessionTeachingMode,
            'day' => $day,
            'time' => "{$startTime}-{$endTime}",
            'duration' => $actualDuration,
            'venue' => $venue,
            'teaching_mode' => $teachingMode
        ]);

        return [
            'success' => true,
            'message' => "Session {$sessionNumber} ({$sessionTeachingMode}, {$actualDuration}h) created successfully",
            'timetable' => $classTimetable,
            'duration' => $actualDuration
        ];
    }

    /**
     * Get session configuration based on credit hours
     */
    private function getSessionsForCredits($creditHours)
    {
        $sessions = [];

        // Assign 1 physical session of 2 hours if possible
        if ($creditHours >= 2) {
            $sessions[] = ['type' => 'physical', 'duration' => 2];
            $remaining = $creditHours - 2;
        } else {
            $remaining = $creditHours;
        }

        // Assign the remaining hours as 1-hour online sessions
        while ($remaining > 0) {
            $sessions[] = ['type' => 'online', 'duration' => 1];
            $remaining--;
        }

        return $sessions;
    }

    /**
     * ✅ UPDATED: Get balanced teaching mode with daily limits (max 2 physical classes, max 5 hours per day)
     */
    private function getBalancedTeachingMode($groupId, $preferredType)
    {
        if (!$groupId) {
            return $preferredType; // No group specified, use preferred type
        }

        try {
            // Get all days of the week
            $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        
            $balancedType = $preferredType;
            $availableDays = [];
        
            // Check each day for constraints and balance needs
            foreach ($daysOfWeek as $day) {
                $dayAnalysis = $this->analyzeDayConstraints($groupId, $day);
            
                if ($dayAnalysis['can_add_physical'] || $dayAnalysis['can_add_online']) {
                    $availableDays[$day] = $dayAnalysis;
                }
            }
        
            if (empty($availableDays)) {
                \Log::warning("No available days found for group", [
                    'group_id' => $groupId,
                    'preferred_type' => $preferredType
                ]);
                return $preferredType; // Fallback, though this might fail later
            }
        
            // Find the best day and teaching mode based on constraints and balance
            $bestOption = $this->findBestTeachingOption($availableDays, $preferredType);
        
            \Log::info("Selected teaching mode after constraint analysis", [
                'group_id' => $groupId,
                'preferred_type' => $preferredType,
                'selected_type' => $bestOption['type'],
                'selected_day' => $bestOption['day'],
                'reason' => $bestOption['reason']
            ]);
        
            return $bestOption['type'];
        
        } catch (\Exception $e) {
            \Log::error('Error in getBalancedTeachingMode: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'preferred_type' => $preferredType
            ]);
        
            return $preferredType;
        }
    }

    /**
     * ✅ NEW: Analyze daily constraints for a specific group and day
     */
    private function analyzeDayConstraints($groupId, $day)
    {
        // Get existing classes for this group on this day
        $existingClasses = ClassTimetable::where('group_id', $groupId)
            ->where('day', $day)
            ->select('teaching_mode', 'start_time', 'end_time')
            ->get();
    
        // Count physical classes
        $physicalCount = $existingClasses->where('teaching_mode', 'physical')->count();
    
        // Calculate total hours for the day
        $totalHours = 0;
        foreach ($existingClasses as $class) {
            $startTime = \Carbon\Carbon::parse($class->start_time);
            $endTime = \Carbon\Carbon::parse($class->end_time);
            $totalHours += $startTime->diffInHours($endTime);
        }
    
        // Check constraints
        $canAddPhysical = $physicalCount < 2; // Max 2 physical classes per day
        $canAddOnline = true; // Online classes have no specific limit beyond total hours
    
        // Check total hours constraint (assuming new class will be 1-2 hours)
        $maxNewClassHours = 2; // Assume worst case for checking
        $canAddAnyClass = ($totalHours + $maxNewClassHours) <= 5;
    
        if (!$canAddAnyClass) {
            $canAddPhysical = false;
            $canAddOnline = false;
        }
    
        // Determine balance needs
        $hasPhysical = $existingClasses->where('teaching_mode', 'physical')->isNotEmpty();
        $hasOnline = $existingClasses->where('teaching_mode', 'online')->isNotEmpty();
    
        $needsBalance = false;
        $preferredForBalance = null;
    
        if ($hasPhysical && !$hasOnline && $canAddOnline) {
            $needsBalance = true;
            $preferredForBalance = 'online';
        } elseif ($hasOnline && !$hasPhysical && $canAddPhysical) {
            $needsBalance = true;
            $preferredForBalance = 'physical';
        }
    
        return [
            'day' => $day,
            'existing_classes' => $existingClasses->count(),
            'physical_count' => $physicalCount,
            'total_hours' => $totalHours,
            'remaining_hours' => 5 - $totalHours,
            'can_add_physical' => $canAddPhysical,
            'can_add_online' => $canAddOnline,
            'needs_balance' => $needsBalance,
            'preferred_for_balance' => $preferredForBalance,
            'has_physical' => $hasPhysical,
            'has_online' => $hasOnline
        ];
    }

    /**
     * ✅ NEW: Find the best teaching option based on constraints and preferences
     */
    private function findBestTeachingOption($availableDays, $preferredType)
    {
        $bestOption = [
            'type' => $preferredType,
            'day' => null,
            'reason' => 'fallback'
        ];
    
        // Priority 1: Days that need balance and can accommodate the needed type
        foreach ($availableDays as $day => $analysis) {
            if ($analysis['needs_balance'] && $analysis['preferred_for_balance']) {
                if ($analysis['preferred_for_balance'] === 'physical' && $analysis['can_add_physical']) {
                    return [
                        'type' => 'physical',
                        'day' => $day,
                        'reason' => 'balance_needed_physical'
                    ];
                } elseif ($analysis['preferred_for_balance'] === 'online' && $analysis['can_add_online']) {
                    return [
                        'type' => 'online',
                        'day' => $day,
                        'reason' => 'balance_needed_online'
                    ];
                }
            }
        }
    
        // Priority 2: Days that can accommodate preferred type
        foreach ($availableDays as $day => $analysis) {
            if ($preferredType === 'physical' && $analysis['can_add_physical']) {
                return [
                    'type' => 'physical',
                    'day' => $day,
                    'reason' => 'preferred_type_available'
                ];
            } elseif ($preferredType === 'online' && $analysis['can_add_online']) {
                return [
                    'type' => 'online',
                    'day' => $day,
                    'reason' => 'preferred_type_available'
                ];
            }
        }
    
        // Priority 3: Any available option
        foreach ($availableDays as $day => $analysis) {
            if ($analysis['can_add_physical']) {
                return [
                    'type' => 'physical',
                    'day' => $day,
                    'reason' => 'any_available_physical'
                ];
            } elseif ($analysis['can_add_online']) {
                return [
                    'type' => 'online',
                    'day' => $day,
                    'reason' => 'any_available_online'
                ];
            }
        }
    
        return $bestOption;
    }

    /**
     * ✅ UPDATED: Assign a random time slot with constraint validation
     */
    private function assignRandomTimeSlot($lecturer, $venue = '', $preferredDay = null, $preferredMode = null, $requiredDuration = 1, $groupId = null)
    {
        try {
            \Log::info('Assigning time slot with constraints', [
                'lecturer' => $lecturer,
                'preferred_mode' => $preferredMode,
                'required_duration' => $requiredDuration,
                'preferred_day' => $preferredDay,
                'group_id' => $groupId
            ]);

            // Get time slots based on required duration
            $availableTimeSlots = collect();
        
            if ($requiredDuration == 2) {
                $twoHourSlots = DB::table('class_time_slots')
                    ->whereRaw('TIMESTAMPDIFF(HOUR, start_time, end_time) = 2')
                    ->when($preferredDay, function ($query) use ($preferredDay) {
                        $query->where('day', $preferredDay);
                    })
                    ->get();
                
                $availableTimeSlots = $twoHourSlots;
            
                if ($availableTimeSlots->isEmpty()) {
                    \Log::warning('No 2-hour slots found, trying any available slots');
                    $availableTimeSlots = DB::table('class_time_slots')
                        ->when($preferredDay, function ($query) use ($preferredDay) {
                            $query->where('day', $preferredDay);
                        })
                        ->get();
                }
            } else {
                $oneHourSlots = DB::table('class_time_slots')
                    ->whereRaw('TIMESTAMPDIFF(HOUR, start_time, end_time) = 1')
                    ->when($preferredDay, function ($query) use ($preferredDay) {
                        $query->where('day', $preferredDay);
                    })
                    ->get();
                
                if ($oneHourSlots->isNotEmpty()) {
                    $availableTimeSlots = $oneHourSlots;
                } else {
                    $availableTimeSlots = DB::table('class_time_slots')
                        ->when($preferredDay, function ($query) use ($preferredDay) {
                            $query->where('day', $preferredDay);
                        })
                        ->get();
                }
            }

            if ($availableTimeSlots->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "No time slots available for {$requiredDuration}-hour sessions."
                ];
            }

            // Filter slots based on all constraints
            $validTimeSlots = $availableTimeSlots->filter(function ($slot) use ($lecturer, $venue, $preferredMode, $requiredDuration, $groupId) {
                // Check lecturer conflicts
                $lecturerConflict = ClassTimetable::where('day', $slot->day)
                    ->where('lecturer', $lecturer)
                    ->where(function ($query) use ($slot) {
                        $query->where(function ($q) use ($slot) {
                            $q->where('start_time', '<', $slot->end_time)
                              ->where('end_time', '>', $slot->start_time);
                        });
                    })
                    ->exists();

                if ($lecturerConflict) {
                    return false;
                }

                // Check venue conflicts (if venue is specified and not online)
                if (!empty($venue) && strtolower(trim($venue)) !== 'remote') {
                    $venueConflict = ClassTimetable::where('day', $slot->day)
                        ->where('venue', $venue)
                        ->where(function ($query) use ($slot) {
                            $query->where(function ($q) use ($slot) {
                                $q->where('start_time', '<', $slot->end_time)
                                  ->where('end_time', '>', $slot->start_time);
                            });
                        })
                        ->exists();

                    if ($venueConflict) {
                        return false;
                    }
                }

                // ✅ NEW: Check group constraints
                if ($groupId) {
                    $slotDuration = \Carbon\Carbon::parse($slot->start_time)
                        ->diffInHours(\Carbon\Carbon::parse($slot->end_time));
                
                    if (!$this->canAddClassToGroupDay($groupId, $slot->day, $preferredMode, $slotDuration)) {
                        return false;
                    }
                }

                return true;
            });

            if ($validTimeSlots->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "No available {$requiredDuration}-hour time slots that meet all constraints for lecturer {$lecturer}."
                ];
            }

            // Randomly select from valid time slots
            $selectedTimeSlot = $validTimeSlots->random();

            // Calculate actual duration
            $actualDuration = \Carbon\Carbon::parse($selectedTimeSlot->start_time)
                ->diffInHours(\Carbon\Carbon::parse($selectedTimeSlot->end_time));

            \Log::info('Time slot assigned successfully with all constraints', [
                'day' => $selectedTimeSlot->day,
                'start_time' => $selectedTimeSlot->start_time,
                'end_time' => $selectedTimeSlot->end_time,
                'required_duration' => $requiredDuration,
                'actual_duration' => $actualDuration,
                'lecturer' => $lecturer,
                'preferred_mode' => $preferredMode,
                'group_id' => $groupId
            ]);

            return [
                'success' => true,
                'day' => $selectedTimeSlot->day,
                'start_time' => $selectedTimeSlot->start_time,
                'end_time' => $selectedTimeSlot->end_time,
                'duration' => $actualDuration,
                'message' => "Assigned: {$selectedTimeSlot->day} {$selectedTimeSlot->start_time}-{$selectedTimeSlot->end_time} ({$actualDuration}h)"
            ];
        } catch (\Exception $e) {
            \Log::error('Error in random time slot assignment: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to assign random time slot: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ NEW: Check if a class can be added to a group's day based on constraints
     */
    private function canAddClassToGroupDay($groupId, $day, $teachingMode, $classDuration)
    {
        $analysis = $this->analyzeDayConstraints($groupId, $day);
    
        // Check total hours constraint
        if (($analysis['total_hours'] + $classDuration) > 5) {
            \Log::info("Cannot add class - would exceed 5-hour daily limit", [
                'group_id' => $groupId,
                'day' => $day,
                'current_hours' => $analysis['total_hours'],
                'class_duration' => $classDuration,
                'would_total' => $analysis['total_hours'] + $classDuration
            ]);
            return false;
        }
    
        // Check physical class limit
        if ($teachingMode === 'physical' && !$analysis['can_add_physical']) {
            \Log::info("Cannot add physical class - would exceed 2 physical classes per day limit", [
                'group_id' => $groupId,
                'day' => $day,
                'current_physical_count' => $analysis['physical_count']
            ]);
            return false;
        }
    
        return true;
    }

    /**
     * ✅ ENHANCED: Assign random venue with upfront conflict filtering
     */
    private function assignRandomVenue($studentCount, $day, $startTime, $endTime, $preferredMode = null, $classId = null, $groupId = null)
    {
        try {
            \Log::info('Assigning conflict-free venue', [
                'student_count' => $studentCount,
                'day' => $day,
                'time' => "{$startTime}-{$endTime}",
                'preferred_mode' => $preferredMode,
                'class_id' => $classId,
                'group_id' => $groupId
            ]);

            // Step 1: Get all classrooms with sufficient capacity
            $baseClassrooms = Classroom::where('capacity', '>=', $studentCount)->get();

            if ($baseClassrooms->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "No venues available with sufficient capacity for {$studentCount} students."
                ];
            }

            // Step 2: Pre-filter by preferred teaching mode
            $modeFilteredClassrooms = $this->filterVenuesByMode($baseClassrooms, $preferredMode);

            // Step 3: Pre-filter to remove venues with time conflicts
            $timeConflictFreeVenues = $this->filterVenuesForTimeAvailability($modeFilteredClassrooms, $day, $startTime, $endTime);

            if ($timeConflictFreeVenues->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "No venues available without time conflicts for {$day} {$startTime}-{$endTime}."
                ];
            }

            // Step 4: Pre-filter to remove venues with class conflicts (if same class)
            $classConflictFreeVenues = $this->filterVenuesForClassAvailability($timeConflictFreeVenues, $classId, $day, $startTime, $endTime);

            // Use class-conflict-free venues if available, otherwise fall back to time-conflict-free venues
            $finalVenues = $classConflictFreeVenues->isNotEmpty() ? $classConflictFreeVenues : $timeConflictFreeVenues;

            // Step 5: Randomly select from pre-filtered, conflict-free venues
            $selectedClassroom = $finalVenues->random();
            $venueInfo = $this->determineTeachingModeAndLocation($selectedClassroom->name);

            \Log::info('Conflict-free venue assigned successfully', [
                'venue' => $selectedClassroom->name,
                'capacity' => $selectedClassroom->capacity,
                'student_count' => $studentCount,
                'teaching_mode' => $venueInfo['teaching_mode'],
                'location' => $venueInfo['location'],
                'day' => $day,
                'time' => "{$startTime}-{$endTime}",
                'total_available_venues' => $finalVenues->count(),
                'filtering_stages' => [
                    'base_venues' => $baseClassrooms->count(),
                    'mode_filtered' => $modeFilteredClassrooms->count(),
                    'time_conflict_free' => $timeConflictFreeVenues->count(),
                    'class_conflict_free' => $classConflictFreeVenues->count()
                ]
            ]);

            return [
                'success' => true,
                'venue' => $selectedClassroom->name,
                'location' => $venueInfo['location'],
                'teaching_mode' => $venueInfo['teaching_mode'],
                'message' => "Conflict-free {$venueInfo['teaching_mode']} venue '{$selectedClassroom->name}' assigned successfully."
            ];

        } catch (\Exception $e) {
            \Log::error('Error in conflict-free venue assignment: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to assign conflict-free venue: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ NEW: Filter venues by teaching mode preference
     */
    private function filterVenuesByMode($classrooms, $preferredMode = null)
    {
        if (!$preferredMode) {
            return $classrooms; // No mode filtering if no preference specified
        }

        $filtered = $classrooms->filter(function ($classroom) use ($preferredMode) {
            $venueInfo = $this->determineTeachingModeAndLocation($classroom->name);
            return $venueInfo['teaching_mode'] === $preferredMode;
        });

        // If no venues match the preferred mode, fall back to all venues
        return $filtered->isNotEmpty() ? $filtered : $classrooms;
    }

    /**
     * ✅ NEW: Filter venues to remove time conflicts upfront
     */
    private function filterVenuesForTimeAvailability($classrooms, $day, $startTime, $endTime)
    {
        return $classrooms->filter(function ($classroom) use ($day, $startTime, $endTime) {
            $venueInfo = $this->determineTeachingModeAndLocation($classroom->name);

            // Online venues (Remote) can handle multiple sessions simultaneously
            if ($venueInfo['teaching_mode'] === 'online') {
                return true;
            }

            // Physical venues need conflict checking
            $hasConflict = ClassTimetable::where('venue', $classroom->name)
                ->where('day', $day)
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<', $endTime)
                          ->where('end_time', '>', $startTime);
                    });
                })
                ->exists();

            if ($hasConflict) {
                \Log::debug('Venue time conflict detected', [
                    'venue' => $classroom->name,
                    'day' => $day,
                    'time_slot' => "{$startTime}-{$endTime}"
                ]);
            }

            return !$hasConflict;
        });
    }

    /**
     * ✅ NEW: Filter venues to remove class conflicts upfront
     */
    private function filterVenuesForClassAvailability($classrooms, $classId = null, $day, $startTime, $endTime)
    {
        if (!$classId) {
            return $classrooms; // No class filtering if no class ID specified
        }

        return $classrooms->filter(function ($classroom) use ($classId, $day, $startTime, $endTime) {
            $venueInfo = $this->determineTeachingModeAndLocation($classroom->name);

            // Online venues can handle multiple classes
            if ($venueInfo['teaching_mode'] === 'online') {
                return true;
            }

            // Check if this venue is already booked by the same class at this time
            $hasClassConflict = ClassTimetable::where('venue', $classroom->name)
                ->where('class_id', $classId)
                ->where('day', $day)
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<', $endTime)
                          ->where('end_time', '>', $startTime);
                    });
                })
                ->exists();

            if ($hasClassConflict) {
                \Log::debug('Venue class conflict detected', [
                    'venue' => $classroom->name,
                    'class_id' => $classId,
                    'day' => $day,
                    'time_slot' => "{$startTime}-{$endTime}"
                ]);
            }

            return !$hasClassConflict;
        });
    }

    /**
     * Determine teaching mode and location based on venue
     */
    private function determineTeachingModeAndLocation($venue)
    {
        if (strtolower(trim($venue)) === 'remote') {
            return [
                'teaching_mode' => 'online',
                'location' => 'online'
            ];
        }
        
        $classroom = Classroom::where('name', $venue)->first();
        $location = $classroom ? $classroom->location : 'Physical';
        return [
            'teaching_mode' => 'physical',
            'location' => $location
        ];
    }

    /**
     * ✅ NEW: Determine teaching mode based on duration (2+ hours = physical, 1 hour = online)
     */
    private function determineDurationBasedTeachingMode($startTime, $endTime, $requestedMode = null)
    {
        if (!$startTime || !$endTime) {
            return $requestedMode ?: 'physical'; // Default fallback
        }

        try {
            $duration = \Carbon\Carbon::parse($startTime)->diffInHours(\Carbon\Carbon::parse($endTime));
            
            // Apply duration-based rules
            if ($duration >= 2) {
                $autoMode = 'physical';
            } else {
                $autoMode = 'online';
            }

            \Log::info('Duration-based teaching mode assignment', [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration' => $duration,
                'auto_assigned_mode' => $autoMode,
                'requested_mode' => $requestedMode
            ]);

            return $autoMode;
            
        } catch (\Exception $e) {
            \Log::error('Error determining duration-based teaching mode: ' . $e->getMessage());
            return $requestedMode ?: 'physical';
        }
    }

    /**
     * ✅ NEW: Determine venue based on teaching mode
     */
    private function determineVenueBasedOnMode($requestedVenue, $teachingMode, $studentCount = 0)
    {
        // If venue is explicitly requested, use it
        if (!empty($requestedVenue)) {
            $classroom = Classroom::where('name', $requestedVenue)->first();
            return [
                'venue' => $requestedVenue,
                'location' => $classroom ? $classroom->location : 'Physical'
            ];
        }

        // Auto-assign based on teaching mode
        if ($teachingMode === 'online') {
            return [
                'venue' => 'Remote',
                'location' => 'Online'
            ];
        } else {
            // Find a suitable physical venue
            $suitableClassroom = Classroom::where('capacity', '>=', $studentCount)
                ->orderBy('capacity', 'asc')
                ->first();

            if ($suitableClassroom) {
                return [
                    'venue' => $suitableClassroom->name,
                    'location' => $suitableClassroom->location
                ];
            } else {
                // Fallback to any available classroom
                $anyClassroom = Classroom::first();
                return [
                    'venue' => $anyClassroom ? $anyClassroom->name : 'TBD',
                    'location' => $anyClassroom ? $anyClassroom->location : 'Physical'
                ];
            }
        }
    }

    /**
     * ✅ NEW: Check for conflicts when creating a timetable entry
     */
    private function checkCreateConflicts($day, $startTime, $endTime, $lecturer, $venue, $teachingMode)
    {
        $conflicts = [];
    
        // Check lecturer conflicts
        $lecturerConflict = ClassTimetable::where('day', $day)
            ->where('lecturer', $lecturer)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                });
            })
            ->exists();

        if ($lecturerConflict) {
            $conflicts[] = "Lecturer '{$lecturer}' has a conflicting class on {$day} during {$startTime}-{$endTime}";
        }

        // Check venue conflicts for physical classes only
        if ($teachingMode === 'physical' && $venue && strtolower(trim($venue)) !== 'remote') {
            $venueConflict = ClassTimetable::where('day', $day)
                ->where('venue', $venue)
                ->where('teaching_mode', 'physical')
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<', $endTime)
                          ->where('end_time', '>', $startTime);
                    });
                })
                ->exists();

            if ($venueConflict) {
                $conflicts[] = "Venue '{$venue}' is already booked on {$day} during {$startTime}-{$endTime}";
            }
        }

        return [
            'success' => empty($conflicts),
            'conflicts' => $conflicts,
            'message' => implode('; ', $conflicts)
        ];
    }

    /**
     * Update the specified resource in storage.
     */
public function update(Request $request, $id)
{
    // OPTIMIZATION 1: Streamlined validation
    $request->validate([
        'unit_id' => 'required|integer',
        'semester_id' => 'required|integer', 
        'class_id' => 'required|integer',
        'no' => 'required|integer|min:1|max:1000',
        'lecturer' => 'required|string|max:255',
        'day' => 'nullable|string|max:20',
        'group_id' => 'nullable|integer',
        'venue' => 'nullable|string|max:100',
        'location' => 'nullable|string|max:100',
        'start_time' => 'nullable|string|max:8',
        'end_time' => 'nullable|string|max:8',
        'teaching_mode' => 'nullable|in:physical,online',
        'program_id' => 'nullable|integer',
        'school_id' => 'nullable|integer',
        'classtimeslot_id' => 'nullable|integer',
    ]);

    try {
        DB::beginTransaction();
        
        // Find the timetable entry
        $timetable = ClassTimetable::findOrFail($id);
        
        // Store original data for comparison (to determine what changed)
        $originalData = [
            'day' => $timetable->day,
            'start_time' => $timetable->start_time,
            'end_time' => $timetable->end_time,
            'venue' => $timetable->venue,
            'location' => $timetable->location,
            'lecturer' => $timetable->lecturer,
            'teaching_mode' => $timetable->teaching_mode,
        ];
        
        // Prepare update data efficiently
        $updateData = [
            'unit_id' => $request->unit_id,
            'semester_id' => $request->semester_id,
            'class_id' => $request->class_id,
            'no' => $request->no,
            'lecturer' => $request->lecturer,
            'updated_at' => now(),
        ];
        
        // Handle time slot data
        $timeSlotId = $request->classtimeslot_id;
        if ($timeSlotId && $timeSlotId != $timetable->classtimeslot_id) {
            $timeSlot = DB::selectOne(
                'SELECT day, start_time, end_time FROM class_time_slots WHERE id = ?', 
                [$timeSlotId]
            );
            
            if ($timeSlot) {
                $updateData['day'] = $timeSlot->day;
                $updateData['start_time'] = $timeSlot->start_time;
                $updateData['end_time'] = $timeSlot->end_time;
                $updateData['classtimeslot_id'] = $timeSlotId;
            }
        } else {
            if ($request->day) $updateData['day'] = $request->day;
            if ($request->start_time) $updateData['start_time'] = $request->start_time;
            if ($request->end_time) $updateData['end_time'] = $request->end_time;
        }
        
        // Handle teaching mode and related fields
        $teachingMode = $request->teaching_mode ?: 'physical';
        $updateData['teaching_mode'] = $teachingMode;
        
        $updateData['venue'] = $request->venue ?: ($teachingMode === 'online' ? 'Remote' : 'TBD');
        $updateData['location'] = $request->location ?: ($teachingMode === 'online' ? 'Online' : 'Physical');
        
        // Add optional fields
        $optionalFields = ['group_id', 'program_id', 'school_id'];
        foreach ($optionalFields as $field) {
            if ($request->has($field) && !is_null($request->$field)) {
                $updateData[$field] = $request->$field;
            }
        }

        // Perform the update
        $result = $timetable->update($updateData);
        
        if (!$result && !$timetable->wasChanged()) {
            throw new \Exception('No changes were made to the timetable');
        }
        
        // Refresh model to get updated data
        $timetable->refresh();
        
        DB::commit();

        // OPTIMIZATION 2: Queue notifications AFTER successful update
        // This prevents notifications if update fails
        $this->queueTimetableNotifications($timetable, $originalData, $updateData);

        // Fast response
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Class timetable updated successfully. Notifications are being sent.',
                'data' => [
                    'id' => $timetable->id,
                    'day' => $timetable->day,
                    'start_time' => $timetable->start_time,
                    'end_time' => $timetable->end_time,
                    'venue' => $timetable->venue,
                    'teaching_mode' => $timetable->teaching_mode
                ]
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        return redirect()->back()->with('success', 'Class timetable updated successfully. Notifications are being sent.');

    } catch (\Exception $e) {
        DB::rollback();
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update: ' . $e->getMessage(),
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }

        return redirect()->back()
            ->withErrors(['error' => 'Failed to update: ' . $e->getMessage()])
            ->withInput();
    }
}

/**
 * Queue notifications for affected students efficiently
 */
private function queueTimetableNotifications($timetable, $originalData, $updateData)
{
    try {
        // OPTIMIZATION 3: Single query to get all affected students - FIXED SECTION FILTERING
        $studentsQuery = DB::table('students as s')
            ->join('enrollments as e', 's.id', '=', 'e.student_id')
            ->where('e.class_id', $timetable->class_id)
            ->where('e.semester_id', $timetable->semester_id)
            ->where('s.status', 'active'); // Only active students
            
        // CRITICAL: Always filter by group_id (section) when it exists
        // This ensures Section A students don't get Section B notifications
        if ($timetable->group_id) {
            $studentsQuery->where('e.group_id', $timetable->group_id);
        } else {
            // If no group_id, only notify students with no specific group assignment
            $studentsQuery->whereNull('e.group_id');
        }
        
        // Get student emails and names efficiently
        $students = $studentsQuery
            ->select('s.id', 's.email', 's.first_name', 's.last_name', 's.code as reg_number', 'e.class_id as student_class_id')
            ->distinct()
            ->get();
            
        if ($students->isEmpty()) {
            \Log::info('No students found for timetable notification', [
                'timetable_id' => $timetable->id,
                'timetable_class_id' => $timetable->class_id,
                'semester_id' => $timetable->semester_id
            ]);
            return;
        }

        // CRITICAL: Log exactly who will receive notifications
        \Log::info('Timetable notification targeting', [
            'timetable_id' => $timetable->id,
            'timetable_class_id' => $timetable->class_id,
            'students_to_notify' => $students->map(function($student) {
                return [
                    'student_code' => $student->reg_number,
                    'email' => $student->email,
                    'enrolled_in_class_id' => $student->student_class_id
                ];
            })->toArray()
        ]);

        // OPTIMIZATION 4: Determine what changed for targeted messaging
        $changes = $this->determineChanges($originalData, $updateData);
        
        // OPTIMIZATION 5: Get additional context data in one query
        $contextData = DB::table('units as u')
            ->join('classes as c', 'c.id', '=', DB::raw($timetable->class_id))
            ->join('semesters as sem', 'sem.id', '=', DB::raw($timetable->semester_id))
            ->where('u.id', $timetable->unit_id)
            ->select(
                'u.unit_name', 
                'u.unit_code',
                'c.class_name',
                'sem.semester_name'
            )
            ->first();
            
        // OPTIMIZATION 6: Queue notifications in batches for better performance
        $notificationData = [
            'timetable_id' => $timetable->id,
            'unit_name' => $contextData->unit_name ?? 'Unknown Unit',
            'unit_code' => $contextData->unit_code ?? '',
            'class_name' => $contextData->class_name ?? 'Unknown Class',
            'semester_name' => $contextData->semester_name ?? 'Unknown Semester',
            'lecturer' => $timetable->lecturer,
            'day' => $timetable->day,
            'start_time' => $timetable->start_time,
            'end_time' => $timetable->end_time,
            'venue' => $timetable->venue,
            'location' => $timetable->location,
            'teaching_mode' => $timetable->teaching_mode,
            'changes' => $changes,
            'updated_at' => $timetable->updated_at->format('Y-m-d H:i:s')
        ];

        // Queue notification job for each student
        foreach ($students as $student) {
            \Queue::push(new \App\Jobs\TimetableUpdateNotification([
                'student' => [
                    'id' => $student->id,
                    'email' => $student->email,
                    'name' => trim($student->first_name . ' ' . $student->last_name),
                    'reg_number' => $student->reg_number
                ],
                'timetable' => $notificationData
            ]));
        }

        \Log::info('Timetable update notifications queued', [
            'timetable_id' => $timetable->id,
            'students_count' => $students->count(),
            'changes' => array_keys($changes)
        ]);

    } catch (\Exception $e) {
        // Don't fail the main update if notifications fail
        \Log::error('Failed to queue timetable notifications', [
            'timetable_id' => $timetable->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Determine what fields changed for targeted messaging
 */
private function determineChanges($originalData, $updateData)
{
    $changes = [];
    
    $trackableFields = [
        'day' => 'Day',
        'start_time' => 'Start Time', 
        'end_time' => 'End Time',
        'venue' => 'Venue',
        'location' => 'Location',
        'lecturer' => 'Lecturer',
        'teaching_mode' => 'Teaching Mode'
    ];
    
    foreach ($trackableFields as $field => $label) {
        if (isset($updateData[$field]) && $originalData[$field] !== $updateData[$field]) {
            $changes[$field] = [
                'label' => $label,
                'from' => $originalData[$field],
                'to' => $updateData[$field]
            ];
        }
    }
    
    return $changes;
}


/**
 * Format class details for notification
 */
private function formatClassDetails($timetable, $contextData)
{
    return sprintf(
        "Unit: %s - %s Day: %s Time: %s - %s Venue: %s (%s)",
        $contextData->unit_code ?? '',
        $contextData->unit_name ?? '',
        $timetable->day ?? '',
        $timetable->start_time ?? '',
        $timetable->end_time ?? '',
        $timetable->venue ?? '',
        $timetable->location ?? ''
    );
}

/**
 * Format changes for notification
 */
private function formatChanges($changes)
{
    if (empty($changes)) {
        return 'No specific changes recorded.';
    }

    $changeText = '';
    foreach ($changes as $field => $change) {
        $changeText .= sprintf(
            "%s: Changed from \"%s\" to \"%s\" - ",
            $change['label'],
            $change['from'] ?? 'Not set',
            $change['to'] ?? 'Not set'
        );
    }

    return rtrim($changeText, ' - ');
}
/**
 * ✅ NEW: Optimized bulk update method for multiple timetables
 */
public function bulkUpdate(Request $request)
{
    $request->validate([
        'updates' => 'required|array|min:1|max:100',
        'updates.*.id' => 'required|integer',
        'updates.*.data' => 'required|array'
    ]);

    DB::beginTransaction();
    
    try {
        $updated = 0;
        $errors = [];
        
        // Batch process updates
        foreach ($request->updates as $update) {
            try {
                $timetable = ClassTimetable::findOrFail($update['id']);
                $timetable->update($update['data']);
                $updated++;
            } catch (\Exception $e) {
                $errors[] = "ID {$update['id']}: " . $e->getMessage();
            }
        }
        
        DB::commit();
        
        return response()->json([
            'success' => true,
            'message' => "Successfully updated {$updated} timetable entries.",
            'updated_count' => $updated,
            'errors' => $errors
        ]);
        
    } catch (\Exception $e) {
        DB::rollback();
        return response()->json([
            'success' => false,
            'message' => 'Bulk update failed: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * ✅ NEW: Quick update method for specific fields only
 */
public function quickUpdate(Request $request, $id)
{
    $allowedFields = ['venue', 'lecturer', 'teaching_mode', 'no'];
    
    $request->validate([
        'field' => 'required|in:' . implode(',', $allowedFields),
        'value' => 'required|string|max:255'
    ]);

    try {
        $timetable = ClassTimetable::findOrFail($id);
        $field = $request->field;
        $value = $request->value;
        
        // Validate specific field
        if ($field === 'no') {
            $value = (int) $value;
            if ($value < 1 || $value > 1000) {
                throw new \Exception('Student count must be between 1 and 1000');
            }
        }
        
        if ($field === 'teaching_mode' && !in_array($value, ['physical', 'online'])) {
            throw new \Exception('Teaching mode must be physical or online');
        }
        
        $timetable->update([$field => $value]);
        
        \Log::info('Quick update completed', [
            'id' => $id,
            'field' => $field,
            'new_value' => $value
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Updated successfully.',
            'data' => $timetable->fresh()
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Quick update failed: ' . $e->getMessage(), [
            'id' => $id,
            'field' => $request->field,
            'value' => $request->value
        ]);
        
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}


    /**
     * ✅ NEW: Check for conflicts when updating a timetable entry
     */
    private function checkUpdateConflicts($timetableId, $day, $startTime, $endTime, $lecturer, $venue, $teachingMode)
    {
        $conflicts = [];
    
        // Check lecturer conflicts (excluding the current timetable being updated)
        $lecturerConflict = ClassTimetable::where('day', $day)
            ->where('lecturer', $lecturer)
            ->where('id', '!=', $timetableId)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                });
            })
            ->exists();

        if ($lecturerConflict) {
            $conflicts[] = "Lecturer '{$lecturer}' has a conflicting class on {$day} during {$startTime}-{$endTime}";
        }

        // Check venue conflicts for physical classes only (excluding the current timetable being updated)
        if ($teachingMode === 'physical' && $venue && strtolower(trim($venue)) !== 'remote') {
            $venueConflict = ClassTimetable::where('day', $day)
                ->where('venue', $venue)
                ->where('teaching_mode', 'physical')
                ->where('id', '!=', $timetableId)
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<', $endTime)
                          ->where('end_time', '>', $startTime);
                    });
                })
                ->exists();

            if ($venueConflict) {
                $conflicts[] = "Venue '{$venue}' is already booked on {$day} during {$startTime}-{$endTime}";
            }
        }

        return [
            'success' => empty($conflicts),
            'conflicts' => $conflicts,
            'message' => implode('; ', $conflicts)
        ];
    }

    
    /**
     * ✅ NEW: Get lecturer information for a specific unit and semester
     */
    public function getLecturerForUnit($unitId, $semesterId)
    {
        try {
            // Find the lecturer assigned to this unit in this semester
            $enrollment = Enrollment::where('unit_id', $unitId)
                ->where('semester_id', $semesterId)
                ->whereNotNull('lecturer_code')
                ->first();

            if (!$enrollment) {
                return response()->json(['success' => false, 'message' => 'No lecturer assigned to this unit.']);
            }

            // Get lecturer details
            $lecturer = User::where('code', $enrollment->lecturer_code)->first();
            if (!$lecturer) {
                return response()->json(['success' => false, 'message' => 'Lecturer not found.']);
            }

            // ✅ REAL DATA: Count students enrolled in this unit
            $realStudentCount = Enrollment::where('unit_id', $unitId)
                ->where('semester_id', $semesterId)
                ->count();

            return response()->json([
                'success' => true,
                'lecturer' => [
                    'id' => $lecturer->id,
                    'code' => $lecturer->code,
                    'name' => $lecturer->first_name . ' ' . $lecturer->last_name,
                ],
                'studentCount' => $realStudentCount // ✅ REAL DATA
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to get lecturer for unit: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to get lecturer information: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $timetable = ClassTimetable::findOrFail($id);
            $timetable->delete();

            \Log::info('Class timetable deleted successfully', ['id' => $id]);

            return redirect()->back()->with('success', 'Class timetable deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to delete class timetable: ' . $e->getMessage(), ['id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete class timetable.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $timetable = ClassTimetable::findOrFail($id);
            return response()->json($timetable);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch class timetable: ' . $e->getMessage(), ['id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch class timetable.'], 500);
        }
    }

/**
 * Get programs by school ID
 */
public function getProgramsBySchool(Request $request)
    {
        try {
            $schoolId = $request->input('school_id');

            if (!$schoolId) {
                return response()->json(['error' => 'School ID is required.'], 400);
            }

            \Log::info('Fetching programs for school', ['school_id' => $schoolId]);

            $programs = DB::table('programs')
                ->where('school_id', $schoolId)
                ->select('id', 'code', 'name', 'school_id')
                ->orderBy('name')
                ->get();

            \Log::info('Programs found for school', [
                'school_id' => $schoolId,
                'programs_count' => $programs->count()
            ]);

            return response()->json($programs);

        } catch (\Exception $e) {
            \Log::error('Error fetching programs by school: ' . $e->getMessage(), [
                'school_id' => $request->input('school_id'),
                'exception' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Failed to fetch programs.'], 500);
        }
    }

/**
 * Get classes by program ID and semester ID
 */
public function getClassesByProgram(Request $request)
    {
        try {
            $programId = $request->input('program_id');
            $semesterId = $request->input('semester_id');

            if (!$programId) {
                return response()->json(['error' => 'Program ID is required.'], 400);
            }

            if (!$semesterId) {
                return response()->json(['error' => 'Semester ID is required.'], 400);
            }

            \Log::info('Fetching classes for program and semester with enhanced display', [
                'program_id' => $programId,
                'semester_id' => $semesterId
            ]);

            $classes = DB::table('classes')
                ->where('program_id', $programId)
                ->where('semester_id', $semesterId)
                ->where('is_active', true)
                ->select('id', 'name', 'program_id', 'semester_id', 'year_level', 'section')
                ->orderBy('name')
                ->orderBy('section')
                ->get()
                ->map(function ($class) {
                    // ✅ ENHANCED: Create display name with section and year info
                    $displayName = $class->name;
                    if ($class->section) {
                        $displayName .= ' - Section ' . $class->section;
                    }
                    if ($class->year_level) {
                        $displayName .= ' (Year ' . $class->year_level . ')';
                    }
                    
                    return [
                        'id' => $class->id,
                        'name' => $class->name,
                        'display_name' => $displayName,
                        'program_id' => $class->program_id,
                        'semester_id' => $class->semester_id,
                        'year_level' => $class->year_level,
                        'section' => $class->section
                    ];
                });

            \Log::info('Enhanced classes found for program and semester', [
                'program_id' => $programId,
                'semester_id' => $semesterId,
                'classes_count' => $classes->count()
            ]);

            return response()->json($classes);

        } catch (\Exception $e) {
            \Log::error('Error fetching classes by program: ' . $e->getMessage(), [
                'program_id' => $request->input('program_id'),
                'semester_id' => $request->input('semester_id'),
                'exception' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Failed to fetch classes.'], 500);
        }
    }

    /**
     * ✅ REAL DATA: Display student's timetable page with real group filtering
     */
    public function studentTimetable(Request $request)
{
    try {
        // Fetch the authenticated student
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login')->with('error', 'Please log in to view your timetable.');
        }

        \Log::info('Student accessing timetable', [
            'user_id' => $user->id,
            'user_code' => $user->code ?? 'No code'
        ]);

        // Check if user has a code (required for enrollments)
        if (!$user->code) {
            \Log::error('User has no code for enrollment lookup', ['user_id' => $user->id]);
            return Inertia::render('Student/Timetable', [
                'classTimetables' => [],
                'currentSemester' => null,
                'availableSemesters' => [],
                'downloadUrl' => route('student.timetable.download'),
                'student' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'code' => $user->code,
                ],
                'filters' => [
                    'per_page' => $request->get('per_page', 100),
                    'search' => $request->get('search', ''),
                    'semester_id' => $request->get('semester_id'),
                ],
                'error' => 'Student code not found. Please contact administration.'
            ]);
        }

        // ✅ NEW: Get all semesters where the student has enrollments
        $availableSemesters = Semester::whereIn('id', function($query) use ($user) {
            $query->select('semester_id')
                  ->from('enrollments')
                  ->where('student_code', $user->code)
                  ->distinct();
        })
        ->orderByDesc('is_active')
        ->orderByDesc('id')
        ->get();

        \Log::info('Available semesters for student', [
            'student_code' => $user->code,
            'available_semesters' => $availableSemesters->pluck('name', 'id')->toArray()
        ]);

        // Determine which semester to show
        $selectedSemesterId = $request->get('semester_id');
        $currentSemester = null;

        if ($selectedSemesterId) {
            // Use the selected semester
            $currentSemester = $availableSemesters->where('id', $selectedSemesterId)->first();
        } else {
            // Default to active semester if student is enrolled, otherwise first available
            $currentSemester = $availableSemesters->where('is_active', true)->first() 
                            ?? $availableSemesters->first();
        }

        // If no semester is available or selected, show empty state
        if (!$currentSemester) {
            return Inertia::render('Student/Timetable', [
                'classTimetables' => [],
                'currentSemester' => null,
                'availableSemesters' => $availableSemesters,
                'downloadUrl' => route('student.timetable.download'),
                'student' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'code' => $user->code,
                ],
                'filters' => [
                    'per_page' => $request->get('per_page', 100),
                    'search' => $request->get('search', ''),
                    'semester_id' => $selectedSemesterId,
                ],
                'error' => 'You are not enrolled in any semester yet.'
            ]);
        }

        // ✅ REAL DATA: Fetch enrollments for this student in the selected semester
        $enrollments = Enrollment::where('student_code', $user->code)
            ->where('semester_id', $currentSemester->id)
            ->with(['unit', 'semester', 'group'])
            ->get();

        \Log::info('Real enrollments found for selected semester', [
            'student_code' => $user->code,
            'semester_id' => $currentSemester->id,
            'semester_name' => $currentSemester->name,
            'count' => $enrollments->count()
        ]);

        // Get the student's enrolled unit IDs and group IDs for this semester
        $enrolledUnitIds = $enrollments->pluck('unit_id')->filter()->toArray();
        $studentGroupIds = $enrollments->pluck('group_id')->filter()->unique()->toArray();

        // Get pagination parameters
        $perPage = $request->get('per_page', 100);
        $search = $request->get('search', '');

        // ✅ REAL DATA: Fetch actual class timetable entries for the student's units AND groups with pagination
        $classTimetables = collect();
        
        if (!empty($enrolledUnitIds)) {
            try {
                $query = DB::table('class_timetable')
                    ->whereIn('class_timetable.unit_id', $enrolledUnitIds)
                    ->where('class_timetable.semester_id', $currentSemester->id);

                // ✅ CRITICAL FIX: Filter by student's group(s) using REAL data
                if (!empty($studentGroupIds)) {
                    $query->whereIn('class_timetable.group_id', $studentGroupIds);
                } else {
                    // If no specific group assigned, check if there are enrollments with null group_id
                    $hasNullGroup = $enrollments->whereNull('group_id')->isNotEmpty();
                    if ($hasNullGroup) {
                        $query->whereNull('class_timetable.group_id');
                    }
                }

                // Add search functionality
                if (!empty($search)) {
                    $query->where(function ($q) use ($search) {
                        $q->where('units.code', 'like', "%{$search}%")
                          ->orWhere('units.name', 'like', "%{$search}%")
                          ->orWhere('class_timetable.venue', 'like', "%{$search}%")
                          ->orWhere('class_timetable.day', 'like', "%{$search}%")
                          ->orWhere(DB::raw("CONCAT(users.first_name, ' ', users.last_name)"), 'like', "%{$search}%")
                          ->orWhere('class_timetable.lecturer', 'like', "%{$search}%");
                    });
                }

                $classTimetables = $query
                    ->leftJoin('units', 'class_timetable.unit_id', '=', 'units.id')
                    ->leftJoin('users', 'users.code', '=', 'class_timetable.lecturer')
                    ->leftJoin('groups', 'class_timetable.group_id', '=', 'groups.id')
                    ->leftJoin('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
                    ->select(
                        'class_timetable.id',
                        'class_timetable.day',
                        'class_timetable.start_time',
                        'class_timetable.end_time',
                        'class_timetable.venue',
                        'class_timetable.location',
                        'class_timetable.teaching_mode',
                        'class_timetable.lecturer',
                        'units.code as unit_code',
                        'units.name as unit_name',
                        DB::raw("CASE 
                            WHEN users.id IS NOT NULL THEN CONCAT(users.first_name, ' ', users.last_name) 
                            ELSE class_timetable.lecturer 
                            END as lecturer"),
                        'groups.name as group_name',
                        'semesters.name as semester_name'
                    )
                    ->orderBy('class_timetable.day')
                    ->orderBy('class_timetable.start_time')
                    ->paginate($perPage)
                    ->withQueryString();

                \Log::info('Paginated class timetables found with REAL data', [
                    'total' => $classTimetables->total(),
                    'per_page' => $classTimetables->perPage(),
                    'current_page' => $classTimetables->currentPage(),
                    'student_code' => $user->code,
                    'semester_name' => $currentSemester->name,
                    'groups_filtered' => $studentGroupIds,
                    'search_term' => $search
                ]);

            } catch (\Exception $e) {
                \Log::error('Error fetching class timetables: ' . $e->getMessage(), [
                    'student_code' => $user->code,
                    'semester_id' => $currentSemester->id,
                    'unit_ids' => $enrolledUnitIds,
                    'group_ids' => $studentGroupIds
                ]);
                
                // Return empty paginated result in case of error
                $classTimetables = new \Illuminate\Pagination\LengthAwarePaginator(
                    collect([]),
                    0,
                    $perPage,
                    1,
                    [
                        'path' => request()->url(),
                        'pageName' => 'page',
                    ]
                );
            }
        } else {
            \Log::info('No enrolled units found for student in selected semester', [
                'student_code' => $user->code,
                'semester_id' => $currentSemester->id,
                'semester_name' => $currentSemester->name
            ]);
            
            // Return empty paginated result when no enrolled units
            $classTimetables = new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                $perPage,
                1,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );
        }

        return Inertia::render('Student/Timetable', [
            'classTimetables' => $classTimetables,
            'currentSemester' => [
                'id' => $currentSemester->id,
                'name' => $currentSemester->name
            ],
            'availableSemesters' => $availableSemesters->map(function($semester) {
                return [
                    'id' => $semester->id,
                    'name' => $semester->name
                ];
            }),
            'downloadUrl' => route('student.timetable.download'),
            'student' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'code' => $user->code,
                'groups' => $studentGroupIds
            ],
            'filters' => [
                'per_page' => (int) $perPage,
                'search' => $search,
                'semester_id' => (int) $currentSemester->id,
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('Critical error in studentTimetable method: ' . $e->getMessage(), [
            'exception' => $e,
            'trace' => $e->getTraceAsString(),
            'user_id' => auth()->id()
        ]);

        return Inertia::render('Student/Timetable', [
            'classTimetables' => new \Illuminate\Pagination\LengthAwarePaginator(
                collect([]),
                0,
                $request->get('per_page', 100),
                1,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            ),
            'currentSemester' => null,
            'availableSemesters' => [],
            'downloadUrl' => route('student.timetable.download'),
            'student' => [
                'id' => auth()->id(),
                'first_name' => auth()->user()->first_name ?? '',
                'last_name' => auth()->user()->last_name ?? '',
                'code' => auth()->user()->code ?? '',
                'groups' => []
            ],
            'filters' => [
                'per_page' => $request->get('per_page', 100),
                'search' => $request->get('search', ''),
                'semester_id' => $request->get('semester_id'),
            ],
            'error' => 'An error occurred while loading your timetable. Please try again or contact support.'
        ]);
    }
}
    /**
     * Download the class timetable as a PDF.
     */
    public function downloadPDF(Request $request)
    {
        try {
            // Ensure the view file exists
            if (!view()->exists('classtimetables.pdf')) {
                \Log::error('PDF template not found: classtimetables.pdf');
                return redirect()->back()->with('error', 'PDF template not found. Please contact the administrator.');
            }

            // Fetch class timetables
            $query = ClassTimetable::query()
                ->join('units', 'class_timetable.unit_id', '=', 'units.id')
                ->join('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
                ->leftJoin('classes', 'class_timetable.class_id', '=', 'classes.id')
                ->leftJoin('groups', 'class_timetable.group_id', '=', 'groups.id')
                ->leftJoin('class_time_slots', function ($join) {
                    $join->on('class_timetable.day', '=', 'class_time_slots.day')
                        ->on('class_timetable.start_time', '=', 'class_time_slots.start_time')
                        ->on('class_timetable.end_time', '=', 'class_time_slots.end_time');
                })
                ->select(
                    'class_timetable.*',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'semesters.name as semester_name',
                    'classes.name as class_name',
                    'groups.name as group_name',
                    'class_time_slots.status as mode_of_teaching'
                );

            if ($request->has('semester_id')) {
                $query->where('class_timetable.semester_id', $request->semester_id);
            }

            if ($request->has('class_id')) {
                $query->where('class_timetable.class_id', $request->class_id);
            }

            if ($request->has('group_id')) {
                $query->where('class_timetable.group_id', $request->group_id);
            }

            $classTimetables = $query->orderBy('class_timetable.day')
                ->orderBy('class_timetable.start_time')
                ->get();

            // Log the data being passed to the view for debugging
            \Log::info('Generating PDF with data:', [
                'count' => $classTimetables->count(),
                'filters' => $request->only(['semester_id', 'class_id', 'group_id'])
            ]);

            // Generate PDF
            $pdf = Pdf::loadView('classtimetables.pdf', [
                'classTimetables' => $classTimetables,
                'title' => 'Class Timetable',
                'generatedAt' => now()->format('Y-m-d H:i:s'),
                'filters' => $request->only(['semester_id', 'class_id', 'group_id'])
            ]);

            // Set paper size and orientation
            $pdf->setPaper('a4', 'landscape');

            // Return the PDF for download
            return $pdf->download('class-timetable-' . now()->format('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            // Log detailed error information
            \Log::error('Failed to generate PDF: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            // Return a more informative error response
            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to detect conflicts in the timetable
     */
    private function detectConflicts()
    {
        // Detect lecturer conflicts
        $lecturerConflicts = DB::select("
            SELECT lecturer, day, start_time, end_time, COUNT(*) as conflict_count
            FROM class_timetable 
            GROUP BY lecturer, day, start_time, end_time
            HAVING COUNT(*) > 1
        ");

        // Detect venue conflicts
        $venueConflicts = DB::select("
            SELECT venue, day, start_time, end_time, COUNT(*) as conflict_count
            FROM class_timetable 
            GROUP BY venue, day, start_time, end_time
            HAVING COUNT(*) > 1
        ");

        return [
            'lecturer_conflicts' => $lecturerConflicts,
            'venue_conflicts' => $venueConflicts
        ];
    }

    /**
     * Helper method to detect and resolve conflicts
     */
    private function detectAndResolveConflicts()
    {
        $conflicts = $this->detectConflicts();
        $resolved = 0;

        // Implementation of conflict resolution logic
        // This could include automatic rescheduling, venue reassignment, etc.

        return ['conflicts_resolved' => $resolved];
    }
}