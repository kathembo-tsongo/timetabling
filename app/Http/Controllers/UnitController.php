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
     * Display units for a specific program with enhanced context
     */
    public function programUnits(Program $program, Request $request, $schoolCode)
{
    $schoolCode = strtoupper($schoolCode);
    $user = auth()->user();
    
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
    
    Log::info("Program units accessed", [
        'program_id' => $program->id,
        'program_code' => $program->code,
        'school_code' => $schoolCode,
        'user_id' => auth()->id(),
        'total_units' => $units->total()
    ]);
    
    return Inertia::render('Schools/SCES/Programs/Units/Index', [
        'units' => $units,
        'program' => $program->load('school'),
        'schoolCode' => $schoolCode,
        'filters' => [
            'search' => $search,
            'per_page' => (int) $perPage,
        ],
        'can' => [
            // ✅ Fixed: Use hyphens to match actual permissions
            'create' => $user->hasRole('Admin') || 
                       $user->can('manage-units') || 
                       $user->can('edit-units'),
            
            'update' => $user->hasRole('Admin') || 
                       $user->can('manage-units') || 
                       $user->can('edit-units'),
            
            'delete' => $user->hasRole('Admin') || 
                       $user->can('manage-units') || 
                       $user->can('delete-units'),
        ],
        'flash' => [
            'success' => session('success'),
            'error' => session('error'),
        ],
    ]);
}

    /**
     * Update program unit with enhanced validation and context logging
     */
    public function updateProgramUnit(Program $program, Unit $unit, Request $request, $schoolCode)
    {
        $schoolCode = strtoupper($schoolCode);
        
        // Verify program belongs to the correct school
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        // Verify unit belongs to this program
        if ($unit->program_id !== $program->id) {
            Log::warning("Attempt to update unit from different program", [
                'unit_id' => $unit->id,
                'unit_program_id' => $unit->program_id,
                'expected_program_id' => $program->id,
                'user_id' => auth()->id()
            ]);
            abort(404, 'Unit not found in this program.');
        }

        $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('units', 'code')->ignore($unit->id)
            ],
            'name' => 'required|string|max:255',
            'credit_hours' => 'required|integer|min:1|max:10',
            'is_active' => 'boolean',
        ], [
            'code.unique' => 'This unit code is already in use by another unit.',
            'code.required' => 'Unit code is required.',
            'name.required' => 'Unit name is required.',
            'credit_hours.required' => 'Credit hours are required.',
            'credit_hours.min' => 'Credit hours must be at least 1.',
            'credit_hours.max' => 'Credit hours cannot exceed 10.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }
        
        try {
            DB::beginTransaction();

            // Store original values for logging
            $originalData = [
                'code' => $unit->code,
                'name' => $unit->name,
                'credit_hours' => $unit->credit_hours,
                'is_active' => $unit->is_active
            ];

            $unit->update([
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'credit_hours' => $request->credit_hours,
                'is_active' => $request->boolean('is_active', true),
            ]);

            Log::info("Unit updated successfully", [
                'unit_id' => $unit->id,
                'program_id' => $program->id,
                'program_code' => $program->code,
                'program_name' => $program->name,
                'school_code' => $schoolCode,
                'school_name' => $program->school->name,
                'updated_by' => auth()->id(),
                'original' => $originalData,
                'changes' => $unit->getChanges()
            ]);

            DB::commit();
            
            return redirect()->back()->with('success', "Unit '{$unit->code}' updated successfully!");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Error updating program unit", [
                'unit_id' => $unit->id,
                'program_id' => $program->id,
                'school_code' => $schoolCode,
                'data' => $request->except(['_token', '_method']),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error updating unit. Please try again or contact support.');
        }
    }

    /**
     * Delete program unit with enhanced validation and context logging
     */
    public function destroyProgramUnit(Program $program, Unit $unit, $schoolCode)
    {
        $schoolCode = strtoupper($schoolCode);
        
        // Verify program belongs to the correct school
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        // Verify unit belongs to this program
        if ($unit->program_id !== $program->id) {
            Log::warning("Attempt to delete unit from different program", [
                'unit_id' => $unit->id,
                'unit_program_id' => $unit->program_id,
                'expected_program_id' => $program->id,
                'user_id' => auth()->id()
            ]);
            abort(404, 'Unit not found in this program.');
        }

        try {
            DB::beginTransaction();

            // Check for dependencies
            $enrollmentCount = 0;
            $assignmentCount = UnitAssignment::where('unit_id', $unit->id)->count();
            
            if (method_exists($unit, 'enrollments')) {
                $enrollmentCount = $unit->enrollments()->count();
            }

            if ($enrollmentCount > 0) {
                Log::warning("Attempt to delete unit with enrollments", [
                    'unit_id' => $unit->id,
                    'unit_code' => $unit->code,
                    'enrollment_count' => $enrollmentCount,
                    'program_id' => $program->id,
                    'school_code' => $schoolCode,
                    'user_id' => auth()->id()
                ]);

                return redirect()->back()->with('error', 
                    "Cannot delete unit '{$unit->code}' because it has {$enrollmentCount} associated enrollment(s). " .
                    "Please remove all enrollments first."
                );
            }

            if ($assignmentCount > 0) {
                Log::warning("Attempt to delete unit with assignments", [
                    'unit_id' => $unit->id,
                    'unit_code' => $unit->code,
                    'assignment_count' => $assignmentCount,
                    'program_id' => $program->id,
                    'school_code' => $schoolCode,
                    'user_id' => auth()->id()
                ]);

                return redirect()->back()->with('error', 
                    "Cannot delete unit '{$unit->code}' because it has {$assignmentCount} class assignment(s). " .
                    "Please remove all assignments first."
                );
            }

            // Store info for logging before deletion
            $unitInfo = [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'credit_hours' => $unit->credit_hours,
                'program_id' => $program->id,
                'program_code' => $program->code,
                'program_name' => $program->name,
                'school_id' => $program->school_id,
                'school_code' => $schoolCode,
                'school_name' => $program->school->name,
            ];

            $unit->delete();

            Log::info("Unit deleted successfully", array_merge($unitInfo, [
                'deleted_by' => auth()->id(),
                'deleted_at' => now()->toDateTimeString()
            ]));

            DB::commit();
            
            return redirect()->back()->with('success', "Unit '{$unitInfo['code']}' deleted successfully from {$program->name}!");

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Error deleting program unit", [
                'unit_id' => $unit->id,
                'unit_code' => $unit->code,
                'program_id' => $program->id,
                'school_code' => $schoolCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return redirect()->back()->with('error', 'Error deleting unit. Please try again or contact support.');
        }
    }

    /**
     * Show specific program unit with enhanced context
     */
    public function showProgramUnit(Program $program, Unit $unit, $schoolCode)
    {
        $schoolCode = strtoupper($schoolCode);
        
        // Verify program belongs to the correct school
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        // Verify unit belongs to this program
        if ($unit->program_id !== $program->id) {
            abort(404, 'Unit not found in this program.');
        }

        Log::info("Unit details viewed", [
            'unit_id' => $unit->id,
            'unit_code' => $unit->code,
            'program_id' => $program->id,
            'program_code' => $program->code,
            'school_code' => $schoolCode,
            'user_id' => auth()->id()
        ]);

        return Inertia::render('Schools/Programs/Units/Show', [
    'unit' => $unit->load(['school', 'program']),
    'program' => $program->load('school'),
    'schoolCode' => $schoolCode,
    'can' => [
        // ✅ Fixed: Use hyphens
        'update' => $user->hasRole('Admin') || 
                   $user->can('manage-units') || 
                   $user->can('edit-units'),
        'delete' => $user->hasRole('Admin') || 
                   $user->can('manage-units') || 
                   $user->can('delete-units'),
    ],
]);
    }

    /**
     * Admin-level index with enhanced filtering and context
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-units')) {
            abort(403, "Unauthorized access to units management.");
        }

        try {
            $query = Unit::query();
            
            $this->applyFilters($query, $request);

            $units = $query->get()->map(function ($unit) {
                $program = $unit->program_id ? Program::find($unit->program_id) : null;
                $school = ($program && $program->school_id) ? School::find($program->school_id) : null;
                
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
                    'semester_name' => $semester?->name ?? null,
                    'created_at' => $unit->created_at->toISOString(),
                    'updated_at' => $unit->updated_at->toISOString(),
                ];
            });

            $programs = Program::where('is_active', true)
                ->select('id', 'code', 'name', 'school_id')
                ->orderBy('name')
                ->get();

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
     * Apply filters with safe relationship checks
     */
    private function applyFilters($query, $request)
    {
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
        
        if ($request->has('school_id') && $request->filled('school_id')) {
            $query->whereHas('program', function($q) use ($request) {
                $q->where('school_id', $request->input('school_id'));
            });
        }
        
        if ($request->has('is_active') && $request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        
        if ($request->has('program_id') && $request->filled('program_id')) {
            $query->where('program_id', $request->input('program_id'));
        }
        
        if ($request->has('semester_id') && $request->filled('semester_id')) {
            $query->where('semester_id', $request->input('semester_id'));
        }
        
        $sortField = $request->input('sort_field', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);
    }

    /**
     * Get statistics for all units
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

    // units assignment to lecturers
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

    $user = auth()->user();

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
            'assign' => $user->hasRole('Admin') || 
                       $user->can('manage-units') || 
                       $user->can('edit-units') ||
                       $user->can('assign-units'),
            'remove' => $user->hasRole('Admin') || 
                       $user->can('manage-units') || 
                       $user->can('delete-units'),
        ],
        'flash' => [
            'success' => session('success'),
            'error' => session('error'),
        ]
    ]);
}
}