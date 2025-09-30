<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Program;
use App\Models\School;
use App\Models\Semester;
use App\Models\ClassModel;
use App\Models\UnitAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display all units across all schools (Admin view).
     * Fixed to handle NULL relationships gracefully.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Admin-level: Show all units across all schools
        if (!$user->hasRole('Admin') && !$user->can('manage-units')) {
            abort(403, "Unauthorized access to units management.");
        }

        try {
            // Don't eager load relationships that might not exist - load them manually
            $query = Unit::query();
            
            // Apply admin filters
            $this->applyFilters($query, $request);

            $units = $query->get()->map(function ($unit) {
        // Get the program and school as you're doing
        $program = $unit->program_id ? Program::find($unit->program_id) : null;
        $school = ($program && $program->school_id) ? School::find($program->school_id) : null;
        
        // Get semester from unit assignments instead of direct relationship
        $activeAssignment = UnitAssignment::where('unit_id', $unit->id)
            ->where('is_active', true)
            ->with('semester')
            ->first();
        
        $semester = $activeAssignment ? $activeAssignment->semester : null;
        
        return [
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'description' => $unit->description,
            'credit_hours' => $unit->credit_hours,
            'is_active' => $unit->is_active,
            'program_id' => $unit->program_id,
            'program_name' => $program?->name ?? 'No Program Assigned',
            'program_code' => $program?->code ?? 'N/A',
            'school_id' => $school?->id ?? null,
            'school_name' => $school?->name ?? 'No School Assigned',
            'school_code' => $school?->code ?? 'N/A',
            'semester_id' => $semester?->id ?? null,
            'semester_name' => $semester?->name ?? null, // This will now show correctly
            'created_at' => $unit->created_at->toISOString(),
            'updated_at' => $unit->updated_at->toISOString(),
        ];
    });

            // Get all programs from all schools
            $programs = Program::where('is_active', true)
                ->select('id', 'code', 'name', 'school_id')
                ->orderBy('name')
                ->get();

            // Get all schools
            $schools = School::where('is_active', true)
                ->select('id', 'code', 'name')
                ->orderBy('name')
                ->get();

            $semesters = Semester::where('is_active', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();

            return Inertia::render("Admin/Units/Index", [
                'units' => $units,
                'programs' => $programs,
                'schools' => $schools,
                'semesters' => $semesters,
                'can' => [
                    'create' => $user->can('manage-units') || $user->hasRole('Admin'),
                    'update' => $user->can('manage-units') || $user->hasRole('Admin'),
                    'delete' => $user->can('manage-units') || $user->hasRole('Admin'),
                ],
                'filters' => $request->only([
                    'search', 'program_id', 'school_id', 'semester_id', 'is_active', 
                    'sort_field', 'sort_direction'
                ]),
                'stats' => $this->getAllUnitsStats(),
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching admin units", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render("Admin/Units/Index", [
                'units' => [],
                'programs' => [],
                'schools' => [],
                'semesters' => [],
                'can' => [
                    'create' => false,
                    'update' => false,
                    'delete' => false,
                ],
                'filters' => [],
                'stats' => [
                    'total' => 0,
                    'active' => 0,
                    'inactive' => 0,
                    'assigned_to_semester' => 0,
                    'unassigned' => 0,
                ],
                'error' => 'Unable to load units. Please try again.',
            ]);
        }
    }

    /**
     * Apply filters with safe relationship checks.
     */
    private function applyFilters($query, $request)
    {
        // Search functionality - updated to handle NULL relationships
        if ($request->has('search') && $request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhereHas('program', function($programQuery) use ($search) {
                      $programQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('code', 'like', "%{$search}%");
                  })
                  ->orWhereHas('program.school', function($schoolQuery) use ($search) {
                      $schoolQuery->where('name', 'like', "%{$search}%")
                                  ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }
        
        // Filter by school - only apply if program relationship exists
        if ($request->has('school_id') && $request->filled('school_id')) {
            $query->whereHas('program', function($q) use ($request) {
                $q->where('school_id', $request->input('school_id'));
            });
        }
        
        // Filter by active status
        if ($request->has('is_active') && $request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        
        // Filter by program - only apply if not null
        if ($request->has('program_id') && $request->filled('program_id')) {
            $query->where('program_id', $request->input('program_id'));
        }
        
        // Filter by semester - only apply if not null
        if ($request->has('semester_id') && $request->filled('semester_id')) {
            $query->where('semester_id', $request->input('semester_id'));
        }
        
        // Sorting
        $sortField = $request->input('sort_field', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);
    }

    /**
     * Get statistics for all units across all schools.
     */
    private function getAllUnitsStats()
    {
        return [
            'total' => Unit::count(),
            'active' => Unit::where('is_active', true)->count(),
            'inactive' => Unit::where('is_active', false)->count(),
            'assigned_to_semester' => Unit::whereNotNull('semester_id')->count(),
            'unassigned' => Unit::whereNull('semester_id')->count(),
        ];
    }

    /**
     * Store a newly created unit (Admin).
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-units')) {
            abort(403, "Unauthorized to create units.");
        }

        try {
            $validated = $request->validate([
                'code' => 'required|string|max:20|unique:units,code',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'credit_hours' => 'required|integer|min:1|max:6',
                'program_id' => 'required|exists:programs,id',
                'semester_id' => 'nullable|exists:semesters,id',
                'is_active' => 'boolean',
            ]);

            $unit = Unit::create($validated);

           Log::error("Error updating unit", [
    'unit_id' => $unit->id,
    'data' => $request->all(),
    'error' => $e->getMessage(),
    'user_id' => $user->id
]);

            return redirect()->route('admin.units.index')
                ->with('success', 'Unit created successfully.');

        } catch (\Exception $e) {
            Log::error("Error creating admin unit", [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return back()
                ->withErrors(['error' => 'Failed to create unit. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Update a unit (Admin).
     */
    public function update(Request $request, Unit $unit)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-units')) {
            abort(403, "Unauthorized to update units.");
        }

        try {
            $validated = $request->validate([
                'code' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('units', 'code')->ignore($unit->id),
                ],
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'credit_hours' => 'required|integer|min:1|max:6',
                'program_id' => 'required|exists:programs,id',
                'semester_id' => 'nullable|exists:semesters,id',
                'is_active' => 'boolean',
            ]);

            $unit->update($validated);

            Log::info("Admin Unit updated", [
                'unit_id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'changes' => $unit->getChanges()
            ]);

            return back()->with('success', 'Unit updated successfully.');

        } catch (\Exception $e) {
            Log::error("Error updating {$schoolCode} unit", [
                'unit_id' => $unit->id,
                'school_code' => $schoolCode,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return back()
                ->withErrors(['error' => 'Failed to update unit. Please try again.'])
                ->withInput();
        }
    }

/**
 * Destroy a unit (Admin).
 */
public function destroy(Unit $unit)
{
    $user = auth()->user();

    if (!$user->hasRole('Admin') && !$user->can('manage-units')) {
        abort(403, "Unauthorized to delete units.");
    }

    try {
        // Check if unit has enrollments (if applicable)
        if (method_exists($unit, 'enrollments') && $unit->enrollments()->exists()) {
            return back()->withErrors([
                'error' => 'Cannot delete unit because it has associated enrollments.'
            ]);
        }

        $unitName = $unit->name;
        $unit->delete();

        \Log::info("Unit deleted", [
            'unit_id' => $unit->id,
            'unit_name' => $unitName,
            'deleted_by' => $user->id
        ]);

        return redirect()->route('admin.units.index')
            ->with('success', "Unit '{$unitName}' deleted successfully.");

    } catch (\Exception $e) {
        \Log::error("Error deleting unit", [
            'unit_id' => $unit->id,
            'error' => $e->getMessage(),
            'user_id' => $user->id
        ]);

        return back()->withErrors([
            'error' => 'Failed to delete unit. Please try again.'
        ]);
    }
}


    public function schoolDestroy($schoolCode, Unit $unit)
    {
        $user = auth()->user();
        $schoolCode = strtoupper($schoolCode);
        
        if (!$this->hasSchoolPermission($user, $schoolCode, 'delete')) {
            abort(403, "Unauthorized to delete {$schoolCode} units.");
        }

        // Safely check if unit belongs to the specified school
        $program = $unit->program;
        if (!$program || !$program->school || $program->school->code !== $schoolCode) {
            abort(404, "Unit not found in {$schoolCode}.");
        }

        try {
            // Check if unit has enrollments
            if (method_exists($unit, 'enrollments') && $unit->enrollments()->exists()) {
                return back()->withErrors([
                    'error' => 'Cannot delete unit because it has associated enrollments.'
                ]);
            }

            $unitName = $unit->name;
            $unit->delete();

            Log::info("{$schoolCode} Unit deleted", [
                'unit_name' => $unitName,
                'school_code' => $schoolCode,
                'deleted_by' => $user->id
            ]);

            return redirect()->route("schools.{$schoolCode}.units.index")
                ->with('success', "Unit '{$unitName}' deleted successfully.");

        } catch (\Exception $e) {
            Log::error("Error deleting {$schoolCode} unit", [
                'unit_id' => $unit->id,
                'school_code' => $schoolCode,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return back()->withErrors([
                'error' => 'Failed to delete unit. Please try again.'
            ]);
        }
    }

    public function getUnitsByProgram($schoolCode, $programId)
    {
        $schoolCode = strtoupper($schoolCode);
        
        try {
            $units = Unit::whereHas('program', function($q) use ($schoolCode, $programId) {
                $q->where('school_id', function($subQuery) use ($schoolCode) {
                    $subQuery->select('id')->from('schools')->where('code', $schoolCode);
                })->where('id', $programId);
            })
            ->where('is_active', true)
            ->select('id', 'name', 'code', 'credit_hours')
            ->orderBy('name')
            ->get();

            return response()->json([
                'success' => true,
                'units' => $units
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching units by program', [
                'school_code' => $schoolCode,
                'program_id' => $programId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'units' => [],
                'message' => 'Failed to fetch units'
            ], 500);
        }
    }

    private function hasSchoolPermission($user, $schoolCode, $action)
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        $schoolCode = strtolower($schoolCode);
        
        switch ($action) {
            case 'view':
                return $user->can("view-faculty-units-{$schoolCode}");
            case 'create':
                return $user->can("create-faculty-units-{$schoolCode}");
            case 'edit':
                return $user->can("edit-faculty-units-{$schoolCode}");
            case 'delete':
                return $user->can("delete-faculty-units-{$schoolCode}");
            default:
                return false;
        }
    }

    private function getStats($schoolId)
    {
        $query = Unit::whereHas('program', function($q) use ($schoolId) {
            $q->where('school_id', $schoolId);
        });
        
        return [
            'total' => $query->count(),
            'active' => $query->where('is_active', true)->count(),
            'inactive' => $query->where('is_active', false)->count(),
            'assigned_to_semester' => $query->whereNotNull('semester_id')->count(),
            'unassigned' => $query->whereNull('semester_id')->count(),
        ];
    }

    public function show(Unit $unit)
{
    return response()->json($unit->load(['school', 'program', 'semester']));
}

// Show assignment interface

public function assignSemesters(Request $request)
{
    $search = $request->search ?? '';
    $semesterId = $request->semester_id;
    $programId = $request->program_id;
    $classId = $request->class_id;
    
    // Get unassigned units (units without any assignments)
    $unassignedUnits = Unit::with(['program.school'])
        ->whereDoesntHave('assignments')
        ->when($search, function($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%')
              ->orWhere('code', 'like', '%' . $search . '%');
        })
        ->when($programId, function($q) use ($programId) {
            $q->where('program_id', $programId);
        })
        ->orderBy('name')
        ->get();

    // Get assigned units with their assignments
    $assignedUnitsQuery = UnitAssignment::with([
        'unit.program.school', 
        'semester', 
        'class'
    ])
        ->when($semesterId, function($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        })
        ->when($programId, function($q) use ($programId) {
            $q->whereHas('unit', function($uq) use ($programId) {
                $uq->where('program_id', $programId);
            });
        })
        ->when($classId, function($q) use ($classId) {
            $q->where('class_id', $classId);
        })
        ->orderBy('created_at', 'desc');

    $assignedUnits = $assignedUnitsQuery->get();

    // Get dropdown data
    $schools = School::select('id', 'name', 'code')->orderBy('name')->get();
    $programs = Program::with('school')->select('id', 'name', 'code', 'school_id')->orderBy('name')->get();
    $semesters = Semester::select('id', 'name', 'is_active')->orderBy('name')->get();
    
    // Get all classes for initial load, or filtered classes if parameters are provided
    $classes = ClassModel::with(['semester:id,name', 'program:id,name,code'])
        ->when($semesterId, function($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        })
        ->when($programId, function($q) use ($programId) {
            $q->where('program_id', $programId);
        })
        ->where('is_active', true)
        ->select('id', 'name', 'section', 'year_level', 'semester_id', 'program_id', 'capacity')
        ->orderBy('name')
        ->orderBy('section')
        ->get()
        ->map(function($class) {
            return [
                'id' => $class->id,
                'name' => $class->name,
                'section' => $class->section,
                'display_name' => "{$class->name} Section {$class->section}",
                'year_level' => $class->year_level,
                'capacity' => $class->capacity,
            ];
        });

    return Inertia::render('Schools/SCES/Programs/UnitAssignments/AssignSemesters', [
        'unassigned_units' => $unassignedUnits,
        'assigned_units' => $assignedUnits,
        'schools' => $schools,
        'programs' => $programs,
        'semesters' => $semesters,
        'classes' => $classes,
        'filters' => [
            'search' => $search,
            'semester_id' => $semesterId ? (int) $semesterId : null,
            'program_id' => $programId ? (int) $programId : null,
            'class_id' => $classId ? (int) $classId : null,
        ],
        'stats' => [
            'total_units' => Unit::count(),
            'unassigned_count' => $unassignedUnits->count(),
            'assigned_count' => $assignedUnits->count(),
        ],
        'flash' => [
            'success' => session('success'),
            'error' => session('error'),
        ]
    ]);
}

public function assignToSemester(Request $request)
{
    $validated = $request->validate([
        'unit_ids' => 'required|array',
        'unit_ids.*' => 'exists:units,id',
        'semester_id' => 'required|exists:semesters,id',
        'class_ids' => 'required|array', // Changed from 'class_id'
        'class_ids.*' => 'exists:classes,id' // Validate each class ID
    ]);

    try {
        $createdAssignments = 0;
        
        foreach ($validated['unit_ids'] as $unitId) {
            foreach ($validated['class_ids'] as $classId) {
                // Get the program_id from the unit
                $unit = Unit::find($unitId);
                
                // Check if assignment already exists
                $existingAssignment = UnitAssignment::where([
                    'unit_id' => $unitId,
                    'semester_id' => $validated['semester_id'],
                    'class_id' => $classId,
                ])->first();
                
                if (!$existingAssignment) {
                    UnitAssignment::create([
                        'unit_id' => $unitId,
                        'semester_id' => $validated['semester_id'],
                        'class_id' => $classId,
                        'program_id' => $unit->program_id,
                        'is_active' => true
                    ]);
                    $createdAssignments++;
                }
            }
        }

        $message = "Successfully assigned units to {$createdAssignments} class-unit combinations";
        return redirect()->back()->with('success', $message);
        
    } catch (\Exception $e) {
        \Log::error('Error assigning units to classes: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Error assigning units to classes. Please try again.');
    }
}

public function removeFromSemester(Request $request)
{
    $request->validate([
        'assignment_ids' => 'required|array',
        'assignment_ids.*' => 'exists:unit_assignments,id',
    ]);

    try {
        $removed = UnitAssignment::whereIn('id', $request->assignment_ids)->delete();
        
        return redirect()->back()->with('success', "Successfully removed {$removed} unit assignments.");
        
    } catch (\Exception $e) {
        Log::error('Error removing unit assignments: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Error removing assignments. Please try again.');
    }
}
public function getUnitsForClass(Request $request)
{
    $request->validate([
        'class_id' => 'required|exists:classes,id',
        'semester_id' => 'required|exists:semesters,id',
    ]);

    try {
        // Get units that are assigned to this specific class
        $units = Unit::whereHas('assignments', function($query) use ($request) {
            $query->where('class_id', $request->class_id)
                  ->where('semester_id', $request->semester_id)
                  ->where('is_active', true);
        })
        ->with(['school', 'program', 'assignments' => function($query) use ($request) {
            $query->where('class_id', $request->class_id)
                  ->where('semester_id', $request->semester_id);
        }])
        ->get();

        return response()->json($units);
    } catch (\Exception $e) {
        Log::error('Error fetching units for class: ' . $e->getMessage());
        return response()->json(['error' => 'Failed to fetch units'], 500);
    }
}

// specific methods
/**
 * Display units for a specific program
 */
public function programUnits(Program $program, Request $request, $schoolCode)
{
    // Verify program belongs to the correct school
    if ($program->school->code !== $schoolCode) {
        abort(404, 'Program not found in this school.');
    }

    $perPage = $request->per_page ?? 10;
    $search = $request->search ?? '';
    
    // Build the query for program-specific units
    $query = Unit::where('program_id', $program->id)
        ->with(['school', 'program'])
        ->when($search, function($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%')
              ->orWhere('code', 'like', '%' . $search . '%');
        })
        ->orderBy('code')
        ->orderBy('name');
    
    // Get paginated results
    $units = $query->paginate($perPage)->withQueryString();
    
    return Inertia::render('Schools/SCES/Programs/Units/Index', [
        'units' => $units, // This already includes data, links, meta from Laravel pagination
        'program' => $program->load('school'),
        'schoolCode' => $schoolCode,
        'filters' => [
            'search' => $search,
            'per_page' => (int) $perPage, // Make sure this is included
        ],
        'can' => [
            'create' => auth()->user()->can('create_units'),
            'update' => auth()->user()->can('update_units'),
            'delete' => auth()->user()->can('delete_units'),
        ],
        'flash' => [
            'success' => session('success'),
            'error' => session('error'),
        ],
    ]);
}

/**
 * Store a new unit for a specific program (Updated to remove description)
 */
public function storeProgramUnit(Program $program, Request $request, $schoolCode)
{
    // Verify program belongs to the correct school
    if ($program->school->code !== $schoolCode) {
        abort(404, 'Program not found in this school.');
    }

    $validator = Validator::make($request->all(), [
        'code' => 'required|string|max:20|unique:units,code',
        'name' => 'required|string|max:255',
        'credit_hours' => 'required|integer|min:1|max:10',
        'is_active' => 'boolean',
    ]);

    if ($validator->fails()) {
        return redirect()->back()
            ->withErrors($validator)
            ->withInput()
            ->with('error', 'Please check the form for errors.');
    }

    try {
        Unit::create([
            'code' => $request->code,
            'name' => $request->name,
            'credit_hours' => $request->credit_hours,
            'program_id' => $program->id,
            'school_id' => $program->school_id,
            'is_active' => $request->boolean('is_active', true),
        ]);
        
        return redirect()->back()->with('success', 'Unit created successfully!');
    } catch (\Exception $e) {
        Log::error('Error creating program unit: ' . $e->getMessage());
        return redirect()->back()
            ->withInput()
            ->with('error', 'Error creating unit. Please try again.');
    }
}

/**
 * Update program unit (Updated to remove description)
 */
public function updateProgramUnit(Program $program, Unit $unit, Request $request, $schoolCode)
{
    // Verify program belongs to the correct school
    if ($program->school->code !== $schoolCode) {
        abort(404, 'Program not found in this school.');
    }

    // Verify unit belongs to this program
    if ($unit->program_id !== $program->id) {
        abort(404, 'Unit not found in this program.');
    }

    $validator = Validator::make($request->all(), [
        'code' => 'required|string|max:20|unique:units,code,' . $unit->id,
        'name' => 'required|string|max:255',
        'credit_hours' => 'required|integer|min:1|max:10',
        'is_active' => 'boolean',
    ]);

    if ($validator->fails()) {
        return redirect()->back()
            ->withErrors($validator)
            ->withInput()
            ->with('error', 'Please check the form for errors.');
    }
    
    try {
        $unit->update([
            'code' => $request->code,
            'name' => $request->name,
            'credit_hours' => $request->credit_hours,
            'is_active' => $request->boolean('is_active', true),
        ]);
        
        return redirect()->back()->with('success', 'Unit updated successfully!');
    } catch (\Exception $e) {
        Log::error('Error updating program unit: ' . $e->getMessage());
        return redirect()->back()
            ->withInput()
            ->with('error', 'Error updating unit. Please try again.');
    }
}



    /**
     * Show create form for program unit
     */
    public function createProgramUnit(Program $program, $schoolCode)
    {
        // Verify program belongs to the correct school
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        return Inertia::render('Schools/Programs/Units/Create', [
            'program' => $program->load('school'),
            'schoolCode' => $schoolCode,
        ]);
    }

    /**
     * Show specific program unit
     */
    public function showProgramUnit(Program $program, Unit $unit, $schoolCode)
    {
        // Verify program belongs to the correct school
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        // Verify unit belongs to this program
        if ($unit->program_id !== $program->id) {
            abort(404, 'Unit not found in this program.');
        }

        return Inertia::render('Schools/Programs/Units/Show', [
            'unit' => $unit->load(['school', 'program']),
            'program' => $program->load('school'),
            'schoolCode' => $schoolCode,
        ]);
    }

    /**
     * Show edit form for program unit
     */
    public function editProgramUnit(Program $program, Unit $unit, $schoolCode)
    {
        // Verify program belongs to the correct school
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        // Verify unit belongs to this program
        if ($unit->program_id !== $program->id) {
            abort(404, 'Unit not found in this program.');
        }

        return Inertia::render('Schools/Programs/Units/Edit', [
            'unit' => $unit,
            'program' => $program->load('school'),
            'schoolCode' => $schoolCode,
        ]);
    }
  

    /**
     * Delete program unit
     */
    public function destroyProgramUnit(Program $program, Unit $unit, $schoolCode)
    {
        // Verify program belongs to the correct school
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        // Verify unit belongs to this program
        if ($unit->program_id !== $program->id) {
            abort(404, 'Unit not found in this program.');
        }

        try {
            // Check if unit has enrollments or other associations
            $hasEnrollments = $unit->enrollments()->exists();
            
            if ($hasEnrollments) {
                return redirect()->back()->with('error', 'Cannot delete unit with existing enrollments.');
            }
            
            $unit->delete();
            
            return redirect()->back()->with('success', 'Unit deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Error deleting program unit: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error deleting unit. Please try again.');
        }
    }

    /**
 * Show unit assignment interface for a specific program
 */
public function programUnitAssignments(Program $program, Request $request, $schoolCode)
{
    // Verify program belongs to the correct school
    if ($program->school->code !== $schoolCode) {
        abort(404, 'Program not found in this school.');
    }

    $search = $request->search ?? '';
    $semesterId = $request->semester_id;
    $classId = $request->class_id;
    
    // Get unassigned units for this program
    $unassignedUnits = Unit::where('program_id', $program->id)
        ->whereDoesntHave('assignments')
        ->when($search, function($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%')
              ->orWhere('code', 'like', '%' . $search . '%');
        })
        ->orderBy('name')
        ->get();

    // Get assigned units with their assignments for this program
    $assignedUnitsQuery = UnitAssignment::with([
        'unit', 
        'semester', 
        'class'
    ])
        ->whereHas('unit', function($q) use ($program) {
            $q->where('program_id', $program->id);
        })
        ->when($semesterId, function($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        })
        ->when($classId, function($q) use ($classId) {
            $q->where('class_id', $classId);
        })
        ->orderBy('created_at', 'desc');

    $assignedUnits = $assignedUnitsQuery->get();

    // Get semesters
    $semesters = Semester::where('is_active', true)
        ->select('id', 'name')
        ->orderBy('name')
        ->get();
    
    // Get classes for this program
    $classes = ClassModel::where('program_id', $program->id)
        ->when($semesterId, function($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        })
        ->where('is_active', true)
        ->with(['semester:id,name'])
        ->select('id', 'name', 'section', 'year_level', 'semester_id', 'program_id', 'capacity')
        ->orderBy('name')
        ->orderBy('section')
        ->get()
        ->map(function($class) {
            return [
                'id' => $class->id,
                'name' => $class->name,
                'section' => $class->section,
                'display_name' => "{$class->name} Section {$class->section}",
                'year_level' => $class->year_level,
                'capacity' => $class->capacity,
            ];
        });

    return Inertia::render('Schools/SCES/Programs/UnitAssignments/Index', [
        'unassigned_units' => $unassignedUnits,
        'assigned_units' => $assignedUnits,
        'program' => $program->load('school'),
        'schoolCode' => $schoolCode,
        'semesters' => $semesters,
        'classes' => $classes,
        'filters' => [
            'search' => $search,
            'semester_id' => $semesterId ? (int) $semesterId : null,
            'class_id' => $classId ? (int) $classId : null,
        ],
        'stats' => [
            'total_units' => Unit::where('program_id', $program->id)->count(),
            'unassigned_count' => $unassignedUnits->count(),
            'assigned_count' => $assignedUnits->count(),
        ],
        'can' => [
            'assign' => auth()->user()->can('create_units'),
            'remove' => auth()->user()->can('delete_units'),
        ],
        'flash' => [
            'success' => session('success'),
            'error' => session('error'),
        ]
    ]);
}

/**
 * Assign units to semester for a specific program
 */
public function assignProgramUnitsToSemester(Program $program, Request $request, $schoolCode)
{
    // Verify program belongs to the correct school
    if ($program->school->code !== $schoolCode) {
        abort(404, 'Program not found in this school.');
    }

    $validated = $request->validate([
        'unit_ids' => 'required|array',
        'unit_ids.*' => 'exists:units,id',
        'semester_id' => 'required|exists:semesters,id',
        'class_ids' => 'required|array',
        'class_ids.*' => 'exists:classes,id'
    ]);

    try {
        $createdAssignments = 0;
        
        foreach ($validated['unit_ids'] as $unitId) {
            // Verify unit belongs to this program
            $unit = Unit::where('id', $unitId)
                ->where('program_id', $program->id)
                ->firstOrFail();
            
            foreach ($validated['class_ids'] as $classId) {
                // Verify class belongs to this program
                $class = ClassModel::where('id', $classId)
                    ->where('program_id', $program->id)
                    ->firstOrFail();
                
                // Check if assignment already exists
                $existingAssignment = UnitAssignment::where([
                    'unit_id' => $unitId,
                    'semester_id' => $validated['semester_id'],
                    'class_id' => $classId,
                ])->first();
                
                if (!$existingAssignment) {
                    UnitAssignment::create([
                        'unit_id' => $unitId,
                        'semester_id' => $validated['semester_id'],
                        'class_id' => $classId,
                        'program_id' => $program->id,
                        'is_active' => true
                    ]);
                    $createdAssignments++;
                }
            }
        }

        $message = "Successfully assigned units to {$createdAssignments} class-unit combinations";
        return redirect()->back()->with('success', $message);
        
    } catch (\Exception $e) {
        Log::error('Error assigning program units: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Error assigning units. Please try again.');
    }
}

/**
 * Remove units from semester for a specific program
 */
public function removeProgramUnitsFromSemester(Program $program, Request $request, $schoolCode)
{
    // Verify program belongs to the correct school
    if ($program->school->code !== $schoolCode) {
        abort(404, 'Program not found in this school.');
    }

    $validated = $request->validate([
        'assignment_ids' => 'required|array',
        'assignment_ids.*' => 'exists:unit_assignments,id',
    ]);

    try {
        // Verify all assignments belong to this program
        $assignments = UnitAssignment::whereIn('id', $validated['assignment_ids'])
            ->where('program_id', $program->id)
            ->get();
        
        if ($assignments->count() !== count($validated['assignment_ids'])) {
            return redirect()->back()->with('error', 'Some assignments do not belong to this program.');
        }
        
        $removed = UnitAssignment::whereIn('id', $validated['assignment_ids'])->delete();
        
        return redirect()->back()->with('success', "Successfully removed {$removed} unit assignments.");
        
    } catch (\Exception $e) {
        Log::error('Error removing program unit assignments: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Error removing assignments. Please try again.');
    }
}
}