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
                // Safely load relationships with null checks
                $program = $unit->program_id ? Program::find($unit->program_id) : null;
                $school = ($program && $program->school_id) ? School::find($program->school_id) : null;
                $semester = $unit->semester_id ? Semester::find($unit->semester_id) : null;
                
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
                    'semester_id' => $unit->semester_id,
                    'semester_name' => $semester?->name ?? null,
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

            Log::info("Admin Unit created", [
                'unit_id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'created_by' => $user->id
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
public function assignSemesters()
{
    $units = Unit::with(['school', 'program', 'semester'])->get();
    $schools = School::orderBy('name')->get();
    $programs = Program::with('school')->orderBy('name')->get();
    $semesters = Semester::orderBy('name')->get();
    
    return Inertia::render('Admin/Units/AssignSemesters', [
        'units' => $units,
        'schools' => $schools,
        'programs' => $programs,
        'semesters' => $semesters
    ]);
}

public function assignToSemester(Request $request)
{
    $validated = $request->validate([
        'unit_ids' => 'required|array|min:1',
        'unit_ids.*' => 'exists:units,id',
        'semester_id' => 'required|exists:semesters,id'
    ]);
    
    try {
        Unit::whereIn('id', $validated['unit_ids'])
            ->update([
                'semester_id' => $validated['semester_id'],
                'updated_at' => now()
            ]);
            
        return back()->with('success', 'Units assigned to semester successfully!');
    } catch (\Exception $e) {
        return back()->withErrors(['error' => 'Failed to assign units to semester']);
    }
}

public function removeFromSemester(Request $request)
{
    $validated = $request->validate([
        'unit_ids' => 'required|array|min:1',
        'unit_ids.*' => 'exists:units,id'
    ]);
    
    try {
        Unit::whereIn('id', $validated['unit_ids'])
            ->update([
                'semester_id' => null,
                'is_active' => false,
                'updated_at' => now()
            ]);
            
        return back()->with('success', 'Units removed from semester successfully!');
    } catch (\Exception $e) {
        return back()->withErrors(['error' => 'Failed to remove units from semester']);
    }
}


}