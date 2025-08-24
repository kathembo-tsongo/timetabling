<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SemesterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of semesters.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Check permissions - allow both Admin and users with semester management permission
        if (!$user->hasRole('Admin') && !$user->can('manage-semesters')) {
            abort(403, 'Unauthorized access to semesters.');
        }

        try {
            $query = Semester::query();

            // Apply filters
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('search') && $request->filled('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', "%{$search}%");
            }

            // Sorting
            $sortField = $request->input('sort_field', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // Get semesters with enhanced data
            $semesters = $query->get()->map(function ($semester) {
                return [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'start_date' => $semester->start_date?->format('Y-m-d'),
                    'end_date' => $semester->end_date?->format('Y-m-d'),
                    'is_active' => $semester->is_active,
                    'status' => $this->getSemesterStatus($semester),
                    'created_at' => $semester->created_at,
                    'updated_at' => $semester->updated_at,
                    'stats' => $this->getSemesterStats($semester->id),
                ];
            });

            return Inertia::render('Admin/Semesters/Index', [
                'semesters' => $semesters,
                'filters' => $request->only(['search', 'is_active', 'sort_field', 'sort_direction']),
                'can' => [
                    'create' => $user->hasRole('Admin') || $user->can('manage-semesters'),
                    'update' => $user->hasRole('Admin') || $user->can('manage-semesters'),
                    'delete' => $user->hasRole('Admin') || $user->can('manage-semesters'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching semesters', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render('Admin/Semesters/Index', [
                'semesters' => [],
                'error' => 'Unable to load semesters. Please try again.',
                'filters' => $request->only(['search', 'is_active', 'sort_field', 'sort_direction']),
            ]);
        }
    }

    /**
     * Show the form for creating a new semester.
     */
    public function create()
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-semesters')) {
            abort(403, 'Unauthorized to create semesters.');
        }

        return Inertia::render('Admin/Semesters/Create');
    }

    /**
     * Store a newly created semester.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-semesters')) {
            abort(403, 'Unauthorized to create semesters.');
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:semesters,name',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'is_active' => 'boolean',
            ]);

            // If this semester is active, deactivate all others
            if ($request->boolean('is_active')) {
                Semester::where('is_active', true)->update(['is_active' => false]);
                Log::info('Deactivated all existing semesters for new active semester');
            }

            $semester = Semester::create($validated);

            Log::info('Semester created', [
                'semester_id' => $semester->id,
                'name' => $semester->name,
                'created_by' => $user->id
            ]);

            return redirect()->route('admin.semesters.index')
                ->with('success', 'Semester created successfully.');

        } catch (\Exception $e) {
            Log::error('Error creating semester', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return back()
                ->withErrors(['error' => 'Failed to create semester. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Display the specified semester with detailed information.
     */
    public function show(Semester $semester)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('view-semesters') && !$user->can('manage-semesters')) {
            abort(403, 'Unauthorized to view semester details.');
        }

        try {
            $stats = $this->getSemesterStats($semester->id);
            
            // Get detailed breakdown of units by school and program
            $unitsByProgram = Unit::where('semester_id', $semester->id)
                ->select('school_code', 'program_code', DB::raw('count(*) as count'))
                ->groupBy('school_code', 'program_code')
                ->orderBy('school_code')
                ->orderBy('program_code')
                ->get()
                ->groupBy('school_code');

            // Get recent units added to this semester
            $recentUnits = Unit::where('semester_id', $semester->id)
                ->select('id', 'code', 'name', 'school_code', 'program_code', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return Inertia::render('Admin/Semesters/Show', [
                'semester' => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'start_date' => $semester->start_date?->format('Y-m-d'),
                    'end_date' => $semester->end_date?->format('Y-m-d'),
                    'is_active' => $semester->is_active,
                    'status' => $this->getSemesterStatus($semester),
                    'duration_days' => $semester->start_date && $semester->end_date 
                        ? $semester->start_date->diffInDays($semester->end_date) 
                        : null,
                    'created_at' => $semester->created_at,
                    'updated_at' => $semester->updated_at,
                ],
                'stats' => $stats,
                'unitsByProgram' => $unitsByProgram,
                'recentUnits' => $recentUnits,
                'can' => [
                    'update' => $user->hasRole('Admin') || $user->can('manage-semesters'),
                    'delete' => $user->hasRole('Admin') || $user->can('manage-semesters'),
                    'activate' => $user->hasRole('Admin') || $user->can('manage-semesters'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing semester', [
                'semester_id' => $semester->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->route('admin.semesters.index')
                ->withErrors(['error' => 'Unable to load semester details.']);
        }
    }

    /**
     * Show the form for editing the specified semester.
     */
    public function edit(Semester $semester)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-semesters')) {
            abort(403, 'Unauthorized to edit semesters.');
        }

        return Inertia::render('Admin/Semesters/Edit', [
            'semester' => [
                'id' => $semester->id,
                'name' => $semester->name,
                'start_date' => $semester->start_date?->format('Y-m-d'),
                'end_date' => $semester->end_date?->format('Y-m-d'),
                'is_active' => $semester->is_active,
            ],
        ]);
    }

    /**
     * Update the specified semester.
     */
    public function update(Request $request, Semester $semester)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-semesters')) {
            abort(403, 'Unauthorized to update semesters.');
        }

        try {
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('semesters', 'name')->ignore($semester->id),
                ],
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'is_active' => 'boolean',
            ]);

            // If this semester is being activated, deactivate all others
            if ($request->boolean('is_active') && !$semester->is_active) {
                Semester::where('is_active', true)->update(['is_active' => false]);
                Log::info('Deactivated all semesters for activation', [
                    'activating_semester_id' => $semester->id
                ]);
            }

            $semester->update($validated);

            Log::info('Semester updated', [
                'semester_id' => $semester->id,
                'name' => $semester->name,
                'updated_by' => $user->id,
                'changes' => $semester->getChanges()
            ]);

            return redirect()->route('admin.semesters.index')
                ->with('success', 'Semester updated successfully.');

        } catch (\Exception $e) {
            Log::error('Error updating semester', [
                'semester_id' => $semester->id,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return back()
                ->withErrors(['error' => 'Failed to update semester. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Remove the specified semester from storage.
     */
    public function destroy(Semester $semester)
    {
        Log::info('Delete attempt started', [
        'semester_id' => $semester->id,
        'semester_name' => $semester->name,
        'user_id' => auth()->id()
    ]);
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-semesters')) {
            abort(403, 'Unauthorized to delete semesters.');
        }

        try {
            // Check if semester has any associated data using model relationships
            $hasUnits = $semester->units()->exists();
            $hasEnrollments = $semester->enrollments()->exists();
            $hasClassTimetables = $semester->classTimetables()->exists();
            $hasExamTimetables = $semester->examTimetables()->exists();

            if ($hasUnits || $hasEnrollments || $hasClassTimetables || $hasExamTimetables) {
                $associations = [];
                if ($hasUnits) $associations[] = 'units';
                if ($hasEnrollments) $associations[] = 'enrollments';
                if ($hasClassTimetables) $associations[] = 'class timetables';
                if ($hasExamTimetables) $associations[] = 'exam timetables';

                return redirect()->route('admin.semesters.index')
                    ->withErrors(['error' => 'Cannot delete semester because it has associated ' . implode(', ', $associations) . '.']);
            }

            $semesterName = $semester->name;
            $semester->delete();

            Log::info('Semester deleted', [
                'semester_name' => $semesterName,
                'deleted_by' => $user->id
            ]);

            return redirect()->route('admin.semesters.index')
                ->with('success', "Semester '{$semesterName}' deleted successfully.");

        } catch (\Exception $e) {
            Log::error('Error deleting semester', [
                'semester_id' => $semester->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->route('admin.semesters.index')
                ->withErrors(['error' => 'Failed to delete semester. Please try again.']);
        }
    }

    /**
     * Set the specified semester as active.
     */
    public function setActive(Semester $semester)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-semesters')) {
            abort(403, 'Unauthorized to activate semesters.');
        }

        try {
            // Deactivate all semesters
            Semester::where('is_active', true)->update(['is_active' => false]);

            // Activate the specified semester
            $semester->update(['is_active' => true]);

            Log::info('Semester activated', [
                'semester_id' => $semester->id,
                'name' => $semester->name,
                'activated_by' => $user->id
            ]);

            return redirect()->route('admin.semesters.index')
                ->with('success', "Semester '{$semester->name}' is now active.");

        } catch (\Exception $e) {
            Log::error('Error activating semester', [
                'semester_id' => $semester->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->route('admin.semesters.index')
                ->withErrors(['error' => 'Failed to activate semester. Please try again.']);
        }
    }

    /**
     * API endpoint to get active semesters.
     */
    public function getActiveSemesters()
    {
        try {
            $semesters = Semester::where('is_active', true)
                ->select('id', 'name', 'start_date', 'end_date')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'semesters' => $semesters
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching active semesters', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active semesters'
            ], 500);
        }
    }

    /**
     * API endpoint to get all semesters for dropdowns.
     */
    public function getAllSemesters()
    {
        try {
            $semesters = Semester::select('id', 'name', 'is_active', 'start_date', 'end_date')
                ->orderBy('is_active', 'desc')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'semesters' => $semesters
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching all semesters', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch semesters'
            ], 500);
        }
    }

    // Private helper methods

    /**
     * Get semester statistics using unified tables and model relationships.
     */
    private function getSemesterStats($semesterId)
    {
        try {
            $semester = Semester::find($semesterId);
            
            if (!$semester) {
                return $this->getEmptyStats();
            }

            return [
                'units_count' => $semester->units()->count(),
                'enrollments_count' => $semester->enrollments()->count(),
                'class_timetables_count' => $semester->classTimetables()->count(),
                'exam_timetables_count' => $semester->examTimetables()->count(),
                'units_by_school' => $semester->units()
                    ->selectRaw('school_code, count(*) as count')
                    ->groupBy('school_code')
                    ->pluck('count', 'school_code')
                    ->toArray(),
                'units_by_program' => $semester->units()
                    ->selectRaw('program_code, count(*) as count')
                    ->groupBy('program_code')
                    ->pluck('count', 'program_code')
                    ->toArray(),
            ];

        } catch (\Exception $e) {
            Log::warning('Error getting semester stats', [
                'semester_id' => $semesterId,
                'error' => $e->getMessage()
            ]);
            
            return $this->getEmptyStats();
        }
    }

    /**
     * Get semester status (current, upcoming, past, inactive).
     */
    private function getSemesterStatus($semester)
    {
        if (!$semester->is_active) {
            return 'inactive';
        }

        if (!$semester->start_date || !$semester->end_date) {
            return 'no_dates';
        }

        $now = now();
        
        if ($now < $semester->start_date) {
            return 'upcoming';
        } elseif ($now > $semester->end_date) {
            return 'past';
        } else {
            return 'current';
        }
    }

    /**
     * Get empty stats array for error cases.
     */
    private function getEmptyStats()
    {
        return [
            'units_count' => 0,
            'enrollments_count' => 0,
            'class_timetables_count' => 0,
            'exam_timetables_count' => 0,
            'units_by_school' => [],
            'units_by_program' => [],
        ];
    }
}