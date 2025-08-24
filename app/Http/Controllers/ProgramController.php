<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProgramController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of programs for a specific school.
     */
    public function index(Request $request, $schoolCode)
    {
        $user = auth()->user();
        $schoolCode = strtoupper($schoolCode);
        
        // Check permissions based on school
        if (!$this->hasSchoolPermission($user, $schoolCode, 'view')) {
            abort(403, "Unauthorized access to {$schoolCode} programs.");
        }

        try {
            // Get school
            $school = School::where('code', $schoolCode)->first();
            
            if (!$school) {
                return Inertia::render("Schools/{$schoolCode}/Programs/Index", [
                    'programs' => [],
                    'error' => "{$schoolCode} school not found. Please contact administrator.",
                    'filters' => $request->only(['search', 'is_active', 'sort_field', 'sort_direction']),
                    'school' => ['code' => $schoolCode, 'name' => $schoolCode, 'id' => null],
                ]);
            }

            $query = Program::where('school_id', $school->id);

            // Apply filters
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('search') && $request->filled('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('degree_type', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortField = $request->input('sort_field', 'sort_order');
            $sortDirection = $request->input('sort_direction', 'asc');
            
            if ($sortField === 'sort_order') {
                $query->orderBy('sort_order')->orderBy('name');
            } else {
                $query->orderBy($sortField, $sortDirection);
            }

            // Get programs with stats
            $programs = $query->get()->map(function ($program) {
                return [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'full_name' => $program->getFullNameAttribute(),
                    'degree_type' => $program->degree_type,
                    'duration_years' => $program->duration_years,
                    'is_active' => $program->is_active,
                    'description' => $program->description,
                    'contact_email' => $program->contact_email,
                    'contact_phone' => $program->contact_phone,
                    'sort_order' => $program->sort_order,
                    'school_name' => $program->school->name,
                    'units_count' => $this->safeCount($program, 'units'),
                    'enrollments_count' => $this->safeCount($program, 'enrollments'),
                    'created_at' => $program->created_at,
                    'updated_at' => $program->updated_at,
                ];
            });

            return Inertia::render("Schools/{$schoolCode}/Programs/Index", [
                'programs' => $programs,
                'filters' => $request->only(['search', 'is_active', 'sort_field', 'sort_direction']),
                'can' => [
                    'create' => $this->hasSchoolPermission($user, $schoolCode, 'create'),
                    'update' => $this->hasSchoolPermission($user, $schoolCode, 'edit'),
                    'delete' => $this->hasSchoolPermission($user, $schoolCode, 'delete'),
                ],
                'school' => [
                    'id' => $school->id,
                    'name' => $school->name,
                    'code' => $school->code,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching {$schoolCode} programs", [
                'user_id' => $user->id,
                'school_code' => $schoolCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render("Schools/{$schoolCode}/Programs/Index", [
                'programs' => [],
                'error' => 'Unable to load programs. Please try again.',
                'filters' => $request->only(['search', 'is_active', 'sort_field', 'sort_direction']),
                'school' => ['code' => $schoolCode, 'name' => $schoolCode, 'id' => null],
            ]);
        }
    }

    /**
     * Show the form for creating a new program.
     */
    public function create($schoolCode)
    {
        $user = auth()->user();
        $schoolCode = strtoupper($schoolCode);
        
        if (!$this->hasSchoolPermission($user, $schoolCode, 'create')) {
            abort(403, "Unauthorized to create {$schoolCode} programs.");
        }

        $school = School::where('code', $schoolCode)->first();
        
        if (!$school) {
            return redirect()->route('schools.programs.index', $schoolCode)
                ->withErrors(['error' => "{$schoolCode} school not found."]);
        }

        return Inertia::render("Schools/{$schoolCode}/Programs/Create", [
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->code,
            ],
        ]);
    }

    /**
     * Store a newly created program.
     */
    public function store(Request $request, $schoolCode)
    {
        $user = auth()->user();
        $schoolCode = strtoupper($schoolCode);
        
        if (!$this->hasSchoolPermission($user, $schoolCode, 'create')) {
            abort(403, "Unauthorized to create {$schoolCode} programs.");
        }

        $school = School::where('code', $schoolCode)->first();
        
        if (!$school) {
            return back()->withErrors(['error' => "{$schoolCode} school not found."]);
        }

        try {
            // Get degree types based on school
            $degreeTypes = $this->getDegreeTypesForSchool($schoolCode);
            
            $validated = $request->validate([
                'code' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('programs', 'code')->where('school_id', $school->id),
                ],
                'name' => 'required|string|max:255',
                'degree_type' => 'required|string|in:' . implode(',', array_keys($degreeTypes)),
                'duration_years' => 'required|numeric|min:0.5|max:10',
                'description' => 'nullable|string',
                'contact_email' => 'nullable|email|max:255',
                'contact_phone' => 'nullable|string|max:20',
                'is_active' => 'boolean',
                'sort_order' => 'nullable|integer|min:0',
            ]);

            // Set school_id
            $validated['school_id'] = $school->id;

            // Set default sort order if not provided
            if (!isset($validated['sort_order'])) {
                $validated['sort_order'] = Program::where('school_id', $school->id)->max('sort_order') + 1;
            }

            $program = Program::create($validated);

            Log::info("{$schoolCode} Program created", [
                'program_id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'school_code' => $schoolCode,
                'created_by' => $user->id
            ]);

            return redirect()->route('schools.programs.index', $schoolCode)
                ->with('success', 'Program created successfully.');

        } catch (\Exception $e) {
            Log::error("Error creating {$schoolCode} program", [
                'data' => $request->all(),
                'school_code' => $schoolCode,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return back()
                ->withErrors(['error' => 'Failed to create program. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Display the specified program.
     */
    public function show($schoolCode, Program $program)
    {
        $user = auth()->user();
        $schoolCode = strtoupper($schoolCode);
        
        if (!$this->hasSchoolPermission($user, $schoolCode, 'view')) {
            abort(403, "Unauthorized to view {$schoolCode} program details.");
        }

        // Ensure program belongs to the specified school
        if ($program->school->code !== $schoolCode) {
            abort(404, "Program not found in {$schoolCode}.");
        }

        try {
            // Get units with stats (safely)
            $units = $this->safeRelationQuery($program, 'units', function($query) {
                return $query->select('id', 'code', 'name', 'credit_hours', 'is_active', 'created_at')
                    ->orderBy('code')
                    ->get();
            }, []);
            
            // Get recent enrollments (safely)
            $recentEnrollments = $this->safeRelationQuery($program, 'enrollments', function($query) {
                return $query->with(['student:id,first_name,last_name,student_id', 'semester:id,name'])
                    ->select('id', 'student_id', 'program_id', 'semester_id', 'created_at')
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
            }, []);

            return Inertia::render("Schools/{$schoolCode}/Programs/Show", [
                'program' => [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'full_name' => $program->getFullNameAttribute(),
                    'degree_type' => $program->degree_type,
                    'duration_years' => $program->duration_years,
                    'is_active' => $program->is_active,
                    'description' => $program->description,
                    'contact_email' => $program->contact_email,
                    'contact_phone' => $program->contact_phone,
                    'sort_order' => $program->sort_order,
                    'school_name' => $program->school->name,
                    'created_at' => $program->created_at,
                    'updated_at' => $program->updated_at,
                ],
                'units' => $units,
                'recentEnrollments' => $recentEnrollments,
                'stats' => [
                    'units_count' => $this->safeCount($program, 'units'),
                    'enrollments_count' => $this->safeCount($program, 'enrollments'),
                ],
                'can' => [
                    'update' => $this->hasSchoolPermission($user, $schoolCode, 'edit'),
                    'delete' => $this->hasSchoolPermission($user, $schoolCode, 'delete'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error("Error showing {$schoolCode} program", [
                'program_id' => $program->id,
                'school_code' => $schoolCode,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->route('schools.programs.index', $schoolCode)
                ->withErrors(['error' => 'Unable to load program details.']);
        }
    }

    /**
     * Show the form for editing the specified program.
     */
    public function edit($schoolCode, Program $program)
    {
        $user = auth()->user();
        $schoolCode = strtoupper($schoolCode);
        
        if (!$this->hasSchoolPermission($user, $schoolCode, 'edit')) {
            abort(403, "Unauthorized to edit {$schoolCode} programs.");
        }

        // Ensure program belongs to the specified school
        if ($program->school->code !== $schoolCode) {
            abort(404, "Program not found in {$schoolCode}.");
        }

        return Inertia::render("Schools/{$schoolCode}/Programs/Edit", [
            'program' => [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'degree_type' => $program->degree_type,
                'duration_years' => $program->duration_years,
                'description' => $program->description,
                'contact_email' => $program->contact_email,
                'contact_phone' => $program->contact_phone,
                'is_active' => $program->is_active,
                'sort_order' => $program->sort_order,
            ],
            'school' => [
                'id' => $program->school->id,
                'name' => $program->school->name,
                'code' => $program->school->code,
            ],
        ]);
    }

    /**
     * Update the specified program.
     */
    public function update(Request $request, $schoolCode, Program $program)
    {
        $user = auth()->user();
        $schoolCode = strtoupper($schoolCode);
        
        if (!$this->hasSchoolPermission($user, $schoolCode, 'edit')) {
            abort(403, "Unauthorized to update {$schoolCode} programs.");
        }

        // Ensure program belongs to the specified school
        if ($program->school->code !== $schoolCode) {
            abort(404, "Program not found in {$schoolCode}.");
        }

        try {
            // Get degree types based on school
            $degreeTypes = $this->getDegreeTypesForSchool($schoolCode);
            
            $validated = $request->validate([
                'code' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('programs', 'code')
                        ->where('school_id', $program->school_id)
                        ->ignore($program->id),
                ],
                'name' => 'required|string|max:255',
                'degree_type' => 'required|string|in:' . implode(',', array_keys($degreeTypes)),
                'duration_years' => 'required|numeric|min:0.5|max:10',
                'description' => 'nullable|string',
                'contact_email' => 'nullable|email|max:255',
                'contact_phone' => 'nullable|string|max:20',
                'is_active' => 'boolean',
                'sort_order' => 'nullable|integer|min:0',
            ]);

            $program->update($validated);

            Log::info("{$schoolCode} Program updated", [
                'program_id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'school_code' => $schoolCode,
                'updated_by' => $user->id,
                'changes' => $program->getChanges()
            ]);

            return redirect()->route('schools.programs.index', $schoolCode)
                ->with('success', 'Program updated successfully.');

        } catch (\Exception $e) {
            Log::error("Error updating {$schoolCode} program", [
                'program_id' => $program->id,
                'school_code' => $schoolCode,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return back()
                ->withErrors(['error' => 'Failed to update program. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Remove the specified program from storage.
     */
    public function destroy($schoolCode, Program $program)
    {
        $user = auth()->user();
        $schoolCode = strtoupper($schoolCode);
        
        if (!$this->hasSchoolPermission($user, $schoolCode, 'delete')) {
            abort(403, "Unauthorized to delete {$schoolCode} programs.");
        }

        // Ensure program belongs to the specified school
        if ($program->school->code !== $schoolCode) {
            abort(404, "Program not found in {$schoolCode}.");
        }

        try {
            // Check if program has associated data
            $hasUnits = $this->safeExists($program, 'units');
            $hasEnrollments = $this->safeExists($program, 'enrollments');

            if ($hasUnits || $hasEnrollments) {
                $associations = [];
                if ($hasUnits) $associations[] = 'units';
                if ($hasEnrollments) $associations[] = 'enrollments';

                return redirect()->route('schools.programs.index', $schoolCode)
                    ->withErrors(['error' => 'Cannot delete program because it has associated ' . implode(', ', $associations) . '.']);
            }

            $programName = $program->name;
            $program->delete();

            Log::info("{$schoolCode} Program deleted", [
                'program_name' => $programName,
                'school_code' => $schoolCode,
                'deleted_by' => $user->id
            ]);

            return redirect()->route('schools.programs.index', $schoolCode)
                ->with('success', "Program '{$programName}' deleted successfully.");

        } catch (\Exception $e) {
            Log::error("Error deleting {$schoolCode} program", [
                'program_id' => $program->id,
                'school_code' => $schoolCode,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->route('schools.programs.index', $schoolCode)
                ->withErrors(['error' => 'Failed to delete program. Please try again.']);
        }
    }

    /**
     * API endpoint to get all programs for a school.
     */
    public function getAllPrograms($schoolCode)
    {
        $schoolCode = strtoupper($schoolCode);
        
        try {
            $school = School::where('code', $schoolCode)->first();
            
            if (!$school) {
                return response()->json([
                    'success' => false,
                    'message' => "{$schoolCode} school not found"
                ], 404);
            }

            $programs = Program::where('school_id', $school->id)
                ->where('is_active', true)
                ->select('id', 'code', 'name', 'degree_type')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'programs' => $programs
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching all {$schoolCode} programs", [
                'school_code' => $schoolCode,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch programs'
            ], 500);
        }
    }

    // Private helper methods

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
                return $user->can("view-faculty-programs-{$schoolCode}");
            case 'create':
                return $user->can("create-faculty-programs-{$schoolCode}");
            case 'edit':
                return $user->can("edit-faculty-programs-{$schoolCode}");
            case 'delete':
                return $user->can("delete-faculty-programs-{$schoolCode}");
            default:
                return false;
        }
    }

    /**
     * Get available degree types for a school.
     */
    private function getDegreeTypesForSchool($schoolCode)
    {
        $base = [
            'Certificate' => 'Certificate',
            'Diploma' => 'Diploma',
            'Bachelor' => "Bachelor's Degree",
            'Master' => "Master's Degree",
            'PhD' => 'Doctoral Degree (PhD)',
        ];

        // Add school-specific degree types
        if ($schoolCode === 'SBS') {
            $base['MBA'] = 'Master of Business Administration';
        }

        return $base;
    }

    /**
     * Safely count related records.
     */
    private function safeCount($model, $relation)
    {
        try {
            return $model->{$relation}()->count();
        } catch (\Exception $e) {
            Log::warning("Error counting {$relation} for model", [
                'model' => get_class($model),
                'model_id' => $model->id ?? null,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Safely check if relation exists.
     */
    private function safeExists($model, $relation)
    {
        try {
            return $model->{$relation}()->exists();
        } catch (\Exception $e) {
            Log::warning("Error checking existence of {$relation} for model", [
                'model' => get_class($model),
                'model_id' => $model->id ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Safely execute relation query with fallback.
     */
    private function safeRelationQuery($model, $relation, $callback, $fallback = null)
    {
        try {
            return $callback($model->{$relation}());
        } catch (\Exception $e) {
            Log::warning("Error querying {$relation} for model", [
                'model' => get_class($model),
                'model_id' => $model->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $fallback;
        }
    }
}