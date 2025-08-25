<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Program;
use App\Models\School;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display units for a specific school and program.
     */
    /**
 * Display all units across all schools (Admin view).
 */
public function index(Request $request, $schoolCode = null)
{
    $user = auth()->user();
    
    // If schoolCode is provided, use school-specific logic
    if ($schoolCode) {
        // Your existing school-specific logic here
        return $this->schoolSpecificIndex($request, $schoolCode);
    }
    
    // Admin-level: Show all units across all schools
    if (!$user->hasRole('Admin') && !$user->can('manage-units')) {
        abort(403, "Unauthorized access to units management.");
    }

    try {
        $query = Unit::with(['program.school', 'semester']);
        
        // Apply filters
        $this->applyFilters($query, $request);

        $units = $query->get()->map(function ($unit) {
            return [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'description' => $unit->description,
                'credit_hours' => $unit->credit_hours,
                'is_active' => $unit->is_active,
                'program_id' => $unit->program_id,
                'program_name' => $unit->program->name,
                'program_code' => $unit->program->code,
                'school_name' => $unit->program->school->name,
                'school_code' => $unit->program->school->code,
                'semester_id' => $unit->semester_id,
                'semester_name' => $unit->semester ? $unit->semester->name : null,
                'created_at' => $unit->created_at,
                'updated_at' => $unit->updated_at,
            ];
        });

        // Get all programs from all schools
        $programs = Program::with('school')
            ->where('is_active', true)
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
        ]);

        return Inertia::render("Admin/Units/Index", [
            'units' => [],
            'programs' => [],
            'schools' => [],
            'semesters' => [],
            'error' => 'Unable to load units. Please try again.',
        ]);
    }
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
     * Show the form for creating a new unit.
     */
    public function create($schoolCode)
    {
        $user = auth()->user();
        $schoolCode = strtoupper($schoolCode);
        
        if (!$this->hasSchoolPermission($user, $schoolCode, 'create')) {
            abort(403, "Unauthorized to create {$schoolCode} units.");
        }

        $school = School::where('code', $schoolCode)->first();
        
        if (!$school) {
            return redirect()->route("schools.{$schoolCode}.units.index")
                ->withErrors(['error' => "{$schoolCode} school not found."]);
        }

        $programs = Program::where('school_id', $school->id)
            ->where('is_active', true)
            ->select('id', 'code', 'name')
            ->orderBy('name')
            ->get();

        $semesters = Semester::where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render("Schools/{$schoolCode}/Units/Create", [
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->code,
            ],
            'programs' => $programs,
            'semesters' => $semesters,
        ]);
    }

    /**
     * Store a newly created unit.
     */
    public function store(Request $request, $schoolCode)
    {
        $user = auth()->user();
        $schoolCode = strtoupper($schoolCode);
        
        if (!$this->hasSchoolPermission($user, $schoolCode, 'create')) {
            abort(403, "Unauthorized to create {$schoolCode} units.");
        }

        $school = School::where('code', $schoolCode)->first();
        
        if (!$school) {
            return back()->withErrors(['error' => "{$schoolCode} school not found."]);
        }

        try {
            $validated = $request->validate([
                'code' => [
                    'required',
                    'string',
                    'max:20',
                    'unique:units,code',
                ],
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'credit_hours' => 'required|integer|min:1|max:6',
                'program_id' => [
                    'required',
                    'exists:programs,id',
                    // Ensure program belongs to this school
                    Rule::exists('programs', 'id')->where('school_id', $school->id),
                ],
                'semester_id' => 'nullable|exists:semesters,id',
                'is_active' => 'boolean',
            ]);

            $unit = Unit::create($validated);

            Log::info("{$schoolCode} Unit created", [
                'unit_id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'school_code' => $schoolCode,
                'created_by' => $user->id
            ]);

            return redirect()->route("schools.{$schoolCode}.units.index")
                ->with('success', 'Unit created successfully.');

        } catch (\Exception $e) {
            Log::error("Error creating {$schoolCode} unit", [
                'school_code' => $schoolCode,
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
     * Display the specified unit.
     */
    public function show($schoolCode, Unit $unit)
    {
        $user = auth()->user();
        $schoolCode = strtoupper($schoolCode);
        
        if (!$this->hasSchoolPermission($user, $schoolCode, 'view')) {
            abort(403, "Unauthorized to view {$schoolCode} unit details.");
        }

        // Ensure unit belongs to the specified school
        if ($unit->program->school->code !== $schoolCode) {
            abort(404, "Unit not found in {$schoolCode}.");
        }

        return Inertia::render("Schools/{$schoolCode}/Units/Show", [
            'unit' => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'description' => $unit->description,
                'credit_hours' => $unit->credit_hours,
                'is_active' => $unit->is_active,
                'program_name' => $unit->program->name,
                'program_code' => $unit->program->code,
                'semester_name' => $unit->semester ? $unit->semester->name : null,
                'created_at' => $unit->created_at,
                'updated_at' => $unit->updated_at,
            ],
            'school' => [
                'id' => $unit->program->school->id,
                'name' => $unit->program->school->name,
                'code' => $unit->program->school->code,
            ],
            'can' => [
                'update' => $this->hasSchoolPermission($user, $schoolCode, 'edit'),
                'delete' => $this->hasSchoolPermission($user, $schoolCode, 'delete'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified unit.
     */
    public function edit($schoolCode, Unit $unit)
    {
        $user = auth()->user();
        $schoolCode = strtoupper($schoolCode);
        
        if (!$this->hasSchoolPermission($user, $schoolCode, 'edit')) {
            abort(403, "Unauthorized to edit {$schoolCode} units.");
        }

        // Ensure unit belongs to the specified school
        if ($unit->program->school->code !== $schoolCode) {
            abort(404, "Unit not found in {$schoolCode}.");
        }

        $programs = Program::where('school_id', $unit->program->school_id)
            ->where('is_active', true)
            ->select('id', 'code', 'name')
            ->orderBy('name')
            ->get();

        $semesters = Semester::where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render("Schools/{$schoolCode}/Units/Edit", [
            'unit' => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'description' => $unit->description,
                'credit_hours' => $unit->credit_hours,
                'program_id' => $unit->program_id,
                'semester_id' => $unit->semester_id,
                'is_active' => $unit->is_active,
            ],
            'programs' => $programs,
            'semesters' => $semesters,
            'school' => [
                'id' => $unit->program->school->id,
                'name' => $unit->program->school->name,
                'code' => $unit->program->school->code,
            ],
        ]);
    }

    /**
     * Update the specified unit.
     */
    public function update(Request $request, $schoolCode, Unit $unit)
    {
        $user = auth()->user();
        $schoolCode = strtoupper($schoolCode);
        
        if (!$this->hasSchoolPermission($user, $schoolCode, 'edit')) {
            abort(403, "Unauthorized to update {$schoolCode} units.");
        }

        // Ensure unit belongs to the specified school
        if ($unit->program->school->code !== $schoolCode) {
            abort(404, "Unit not found in {$schoolCode}.");
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
                'program_id' => [
                    'required',
                    'exists:programs,id',
                    // Ensure program belongs to this school
                    Rule::exists('programs', 'id')->where('school_id', $unit->program->school_id),
                ],
                'semester_id' => 'nullable|exists:semesters,id',
                'is_active' => 'boolean',
            ]);

            $unit->update($validated);

            Log::info("{$schoolCode} Unit updated", [
                'unit_id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'school_code' => $schoolCode,
                'updated_by' => $user->id,
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
     * Remove the specified unit from storage.
     */
    public function destroy($schoolCode, Unit $unit)
    {
        $user = auth()->user();
        $schoolCode = strtoupper($schoolCode);
        
        if (!$this->hasSchoolPermission($user, $schoolCode, 'delete')) {
            abort(403, "Unauthorized to delete {$schoolCode} units.");
        }

        // Ensure unit belongs to the specified school
        if ($unit->program->school->code !== $schoolCode) {
            abort(404, "Unit not found in {$schoolCode}.");
        }

        try {
            // Check if unit has enrollments
            if ($unit->enrollments()->exists()) {
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

    /**
     * Get units for a specific program (API endpoint).
     */
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

    /**
     * Check if user has permission for a specific school action.
     */
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

    /**
     * Apply filters to the query.
     */
    private function applyFilters($query, $request)
    {
        // Search functionality
        if ($request->has('search') && $request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhereHas('program', function($programQuery) use ($search) {
                      $programQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }
        
        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        
        // Filter by program
        if ($request->has('program_id') && $request->filled('program_id')) {
            $query->where('program_id', $request->input('program_id'));
        }
        
        // Filter by semester
        if ($request->has('semester_id') && $request->filled('semester_id')) {
            $query->where('semester_id', $request->input('semester_id'));
        }
        
        // Sorting
        $sortField = $request->input('sort_field', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);
    }

    /**
     * Get statistics for units in a school.
     */
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
}