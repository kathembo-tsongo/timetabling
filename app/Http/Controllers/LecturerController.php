<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Add this import
use Illuminate\Support\Facades\Schema; // <-- Add this line
use App\Models\User;
use App\Models\Unit;
use App\Models\UnitAssignment; // 
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\ClassTimetable; // Add this import
use App\Models\ExamTimetable; // Add this import
use Inertia\Inertia;

class LecturerController extends Controller
{
    
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        // Apply auth middleware to all methods
        $this->middleware('auth');
        
        // Add debugging middleware to check user roles
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            
            if ($user) {
                // Debug: Log all user information
                Log::info('LecturerController access attempt', [
                    'user_id' => $user->id,
                    'user_code' => $user->code ?? 'NO CODE',
                    'user_email' => $user->email ?? 'NO EMAIL',
                    'user_role' => $user->role ?? 'NO ROLE FIELD',
                    'user_type' => $user->type ?? 'NO TYPE FIELD',
                    'all_user_attributes' => $user->getAttributes(),
                    'requested_route' => $request->route()->getName(),
                    'requested_url' => $request->url()
                ]);
                
                // Check if user has any roles via relationships
                if (method_exists($user, 'roles')) {
                    $roles = $user->roles;
                    Log::info('User roles via relationship', [
                        'roles_count' => $roles ? $roles->count() : 0,
                        'roles' => $roles ? $roles->pluck('name')->toArray() : []
                    ]);
                }
                
                // Check permissions if available
                if (method_exists($user, 'permissions')) {
                    $permissions = $user->permissions;
                    Log::info('User permissions via relationship', [
                        'permissions_count' => $permissions ? $permissions->count() : 0,
                        'permissions' => $permissions ? $permissions->pluck('name')->toArray() : []
                    ]);
                }
            }
            
            return $next($request);
        });
      
    }
    private function hasLecturerAccess($user)
    {
        // Admin always has access
        if (method_exists($user, 'hasRole') && $user->hasRole('Admin')) {
            Log::info('Access granted: User is Admin');
            return true;
        }
        
        // Check for Lecturer role
        if (method_exists($user, 'hasRole') && $user->hasRole('Lecturer')) {
            Log::info('Access granted: User has Lecturer role');
            return true;
        }
        
        // Check via roles relationship
        if (method_exists($user, 'roles')) {
            $userRoles = $user->roles()->pluck('name')->toArray();
            if (in_array('Lecturer', $userRoles) || in_array('Admin', $userRoles)) {
                Log::info('Access granted via roles relationship', ['roles' => $userRoles]);
                return true;
            }
        }
        
        // Check for Faculty Admin
        if ($this->isFacultyAdmin($user)) {
            Log::info('Access granted: User is Faculty Admin');
            return true;
        }
        
        Log::warning('Access denied: No matching criteria found', [
            'user_id' => $user->id,
            'user_code' => $user->code ?? 'NO CODE'
        ]);
        return false;
    }
    
    /**
     * Check if user is a faculty admin - SIMPLIFIED
     */
    private function isFacultyAdmin($user)
    {
        // Check by role name containing "Faculty Admin"
        if (method_exists($user, 'roles')) {
            $userRoles = $user->roles()->get();
            foreach ($userRoles as $role) {
                if (str_contains($role->name, 'Faculty Admin')) {
                    Log::info('User identified as faculty admin by role name', ['role' => $role->name]);
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Display a listing of the resource
     */
    public function index()
    {
        $user = auth()->user();
        
        if (!$user || !$this->hasLecturerAccess($user)) {
            abort(403, 'Access denied. You do not have lecturer privileges.');
        }
        
        return redirect()->route('lecturer.dashboard');
    }
    
    /**
     * Display the lecturer's dashboard
     */
    public function dashboard()
    {
        $lecturer = auth()->user();
        
        if (!$lecturer || !$this->hasLecturerAccess($lecturer)) {
            abort(403, 'Access denied. You do not have lecturer privileges.');
        }
        
        $currentSemester = Semester::where('is_current', true)->first();
        $lecturerSemesters = Semester::whereHas('unitAssignments', function($query) use ($lecturer) {
            $query->where('lecturer_code', $lecturer->code);
        })->get();
        
        $unitsBySemester = [];
        $studentCounts = [];
        
        foreach ($lecturerSemesters as $semester) {
            $unitIds = UnitAssignment::where('lecturer_code', $lecturer->code)
                ->where('semester_id', $semester->id)
                ->pluck('unit_id')
                ->unique();
            
            if ($unitIds->isNotEmpty()) {
                $units = Unit::whereIn('id', $unitIds)
                    ->with('school')
                    ->get();
                
                if ($units->count() > 0) {
                    $unitsBySemester[$semester->id] = [
                        'semester' => $semester,
                        'units' => $units
                    ];
                    
                    $studentCounts[$semester->id] = [];
                    foreach ($units as $unit) {
                        $studentCounts[$semester->id][$unit->id] = DB::table('enrollments')
                            ->where('unit_id', $unit->id)
                            ->where('semester_id', $semester->id)
                            ->whereNotNull('student_code')
                            ->where('status', 'enrolled')
                            ->distinct('student_code')
                            ->count();
                    }
                }
            }
        }
        
        return Inertia::render('Lecturer/Dashboard', [
            'currentSemester' => $currentSemester,
            'lecturerSemesters' => $lecturerSemesters,
            'unitsBySemester' => $unitsBySemester,
            'studentCounts' => $studentCounts,
        ]);
    }
   
    
    public function myClasses(Request $request)
{
    $user = $request->user();
    
    if (!$user || !$user->code) {
        Log::error('My Classes accessed with invalid user', [
            'user_id' => $user ? $user->id : 'null',
            'has_code' => $user && isset($user->code)
        ]);
        
        return Inertia::render('Lecturer/Classes', [
            'error' => 'User profile is incomplete. Please contact an administrator.',
            'units' => [],
            'currentSemester' => null,
            'semesters' => [],
            'selectedSemesterId' => null,
            'lecturerSemesters' => [],
            'studentCounts' => []
        ]);
    }
    
    // Check access permissions
    if (!$this->hasLecturerAccess($user)) {
        abort(403, 'Access denied. You do not have the required privileges.');
    }
    
    try {
        // Get semesters where lecturer has assignments (using unit_assignments table)
        $lecturerSemesters = Semester::whereHas('unitAssignments', function($query) use ($user) {
            $query->where('lecturer_code', $user->code);
        })->orderBy('name')->get();
        
        Log::info('Found lecturer semesters via unit_assignments', [
            'lecturer_code' => $user->code,
            'semesters_count' => $lecturerSemesters->count(),
            'semester_ids' => $lecturerSemesters->pluck('id')->toArray()
        ]);
        
        // Get current semester - prioritize active semester among lecturer's semesters
        $currentSemester = $lecturerSemesters->firstWhere('is_current', true) 
                          ?? $lecturerSemesters->firstWhere('is_active', true)
                          ?? $lecturerSemesters->sortByDesc('id')->first();
        
        // Get all semesters for dropdown
        $semesters = Semester::orderBy('name')->get();
        
        // Get selected semester (default to current)
        $selectedSemesterId = $request->input('semester_id', $currentSemester ? $currentSemester->id : null);
        
        Log::info('Selected semester', [
            'selected_semester_id' => $selectedSemesterId,
            'current_semester_id' => $currentSemester ? $currentSemester->id : null
        ]);
        
        $assignedUnits = collect();
        $studentCounts = [];
        
        if ($selectedSemesterId) {
            // Get unit IDs assigned to this lecturer for the selected semester from unit_assignments
            $unitIds = UnitAssignment::where('lecturer_code', $user->code)
                ->where('semester_id', $selectedSemesterId)
                ->pluck('unit_id')
                ->unique()
                ->filter(); // Remove any null values
            
            Log::info('Found unit IDs from unit_assignments', [
                'lecturer_code' => $user->code,
                'semester_id' => $selectedSemesterId,
                'unit_ids' => $unitIds->toArray()
            ]);
            
            // Get the actual unit objects
            if ($unitIds->isNotEmpty()) {
                $assignedUnits = Unit::whereIn('id', $unitIds)
                    ->with(['school', 'program'])
                    ->get();
                
                Log::info('Loaded unit details', [
                    'units_count' => $assignedUnits->count(),
                    'unit_codes' => $assignedUnits->pluck('code')->toArray()
                ]);
                
                // Count students for each unit in this semester (from enrollments table)
                foreach ($assignedUnits as $unit) {
                    $studentCounts[$unit->id] = DB::table('enrollments')
                        ->where('unit_id', $unit->id)
                        ->where('semester_id', $selectedSemesterId)
                        ->whereNotNull('student_code')
                        ->where('status', 'enrolled')
                        ->distinct('student_code')
                        ->count();
                }
            }
        }
        
        Log::info('Final data prepared', [
            'lecturer_code' => $user->code,
            'semester_id' => $selectedSemesterId,
            'units_count' => $assignedUnits->count(),
            'student_counts' => $studentCounts
        ]);
        
        return Inertia::render('Lecturer/Classes', [
            'units' => $assignedUnits->values()->toArray(),
            'currentSemester' => $currentSemester,
            'semesters' => $semesters,
            'selectedSemesterId' => (int)$selectedSemesterId,
            'lecturerSemesters' => $lecturerSemesters->pluck('id')->toArray(),
            'studentCounts' => $studentCounts
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error in lecturer classes', [
            'lecturer_code' => $user->code,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return Inertia::render('Lecturer/Classes', [
            'error' => 'An error occurred while loading your classes. Please try again later.',
            'units' => [],
            'currentSemester' => null,
            'semesters' => [],
            'selectedSemesterId' => null,
            'lecturerSemesters' => [],
            'studentCounts' => []
        ]);
    }
}

// 2. UPDATE THE classStudents METHOD TO USE unit_assignments:

public function classStudents(Request $request, $unitId)
{
    $user = $request->user();
    $selectedSemesterId = $request->input('semester_id');
    
    if (!$selectedSemesterId) {
        return Inertia::render('Lecturer/ClassStudents', [
            'error' => 'Semester ID is required.',
            'unit' => null,
            'students' => [],
            'unitSemester' => null,
            'selectedSemesterId' => null
        ]);
    }

    // Verify that this unit is assigned to the lecturer using unit_assignments table
    $isAssigned = UnitAssignment::where('lecturer_code', $user->code)
        ->where('unit_id', $unitId)
        ->where('semester_id', $selectedSemesterId)
        ->exists();

    if (!$isAssigned) {
        return Inertia::render('Lecturer/ClassStudents', [
            'error' => 'You are not assigned to this unit for the selected semester.',
            'unit' => null,
            'students' => [],
            'unitSemester' => null,
            'selectedSemesterId' => $selectedSemesterId
        ]);
    }

    try {
        $unit = Unit::findOrFail($unitId);
        $unitSemester = Semester::findOrFail($selectedSemesterId);

        // Get students enrolled in this unit for this semester (from enrollments table)
        $enrollments = DB::table('enrollments')
            ->where('unit_id', $unitId)
            ->where('semester_id', $selectedSemesterId)
            ->whereNotNull('student_code')
            ->where('status', 'enrolled')
            ->get();

        // Get student details from users table
        $studentCodes = $enrollments->pluck('student_code')->unique();
        $students = User::whereIn('code', $studentCodes)
            ->select('id', 'code', 'email', 'first_name', 'last_name')
            ->get()
            ->keyBy('code');

        // Map enrollments with student details
        $enrollmentsWithStudents = $enrollments->map(function ($enrollment) use ($students) {
            $student = $students->get($enrollment->student_code);
            
            return [
                'id' => $enrollment->id,
                'student_id' => $student ? $student->id : null,
                'unit_id' => $enrollment->unit_id,
                'semester_id' => $enrollment->semester_id,
                'student' => $student ? [
                    'id' => $student->id,
                    'code' => $student->code,
                    'email' => $student->email ?? 'No email',
                    'name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) ?: 'No name'
                ] : [
                    'id' => null,
                    'code' => $enrollment->student_code,
                    'email' => 'No email available',
                    'name' => 'Student not found'
                ]
            ];
        });

        return Inertia::render('Lecturer/ClassStudents', [
            'unit' => $unit,
            'students' => $enrollmentsWithStudents,
            'unitSemester' => $unitSemester,
            'selectedSemesterId' => $selectedSemesterId,
            'studentCount' => $enrollmentsWithStudents->count()
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error in class students', [
            'lecturer_code' => $user->code,
            'unit_id' => $unitId,
            'semester_id' => $selectedSemesterId,
            'error' => $e->getMessage()
        ]);

        return Inertia::render('Lecturer/ClassStudents', [
            'error' => 'An error occurred while loading student data.',
            'unit' => null,
            'students' => [],
            'unitSemester' => null,
            'selectedSemesterId' => $selectedSemesterId
        ]);
    }
}

   /**
     * Handle faculty admin viewing all classes
     */
    private function handleFacultyAdminClasses(Request $request, $user)
    {
        try {
            // Get all semesters
            $semesters = Semester::orderBy('name')->get();
            
            // Get current semester
            $currentSemester = Semester::where('is_active', true)->first();
            if (!$currentSemester) {
                $currentSemester = Semester::latest()->first();
            }
            
            // Get selected semester (default to current)
            $selectedSemesterId = $request->input('semester_id', $currentSemester ? $currentSemester->id : null);
            
            // Get all units for the selected semester (faculty admin can see all)
            $allUnits = Unit::all();
            
            // Get all enrollments for the selected semester
            $enrollments = Enrollment::where('semester_id', $selectedSemesterId)
                ->with(['unit.school', 'semester'])
                ->get();
            
            // Group units by lecturer
            $unitsByLecturer = $enrollments->groupBy('lecturer_code');
            
            // Get unique unit IDs
            $unitIds = $enrollments->pluck('unit_id')->filter()->unique()->values();
            
            // Get the actual unit objects
            $assignedUnits = Unit::whereIn('id', $unitIds)->get();
            
            // Count students per unit
            $studentCounts = [];
            foreach ($unitIds as $unitId) {
                $studentCounts[$unitId] = Enrollment::where('unit_id', $unitId)
                    ->where('semester_id', $selectedSemesterId)
                    ->where('student_code', '!=', null)
                    ->distinct('student_code')
                    ->count();
            }
            
            // Get all lecturer semester IDs (for faculty admin, this includes all)
            $lecturerSemesterIds = Semester::pluck('id')->toArray();
            
            Log::info('Faculty admin viewing classes', [
                'admin_id' => $user->id,
                'admin_code' => $user->code,
                'semester_id' => $selectedSemesterId,
                'total_units' => $assignedUnits->count(),
                'total_lecturers' => $unitsByLecturer->count()
            ]);
            
            return Inertia::render('Lecturer/Classes', [
                'units' => $assignedUnits,
                'currentSemester' => $currentSemester,
                'semesters' => $semesters,
                'selectedSemesterId' => (int)$selectedSemesterId,
                'lecturerSemesters' => $lecturerSemesterIds,
                'studentCounts' => $studentCounts,
                'isFacultyAdmin' => true, // Flag to indicate faculty admin view
                'unitsByLecturer' => $unitsByLecturer->map(function ($units, $lecturerCode) {
                    return [
                        'lecturer_code' => $lecturerCode,
                        'units' => $units
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Error in faculty admin classes view', [
                'admin_code' => $user->code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Inertia::render('Lecturer/Classes', [
                'error' => 'An error occurred while loading classes data. Please try again later.',
                'units' => [],
                'currentSemester' => null,
                'semesters' => [],
                'selectedSemesterId' => null,
                'lecturerSemesters' => [],
                'studentCounts' => [],
                'isFacultyAdmin' => true
            ]);
        }
    }
    
/**
 * Display the lecturer's class timetable - FIXED VERSION
 */
public function viewClassTimetable(Request $request)
{
    $user = $request->user();
    
    if (!$user || !$user->code) {
        Log::error('Class Timetable accessed with invalid user', [
            'user_id' => $user ? $user->id : 'null',
            'has_code' => $user && isset($user->code)
        ]);
        
        return Inertia::render('Lecturer/ClassTimetable', [
            'error' => 'User profile is incomplete. Please contact an administrator.',
            'classTimetables' => [],
            'currentSemester' => null,
            'selectedSemesterId' => null,
            'selectedUnitId' => null,
            'assignedUnits' => [],
            'lecturerSemesters' => []
        ]);
    }
    
    try {
        // Get selected semester and unit from request
        $selectedSemesterId = $request->input('semester_id');
        $selectedUnitId = $request->input('unit_id');
        
        // Get semesters where the lecturer has assignments
        $lecturerSemesters = Semester::whereHas('unitAssignments', function($query) use ($user) {
            $query->where('lecturer_code', $user->code);
        })->orderBy('name')->get();
        
        // Get all units assigned to this lecturer across all semesters
        $allAssignedUnits = UnitAssignment::where('lecturer_code', $user->code)
            ->with(['unit.school', 'semester'])
            ->get();
            
        // Extract unique units
        $unitIds = $allAssignedUnits->pluck('unit_id')->unique()->filter();
        $assignedUnits = Unit::whereIn('id', $unitIds)->get();
        
        // Get the selected semester (for display purposes)
        $selectedSemester = null;
        if ($selectedSemesterId) {
            $selectedSemester = $lecturerSemesters->firstWhere('id', $selectedSemesterId);
        }
        
        Log::info('Class timetable - lecturer info', [
            'lecturer_code' => $user->code,
            'lecturer_name' => $user->name,
            'semester_id' => $selectedSemesterId,
            'unit_id' => $selectedUnitId,
            'assigned_unit_ids' => $unitIds->toArray()
        ]);
        
        // Build the query for class timetable entries
        $query = DB::table('class_timetable')
            ->leftJoin('programs', 'class_timetable.program_id', '=', 'programs.id')
            ->leftJoin('classes', 'class_timetable.class_id', '=', 'classes.id')
            ->leftJoin('groups', 'class_timetable.group_id', '=', 'groups.id')
            ->leftJoin('units', 'class_timetable.unit_id', '=', 'units.id')
            ->select(
                'class_timetable.*',
                'programs.name as program_name',
                'programs.code as program_code',
                'classes.name as class_name',
                'classes.section as class_section',
                'groups.name as group_name',
                'units.code as unit_code',
                'units.name as unit_name'
            );

        // SIMPLIFIED FILTERING - Based on your database screenshot
        // Your class_timetable has a 'lecturer' column that stores lecturer codes like 'BBITLEC010'
        
        Log::info('Filtering timetable by lecturer code', [
            'lecturer_code' => $user->code,
            'assigned_unit_ids' => $unitIds->toArray()
        ]);
        
        // Filter by lecturer column (which contains lecturer codes like BBITLEC010)
        $query->where('class_timetable.lecturer', $user->code);

        // Apply additional filters if provided
        if ($selectedUnitId) {
            $query->where('class_timetable.unit_id', $selectedUnitId);
            Log::info('Added unit filter', ['unit_id' => $selectedUnitId]);
        }
        
        if ($selectedSemesterId) {
            $query->where('class_timetable.semester_id', $selectedSemesterId);
            Log::info('Added semester filter', ['semester_id' => $selectedSemesterId]);
        }
        
        // Get the timetable entries
        $timetableEntries = $query->orderBy('day')
            ->orderBy('start_time')
            ->get();
        
        Log::info('Class timetable query results', [
            'lecturer_code' => $user->code,
            'results_count' => $timetableEntries->count(),
            'sample_entries' => $timetableEntries->take(2)->toArray()
        ]);
        
        $classTimetables = [];
        
        // Format the timetable entries
        if ($timetableEntries->isNotEmpty()) {
            // Get additional unit and semester data if needed
            $unitIds = $timetableEntries->pluck('unit_id')->unique();
            $units = Unit::whereIn('id', $unitIds)->get()->keyBy('id');
            
            $semesterIds = $timetableEntries->pluck('semester_id')->unique();
            $semesters = Semester::whereIn('id', $semesterIds)->get()->keyBy('id');
            
            // Map the timetable entries and convert to array
            $classTimetables = $timetableEntries->map(function($entry) use ($units, $semesters) {
                $unit = $units->get($entry->unit_id);
                $semester = $semesters->get($entry->semester_id);

                return [
                    'id' => $entry->id,
                    'unit_id' => $entry->unit_id,
                    'semester_id' => $entry->semester_id,
                    'unit' => $unit ? [
                        'id' => $unit->id,
                        'code' => $unit->code,
                        'name' => $unit->name
                    ] : [
                        'id' => $entry->unit_id,
                        'code' => $entry->unit_code ?? 'Unknown',
                        'name' => $entry->unit_name ?? 'Unknown Unit'
                    ],
                    'semester' => $semester ? [
                        'id' => $semester->id,
                        'name' => $semester->name
                    ] : null,
                    'day' => $entry->day ?? 'Unknown',
                    'start_time' => $entry->start_time ?? '00:00',
                    'end_time' => $entry->end_time ?? '00:00',
                    'venue' => $entry->room_name ?? $entry->venue ?? 'TBA',
                    'location' => $entry->location ?? '',
                    'no' => $entry->no ?? 0,
                    'program_name' => $entry->program_name ?? '',
                    'program_code' => $entry->program_code ?? '',
                    'class_name' => $entry->class_name ?? '',
                    'class_section' => $entry->class_section ?? '',
                    'group_name' => $entry->group_name ?? '',
                    // Format program and section for display
                    'program_section_display' => trim(
                        ($entry->program_code ?? '') . ' ' . 
                        ($entry->class_name ?? '') . 
                        ($entry->class_section ? ' Section ' . $entry->class_section : '')
                    ),
                ];
            })->toArray();
        }
        
        Log::info('Final timetable data', [
            'lecturer_code' => $user->code,
            'formatted_entries_count' => count($classTimetables)
        ]);
        
        return Inertia::render('Lecturer/ClassTimetable', [
            'classTimetables' => $classTimetables,
            'currentSemester' => $selectedSemester,
            'selectedSemesterId' => $selectedSemesterId,
            'selectedUnitId' => $selectedUnitId,
            'assignedUnits' => $assignedUnits,
            'lecturerSemesters' => $lecturerSemesters,
            'showAllByDefault' => true
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error in class timetable', [
            'lecturer_code' => $user->code,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return Inertia::render('Lecturer/ClassTimetable', [
            'error' => 'An error occurred while loading the timetable: ' . $e->getMessage(),
            'classTimetables' => [],
            'currentSemester' => null,
            'selectedSemesterId' => $request->input('semester_id'),
            'selectedUnitId' => $request->input('unit_id'),
            'assignedUnits' => [],
            'lecturerSemesters' => [],
            'showAllByDefault' => true
        ]);
    }
}
    
    /**
     * Display the lecturer's exam supervision assignments
     */
    public function examSupervision(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            Log::error('Exam Supervision accessed with invalid user');

            return Inertia::render('Lecturer/ExamSupervision', [
                'error' => 'User profile is incomplete. Please contact an administrator.',
                'supervisions' => [],
                'lecturerSemesters' => [],
                'units' => [],
            ]);
        }

        try {
            // Find semesters where the lecturer is assigned units
            $lecturerSemesters = Enrollment::where('lecturer_code', $user->code)
                ->distinct('semester_id')
                ->join('semesters', 'enrollments.semester_id', '=', 'semesters.id')
                ->select('semesters.*')
                ->orderBy('semesters.name')
                ->get();

            // Get all exam timetables where this lecturer is the chief invigilator for the relevant semesters
            $supervisions = ExamTimetable::where('chief_invigilator', $user->name)
                ->whereIn('semester_id', $lecturerSemesters->pluck('id'))
                ->with('unit') // Include unit details
                ->orderBy('date')
                ->orderBy('start_time')
                ->get();

            // Format the supervisions for the view
            $formattedSupervisions = $supervisions->map(function ($exam) {
                return [
                    'id' => $exam->id,
                    'unit_code' => $exam->unit->code ?? '',
                    'unit_name' => $exam->unit->name ?? '',
                    'venue' => $exam->venue,
                    'location' => $exam->location,
                    'day' => $exam->day,
                    'date' => $exam->date,
                    'start_time' => $exam->start_time,
                    'end_time' => $exam->end_time,
                    'no' => $exam->no,
                ];
            });

            // Get all units assigned to the lecturer
            $units = Unit::whereIn('id', $supervisions->pluck('unit_id')->unique())
                ->select('id', 'code', 'name')
                ->get();

            return Inertia::render('Lecturer/ExamSupervision', [
                'supervisions' => $formattedSupervisions,
                'lecturerSemesters' => $lecturerSemesters,
                'units' => $units,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in exam supervision', [
                'user_id' => $user ? $user->id : 'null',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('Lecturer/ExamSupervision', [
                'error' => 'An error occurred while loading supervision assignments. Please try again later.',
                'supervisions' => [],
                'lecturerSemesters' => [],
                'units' => [],
            ]);
        }
    }

    /**
     * Display the lecturer's profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        
        try {
            // Get lecturer details with related data
            $lecturer = User::where('id', $user->id)
                ->first();
                
            return Inertia::render('Lecturer/Profile', [
                'lecturer' => $lecturer,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in lecturer profile', [
                'lecturer_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return Inertia::render('Lecturer/Profile', [
                'error' => 'An error occurred while loading your profile. Please try again later.',
                'lecturer' => null,
            ]);
        }
    }
}