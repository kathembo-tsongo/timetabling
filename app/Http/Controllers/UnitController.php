<?php

namespace App\Http\Controllers;

use App\Models\Unit;
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
    public function index($school, $program, Request $request)
    {
        $user = auth()->user();
        
        // Validate school and program codes
        $this->validateSchoolProgram($school, $program);
        
        // Check permissions
        $this->authorizeAccess($user, $school, $program);

        try {
            $query = Unit::forSchoolAndProgram($school, $program)
                        ->with(['semester']);

            // Apply filters
            $this->applyFilters($query, $request);

            // Get units
            $units = $query->get();

            // Get additional data for the view
            $semesters = Semester::select('id', 'name', 'is_active')
                ->orderBy('is_active', 'desc')
                ->orderBy('name')
                ->get();

            return Inertia::render('FacultyAdmin/Units/Index', [
                'units' => $units,
                'semesters' => $semesters,
                'schoolCode' => strtoupper($school),
                'programCode' => strtoupper($program),
                'schoolName' => Unit::getSchoolOptions()[strtoupper($school)] ?? $school,
                'programName' => Unit::getProgramOptions(strtoupper($school))[strtoupper($program)] ?? $program,
                'userPermissions' => $user->getAllPermissions()->pluck('name'),
                'userRoles' => $user->getRoleNames(),
                'filters' => $request->only([
                    'search', 'semester_id', 'is_active', 
                    'sort_field', 'sort_direction'
                ]),
                'stats' => $this->getStats($school, $program),
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching units', [
                'school' => $school,
                'program' => $program,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render('FacultyAdmin/Units/Index', [
                'units' => [],
                'semesters' => [],
                'schoolCode' => strtoupper($school),
                'programCode' => strtoupper($program),
                'error' => 'Unable to load units. Please try again.',
                'filters' => $request->only([
                    'search', 'semester_id', 'is_active', 
                    'sort_field', 'sort_direction'
                ]),
            ]);
        }
    }

    /**
     * Store a new unit for a specific school and program.
     */
    public function store($school, $program, Request $request)
    {
        $this->validateSchoolProgram($school, $program);
        
        try {
            $validated = $request->validate([
                'code' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('units', 'code'),
                ],
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'credit_hours' => 'required|integer|min:1|max:6',
                'semester_id' => 'nullable|exists:semesters,id',
                'is_active' => 'boolean',
            ]);

            Unit::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'credit_hours' => $validated['credit_hours'],
                'school_code' => strtoupper($school),
                'program_code' => strtoupper($program),
                'semester_id' => $validated['semester_id'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            return redirect()
                ->route('facultyadmin.units.index', [$school, $program])
                ->with('success', 'Unit created successfully.');

        } catch (\Exception $e) {
            Log::error('Error creating unit', [
                'school' => $school,
                'program' => $program,
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return back()
                ->withErrors(['error' => 'Failed to create unit. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Update a unit.
     */
    public function update($school, $program, Unit $unit, Request $request)
    {
        $this->validateSchoolProgram($school, $program);
        
        // Ensure unit belongs to the specified school/program
        if ($unit->school_code !== strtoupper($school) || 
            $unit->program_code !== strtoupper($program)) {
            abort(404, 'Unit not found for this program.');
        }

        try {
            $validated = $request->validate([
                'code' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('units', 'code')->ignore($unit->id),
                ],
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'credit_hours' => 'required|integer|min:1|max:6',
                'semester_id' => 'nullable|exists:semesters,id',
                'is_active' => 'boolean',
            ]);

            $unit->update($validated);

            return redirect()
                ->route('facultyadmin.units.index', [$school, $program])
                ->with('success', 'Unit updated successfully.');

        } catch (\Exception $e) {
            Log::error('Error updating unit', [
                'unit_id' => $unit->id,
                'school' => $school,
                'program' => $program,
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return back()
                ->withErrors(['error' => 'Failed to update unit. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Delete a unit.
     */
    public function destroy($school, $program, Unit $unit)
    {
        $this->validateSchoolProgram($school, $program);
        
        // Ensure unit belongs to the specified school/program
        if ($unit->school_code !== strtoupper($school) || 
            $unit->program_code !== strtoupper($program)) {
            abort(404, 'Unit not found for this program.');
        }

        try {
            // Check if unit has enrollments
            if ($unit->enrollments()->exists()) {
                return back()->withErrors([
                    'error' => 'Cannot delete unit because it has associated enrollments.'
                ]);
            }

            $unit->delete();

            return redirect()
                ->route('facultyadmin.units.index', [$school, $program])
                ->with('success', 'Unit deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Error deleting unit', [
                'unit_id' => $unit->id,
                'school' => $school,
                'program' => $program,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors([
                'error' => 'Failed to delete unit. It may be associated with other records.'
            ]);
        }
    }

    /**
     * Get units for a semester (API endpoint).
     */
    public function getUnitsBySemester($school, $program, $semesterId, Request $request)
    {
        $this->validateSchoolProgram($school, $program);

        try {
            $units = Unit::forSchoolAndProgram($school, $program)
                ->where('semester_id', $semesterId)
                ->where('is_active', true)
                ->select('id', 'name', 'code', 'credit_hours')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'units' => $units,
                'message' => "Found {$units->count()} units for semester {$semesterId}"
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching units by semester', [
                'school' => $school,
                'program' => $program,
                'semester_id' => $semesterId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'units' => [],
                'error' => 'Failed to fetch units for selected semester'
            ], 500);
        }
    }

    /**
     * Bulk assign units to semester.
     */
    public function bulkAssignToSemester($school, $program, Request $request)
    {
        $this->validateSchoolProgram($school, $program);

        $validated = $request->validate([
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'integer|exists:units,id',
            'semester_id' => 'required|integer|exists:semesters,id'
        ]);

        try {
            $updated = Unit::forSchoolAndProgram($school, $program)
                ->whereIn('id', $validated['unit_ids'])
                ->update(['semester_id' => $validated['semester_id']]);

            return response()->json([
                'success' => true,
                'message' => "Successfully assigned {$updated} units to semester",
                'updated_count' => $updated
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign units to semester'
            ], 500);
        }
    }

    // Helper Methods

    private function validateSchoolProgram($school, $program)
    {
        $schoolCode = strtoupper($school);
        $programCode = strtoupper($program);
        
        $validSchools = array_keys(Unit::getSchoolOptions());
        $validPrograms = Unit::getProgramOptions($schoolCode);

        if (!in_array($schoolCode, $validSchools)) {
            abort(404, 'Invalid school code.');
        }

        if (!array_key_exists($programCode, $validPrograms)) {
            abort(404, 'Invalid program code for this school.');
        }
    }

    private function authorizeAccess($user, $school, $program)
    {
        $requiredRole = "Faculty Admin - " . strtoupper($school);
        $requiredPermission = 'manage-faculty-units-' . strtolower($school);
        
        if (!$user->hasRole($requiredRole) && !$user->can($requiredPermission)) {
            abort(403, "Unauthorized access to {$school} {$program} units.");
        }
    }

    private function applyFilters($query, $request)
    {
        // Search functionality
        if ($request->has('search') && $request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
        
        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
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

    private function getStats($school, $program)
    {
        $query = Unit::forSchoolAndProgram($school, $program);
        
        return [
            'total' => $query->count(),
            'active' => $query->where('is_active', true)->count(),
            'inactive' => $query->where('is_active', false)->count(),
            'assigned_to_semester' => $query->whereNotNull('semester_id')->count(),
            'unassigned' => $query->whereNull('semester_id')->count(),
        ];
    }
}