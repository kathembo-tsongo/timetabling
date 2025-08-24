<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SchoolController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of schools.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('view-schools')) {
            abort(403, 'Unauthorized access to schools.');
        }

        try {
            $query = School::query();

            // Apply filters
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('search') && $request->filled('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
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

            // Get schools with stats
            $schools = $query->get()->map(function ($school) {
                return [
                    'id' => $school->id,
                    'code' => $school->code,
                    'name' => $school->name,
                    'full_name' => $school->getFullNameAttribute(),
                    'is_active' => $school->is_active,
                    'description' => $school->description,
                    'contact_email' => $school->contact_email,
                    'contact_phone' => $school->contact_phone,
                    'sort_order' => $school->sort_order,
                    'programs_count' => $this->safeCount($school, 'programs'),
                    'units_count' => $this->safeCount($school, 'units'),
                    'created_at' => $school->created_at,
                    'updated_at' => $school->updated_at,
                ];
            });

            return Inertia::render('Admin/Schools/Index', [
                'schools' => $schools,
                'filters' => $request->only(['search', 'is_active', 'sort_field', 'sort_direction']),
                'can' => [
                    'create' => $user->hasRole('Admin') || $user->can('create-schools'),
                    'update' => $user->hasRole('Admin') || $user->can('edit-schools'),
                    'delete' => $user->hasRole('Admin') || $user->can('delete-schools'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching schools', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render('Admin/Schools/Index', [
                'schools' => [],
                'error' => 'Unable to load schools. Please try again.',
                'filters' => $request->only(['search', 'is_active', 'sort_field', 'sort_direction']),
            ]);
        }
    }

    /**
     * Show the form for creating a new school.
     */
    public function create()
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('create-schools')) {
            abort(403, 'Unauthorized to create schools.');
        }

        return Inertia::render('Admin/Schools/Create');
    }

    /**
     * Store a newly created school.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('create-schools')) {
            abort(403, 'Unauthorized to create schools.');
        }

        try {
            $validated = $request->validate([
                'code' => 'required|string|max:10|unique:schools,code',
                'name' => 'required|string|max:255|unique:schools,name',
                'description' => 'nullable|string',
                'contact_email' => 'nullable|email|max:255',
                'contact_phone' => 'nullable|string|max:20',
                'is_active' => 'boolean',
                'sort_order' => 'nullable|integer|min:0',
            ]);

            // Set default sort order if not provided
            if (!isset($validated['sort_order'])) {
                $validated['sort_order'] = School::max('sort_order') + 1;
            }

            $school = School::create($validated);

            Log::info('School created', [
                'school_id' => $school->id,
                'code' => $school->code,
                'name' => $school->name,
                'created_by' => $user->id
            ]);

            return redirect()->route('admin.schools.index')
                ->with('success', 'School created successfully.');

        } catch (\Exception $e) {
            Log::error('Error creating school', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return back()
                ->withErrors(['error' => 'Failed to create school. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Display the specified school.
     */
    public function show(School $school)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('view-schools')) {
            abort(403, 'Unauthorized to view school details.');
        }

        try {
            // Get programs with stats (safely)
            $programs = $this->safeRelationQuery($school, 'programs', function($query) {
                return $query->withCount(['units'])->get();
            }, []);
            
            // Get recent units (safely)
            $recentUnits = $this->safeRelationQuery($school, 'units', function($query) {
                return $query->select('id', 'code', 'name', 'program_code', 'created_at')
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
            }, []);

            return Inertia::render('Admin/Schools/Show', [
                'school' => [
                    'id' => $school->id,
                    'code' => $school->code,
                    'name' => $school->name,
                    'full_name' => $school->getFullNameAttribute(),
                    'is_active' => $school->is_active,
                    'description' => $school->description,
                    'contact_email' => $school->contact_email,
                    'contact_phone' => $school->contact_phone,
                    'sort_order' => $school->sort_order,
                    'created_at' => $school->created_at,
                    'updated_at' => $school->updated_at,
                ],
                'programs' => $programs,
                'recentUnits' => $recentUnits,
                'stats' => [
                    'programs_count' => $this->safeCount($school, 'programs'),
                    'units_count' => $this->safeCount($school, 'units'),
                ],
                'can' => [
                    'update' => $user->hasRole('Admin') || $user->can('edit-schools'),
                    'delete' => $user->hasRole('Admin') || $user->can('delete-schools'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing school', [
                'school_id' => $school->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->route('admin.schools.index')
                ->withErrors(['error' => 'Unable to load school details.']);
        }
    }

    /**
     * Show the form for editing the specified school.
     */
    public function edit(School $school)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('edit-schools')) {
            abort(403, 'Unauthorized to edit schools.');
        }

        return Inertia::render('Admin/Schools/Edit', [
            'school' => [
                'id' => $school->id,
                'code' => $school->code,
                'name' => $school->name,
                'description' => $school->description,
                'contact_email' => $school->contact_email,
                'contact_phone' => $school->contact_phone,
                'is_active' => $school->is_active,
                'sort_order' => $school->sort_order,
            ],
        ]);
    }

    /**
     * Update the specified school.
     */
    public function update(Request $request, School $school)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('edit-schools')) {
            abort(403, 'Unauthorized to update schools.');
        }

        try {
            $validated = $request->validate([
                'code' => [
                    'required',
                    'string',
                    'max:10',
                    Rule::unique('schools', 'code')->ignore($school->id),
                ],
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('schools', 'name')->ignore($school->id),
                ],
                'description' => 'nullable|string',
                'contact_email' => 'nullable|email|max:255',
                'contact_phone' => 'nullable|string|max:20',
                'is_active' => 'boolean',
                'sort_order' => 'nullable|integer|min:0',
            ]);

            $school->update($validated);

            Log::info('School updated', [
                'school_id' => $school->id,
                'code' => $school->code,
                'name' => $school->name,
                'updated_by' => $user->id,
                'changes' => $school->getChanges()
            ]);

            return redirect()->route('admin.schools.index')
                ->with('success', 'School updated successfully.');

        } catch (\Exception $e) {
            Log::error('Error updating school', [
                'school_id' => $school->id,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return back()
                ->withErrors(['error' => 'Failed to update school. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Remove the specified school from storage.
     */
    public function destroy(School $school)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('delete-schools')) {
            abort(403, 'Unauthorized to delete schools.');
        }

        try {
            // Check if school has associated data
            $hasPrograms = $this->safeExists($school, 'programs');
            $hasUnits = $this->safeExists($school, 'units');

            if ($hasPrograms || $hasUnits) {
                $associations = [];
                if ($hasPrograms) $associations[] = 'programs';
                if ($hasUnits) $associations[] = 'units';

                return redirect()->route('admin.schools.index')
                    ->withErrors(['error' => 'Cannot delete school because it has associated ' . implode(', ', $associations) . '.']);
            }

            $schoolName = $school->name;
            $school->delete();

            Log::info('School deleted', [
                'school_name' => $schoolName,
                'deleted_by' => $user->id
            ]);

            return redirect()->route('admin.schools.index')
                ->with('success', "School '{$schoolName}' deleted successfully.");

        } catch (\Exception $e) {
            Log::error('Error deleting school', [
                'school_id' => $school->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->route('admin.schools.index')
                ->withErrors(['error' => 'Failed to delete school. Please try again.']);
        }
    }

    /**
     * API endpoint to get all schools for dropdowns.
     */
    public function getAllSchools()
    {
        try {
            $schools = School::where('is_active', true)
                ->select('id', 'code', 'name')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'schools' => $schools
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching all schools', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch schools'
            ], 500);
        }
    }

    // Private helper methods

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