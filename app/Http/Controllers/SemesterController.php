<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use App\Models\Unit;
use App\Models\Program;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
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
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('school_code', 'like', "%{$search}%")
                      ->orWhere('intake_type', 'like', "%{$search}%")
                      ->orWhere('academic_year', 'like', "%{$search}%");
                });
            }

            // Filter by intake type
            if ($request->has('intake_type') && $request->filled('intake_type')) {
                $query->where('intake_type', $request->input('intake_type'));
            }

            // Filter by academic year
            if ($request->has('academic_year') && $request->filled('academic_year')) {
                $query->where('academic_year', $request->input('academic_year'));
            }

            // Filter by school code
            if ($request->has('school_code') && $request->filled('school_code')) {
                $query->where('school_code', $request->input('school_code'));
            }

            // Sorting
            $sortField = $request->input('sort_field', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // Get paginated semesters with enhanced data
            $perPage = $request->input('per_page', 15);
            $semesters = $query->paginate($perPage)->withQueryString();

            // Transform the collection
            $semesters->getCollection()->transform(function ($semester) {
                return [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'start_date' => $semester->start_date?->format('Y-m-d'),
                    'end_date' => $semester->end_date?->format('Y-m-d'),
                    'is_active' => $semester->is_active,
                    'school_code' => $semester->school_code,
                    'intake_type' => $semester->intake_type,
                    'academic_year' => $semester->academic_year,
                    'status' => $this->getSemesterStatus($semester),
                    'duration_days' => $semester->start_date && $semester->end_date 
                        ? $semester->start_date->diffInDays($semester->end_date) 
                        : null,
                    'formatted_period' => $semester->start_date && $semester->end_date
                        ? $semester->start_date->format('M j, Y') . ' - ' . $semester->end_date->format('M j, Y')
                        : 'Dates not set',
                    'created_at' => $semester->created_at,
                    'updated_at' => $semester->updated_at,
                    'stats' => $this->getSemesterStats($semester->id),
                ];
            });

            // Get filter options dynamically from database
            $filterOptions = $this->getFilterOptions();

            return Inertia::render('Admin/Semesters/Index', [
                'semesters' => $semesters,
                'filterOptions' => $filterOptions,
                'filters' => $request->only([
                    'search', 'is_active', 'intake_type', 'academic_year', 
                    'school_code', 'sort_field', 'sort_direction'
                ]),
                'can' => [
                    'create' => $user->hasRole('Admin') || $user->can('manage-semesters'),
                    'update' => $user->hasRole('Admin') || $user->can('manage-semesters'),
                    'delete' => $user->hasRole('Admin') || $user->can('manage-semesters'),
                ],
                'flash' => [
                    'success' => session('success'),
                    'error' => session('error'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching semesters', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render('Admin/Semesters/Index', [
                'semesters' => collect(['data' => [], 'links' => [], 'meta' => ['total' => 0]]),
                'filterOptions' => $this->getFilterOptions(),
                'error' => 'Unable to load semesters. Please try again.',
                'filters' => $request->only([
                    'search', 'is_active', 'intake_type', 'academic_year',
                    'school_code', 'sort_field', 'sort_direction'
                ]),
            ]);
        }
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

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:semesters,name',
            'school_code' => 'nullable|string|max:50',
            'intake_type' => 'nullable|string|max:50',
            'academic_year' => 'nullable|string|max:10',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }

        try {
            // Get validated data from the validator, not the request
            $data = $validator->validated();
            
            // Default to active if not specified
            if (!isset($data['is_active'])) {
                $data['is_active'] = true;
            }

            $semester = Semester::create($data);

            Log::info('Semester created', [
                'semester_id' => $semester->id,
                'name' => $semester->name,
                'created_by' => $user->id
            ]);

            return redirect()->route('admin.semesters.index')
                ->with('success', 'Semester created successfully!');

        } catch (\Exception $e) {
            Log::error('Error creating semester', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Failed to create semester. Please try again.'])
                ->withInput();
        }
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

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('semesters', 'name')->ignore($semester->id),
            ],
            'school_code' => 'nullable|string|max:50',
            'intake_type' => 'nullable|string|max:50',
            'academic_year' => 'nullable|string|max:10',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }

        try {
            // Get validated data from the validator, not the request
            $data = $validator->validated();
            
            $semester->update($data);

            Log::info('Semester updated', [
                'semester_id' => $semester->id,
                'name' => $semester->name,
                'updated_by' => $user->id,
                'changes' => $semester->getChanges()
            ]);

            return redirect()->route('admin.semesters.index')
                ->with('success', 'Semester updated successfully!');

        } catch (\Exception $e) {
            Log::error('Error updating semester', [
                'semester_id' => $semester->id,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Failed to update semester. Please try again.'])
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
            
            return Inertia::render('Admin/Semesters/Show', [
                'semester' => [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'school_code' => $semester->school_code,
                    'intake_type' => $semester->intake_type,
                    'academic_year' => $semester->academic_year,
                    'start_date' => $semester->start_date?->format('Y-m-d'),
                    'end_date' => $semester->end_date?->format('Y-m-d'),
                    'is_active' => $semester->is_active,
                    'status' => $this->getSemesterStatus($semester),
                    'duration_days' => $semester->start_date && $semester->end_date 
                        ? $semester->start_date->diffInDays($semester->end_date) 
                        : null,
                    'formatted_period' => $semester->start_date && $semester->end_date
                        ? $semester->start_date->format('M j, Y') . ' - ' . $semester->end_date->format('M j, Y')
                        : 'Dates not set',
                    'created_at' => $semester->created_at,
                    'updated_at' => $semester->updated_at,
                ],
                'stats' => $stats,
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
     * Remove the specified semester from storage.
     */
    public function destroy(Semester $semester)
    {
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
                    ->with('error', 'Cannot delete semester because it has associated ' . implode(', ', $associations) . '.');
            }

            $semesterName = $semester->name;
            $semester->delete();

            Log::info('Semester deleted', [
                'semester_name' => $semesterName,
                'deleted_by' => $user->id
            ]);

            return redirect()->route('admin.semesters.index')
                ->with('success', "Semester '{$semesterName}' deleted successfully!");

        } catch (\Exception $e) {
            Log::error('Error deleting semester', [
                'semester_id' => $semester->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->route('admin.semesters.index')
                ->with('error', 'Failed to delete semester. Please try again.');
        }
    }

    /**
     * Set the specified semester as active (without deactivating others).
     */
    public function setActive(Semester $semester)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-semesters')) {
            abort(403, 'Unauthorized to activate semesters.');
        }

        try {
            // Just activate this semester without deactivating others
            $semester->update(['is_active' => true]);

            Log::info('Semester activated', [
                'semester_id' => $semester->id,
                'name' => $semester->name,
                'activated_by' => $user->id
            ]);

            return redirect()->route('admin.semesters.index')
                ->with('success', "Semester '{$semester->name}' is now active!");

        } catch (\Exception $e) {
            Log::error('Error activating semester', [
                'semester_id' => $semester->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->route('admin.semesters.index')
                ->with('error', 'Failed to activate semester. Please try again.');
        }
    }

    /**
     * Bulk activate semesters
     */
    public function bulkActivate(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-semesters')) {
            abort(403, 'Unauthorized to activate semesters.');
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:semesters,id'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Invalid semester selection for bulk activation.');
        }

        try {
            $updated = Semester::whereIn('id', $request->ids)
                ->update(['is_active' => true]);

            Log::info('Bulk semester activation', [
                'semester_ids' => $request->ids,
                'count' => $updated,
                'activated_by' => $user->id
            ]);

            return redirect()->back()
                ->with('success', "Successfully activated {$updated} semester(s).");

        } catch (\Exception $e) {
            Log::error('Error bulk activating semesters', [
                'semester_ids' => $request->ids,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->back()
                ->with('error', 'Error activating semesters. Please try again.');
        }
    }

    /**
     * Bulk deactivate semesters
     */
    public function bulkDeactivate(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-semesters')) {
            abort(403, 'Unauthorized to deactivate semesters.');
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:semesters,id'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Invalid semester selection for bulk deactivation.');
        }

        try {
            $updated = Semester::whereIn('id', $request->ids)
                ->update(['is_active' => false]);

            Log::info('Bulk semester deactivation', [
                'semester_ids' => $request->ids,
                'count' => $updated,
                'deactivated_by' => $user->id
            ]);

            return redirect()->back()
                ->with('success', "Successfully deactivated {$updated} semester(s).");

        } catch (\Exception $e) {
            Log::error('Error bulk deactivating semesters', [
                'semester_ids' => $request->ids,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->back()
                ->with('error', 'Error deactivating semesters. Please try again.');
        }
    }

    /**
     * Bulk delete semesters
     */
    public function bulkDelete(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-semesters')) {
            abort(403, 'Unauthorized to delete semesters.');
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:semesters,id'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Invalid semester selection for bulk delete.');
        }

        try {
            // Check for semesters with associated data
            $semestersWithData = Semester::whereIn('id', $request->ids)
                ->where(function($query) {
                    $query->whereHas('units')
                          ->orWhereHas('enrollments')
                          ->orWhereHas('classTimetables')
                          ->orWhereHas('examTimetables');
                })
                ->pluck('name');

            if ($semestersWithData->isNotEmpty()) {
                return redirect()->back()->with('error', 
                    'Cannot delete semesters with associated data: ' . $semestersWithData->implode(', '));
            }

            $deleted = Semester::whereIn('id', $request->ids)->delete();
            
            Log::info('Bulk semester deletion', [
                'semester_ids' => $request->ids,
                'count' => $deleted,
                'deleted_by' => $user->id
            ]);

            return redirect()->back()
                ->with('success', "Successfully deleted {$deleted} semester(s).");

        } catch (\Exception $e) {
            Log::error('Error bulk deleting semesters', [
                'semester_ids' => $request->ids,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->back()
                ->with('error', 'Error deleting semesters. Please try again.');
        }
    }

    /**
     * API endpoint to get active semesters.
     */
    public function getActiveSemesters()
    {
        try {
            $semesters = Semester::where('is_active', true)
                ->select('id', 'name', 'school_code', 'intake_type', 'academic_year', 'start_date', 'end_date')
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
            $semesters = Semester::select('id', 'name', 'is_active', 'school_code', 'intake_type', 'academic_year', 'start_date', 'end_date')
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
     * Get filter options dynamically from database
     */
    private function getFilterOptions()
    {
        try {
            // Get school codes from the schools table
            $schoolCodes = School::where('is_active', true)
                ->orderBy('code')
                ->pluck('code')
                ->filter()
                ->values();

            // Get intake types from semesters table (existing data)
            $intakeTypes = Semester::distinct()
                ->whereNotNull('intake_type')
                ->pluck('intake_type')
                ->filter()
                ->sort()
                ->values();

            // Get academic years from semesters table (existing data)
            $academicYears = Semester::distinct()
                ->whereNotNull('academic_year')
                ->pluck('academic_year')
                ->filter()
                ->sort()
                ->values();

            return [
                'intake_types' => $intakeTypes,
                'academic_years' => $academicYears,
                'school_codes' => $schoolCodes,
            ];

        } catch (\Exception $e) {
            Log::error('Error getting filter options', [
                'error' => $e->getMessage()
            ]);

            // Return empty collections on error
            return [
                'intake_types' => collect([]),
                'academic_years' => collect([]),
                'school_codes' => collect([]),
            ];
        }
    }

    /**
     * Get semester statistics
     */
    private function getSemesterStats($semesterId)
    {
        try {
            $semester = Semester::find($semesterId);
            
            if (!$semester) {
                return $this->getEmptyStats();
            }

            // Get statistics using multiple approaches to handle different data structures
            $unitsCount = 0;
            $enrollmentsCount = 0;
            $classTimetablesCount = 0;
            $examTimetablesCount = 0;
            $unitsBySchool = [];
            $unitsByProgram = [];

            // Try multiple approaches to get units count
            try {
                // Approach 1: Through lecturer assignments
                $unitsCount = DB::table('lecturer_assignments')
                    ->where('semester_id', $semesterId)
                    ->distinct('unit_id')
                    ->count('unit_id');
                
                // If no units found through assignments, try alternative approaches
                if ($unitsCount == 0) {
                    // Approach 2: Through enrollments -> units
                    $unitsCount = DB::table('enrollments as e')
                        ->join('units as u', 'e.unit_id', '=', 'u.id')
                        ->where('e.semester_id', $semesterId)
                        ->distinct('u.id')
                        ->count('u.id');
                }
                
                if ($unitsCount == 0) {
                    // Approach 3: Through class_timetables -> units
                    $unitsCount = DB::table('class_timetables as ct')
                        ->join('units as u', 'ct.unit_id', '=', 'u.id')
                        ->where('ct.semester_id', $semesterId)
                        ->distinct('u.id')
                        ->count('u.id');
                }
            } catch (\Exception $e) {
                Log::warning('Error getting units count', ['error' => $e->getMessage()]);
                $unitsCount = 0;
            }

            // Get enrollments count
            try {
                $enrollmentsCount = DB::table('enrollments')
                    ->where('semester_id', $semesterId)
                    ->count();
            } catch (\Exception $e) {
                $enrollmentsCount = 0;
            }

            // Get class timetables count
            try {
                $classTimetablesCount = DB::table('class_timetables')
                    ->where('semester_id', $semesterId)
                    ->count();
            } catch (\Exception $e) {
                $classTimetablesCount = 0;
            }

            // Get exam timetables count
            try {
                $examTimetablesCount = DB::table('exam_timetables')
                    ->where('semester_id', $semesterId)
                    ->count();
            } catch (\Exception $e) {
                $examTimetablesCount = 0;
            }

            // Get units by school - try multiple approaches
            try {
                // Approach 1: Through lecturer assignments
                $unitsBySchool = DB::table('lecturer_assignments as la')
                    ->join('units as u', 'la.unit_id', '=', 'u.id')
                    ->join('programs as p', 'la.program_id', '=', 'p.id')
                    ->join('schools as s', 'p.school_id', '=', 's.id')
                    ->where('la.semester_id', $semesterId)
                    ->select('s.code as school_code', DB::raw('count(distinct la.unit_id) as count'))
                    ->groupBy('s.code')
                    ->pluck('count', 'school_code')
                    ->toArray();

                // If empty, try through enrollments
                if (empty($unitsBySchool)) {
                    $unitsBySchool = DB::table('enrollments as e')
                        ->join('units as u', 'e.unit_id', '=', 'u.id')
                        ->join('classes as c', 'e.class_id', '=', 'c.id')
                        ->join('programs as p', 'c.program_id', '=', 'p.id')
                        ->join('schools as s', 'p.school_id', '=', 's.id')
                        ->where('e.semester_id', $semesterId)
                        ->select('s.code as school_code', DB::raw('count(distinct u.id) as count'))
                        ->groupBy('s.code')
                        ->pluck('count', 'school_code')
                        ->toArray();
                }

                // If still empty, try through class timetables
                if (empty($unitsBySchool)) {
                    $unitsBySchool = DB::table('class_timetables as ct')
                        ->join('units as u', 'ct.unit_id', '=', 'u.id')
                        ->join('classes as c', 'ct.class_id', '=', 'c.id')
                        ->join('programs as p', 'c.program_id', '=', 'p.id')
                        ->join('schools as s', 'p.school_id', '=', 's.id')
                        ->where('ct.semester_id', $semesterId)
                        ->select('s.code as school_code', DB::raw('count(distinct u.id) as count'))
                        ->groupBy('s.code')
                        ->pluck('count', 'school_code')
                        ->toArray();
                }
            } catch (\Exception $e) {
                Log::warning('Error getting units by school', ['error' => $e->getMessage()]);
                $unitsBySchool = [];
            }

            // Get units by program - try multiple approaches
            try {
                // Approach 1: Through lecturer assignments
                $unitsByProgram = DB::table('lecturer_assignments as la')
                    ->join('programs as p', 'la.program_id', '=', 'p.id')
                    ->where('la.semester_id', $semesterId)
                    ->select('p.code as program_code', DB::raw('count(distinct la.unit_id) as count'))
                    ->groupBy('p.code')
                    ->pluck('count', 'program_code')
                    ->toArray();

                // If empty, try through enrollments
                if (empty($unitsByProgram)) {
                    $unitsByProgram = DB::table('enrollments as e')
                        ->join('classes as c', 'e.class_id', '=', 'c.id')
                        ->join('programs as p', 'c.program_id', '=', 'p.id')
                        ->where('e.semester_id', $semesterId)
                        ->select('p.code as program_code', DB::raw('count(distinct e.unit_id) as count'))
                        ->groupBy('p.code')
                        ->pluck('count', 'program_code')
                        ->toArray();
                }

                // If still empty, try through class timetables
                if (empty($unitsByProgram)) {
                    $unitsByProgram = DB::table('class_timetables as ct')
                        ->join('classes as c', 'ct.class_id', '=', 'c.id')
                        ->join('programs as p', 'c.program_id', '=', 'p.id')
                        ->where('ct.semester_id', $semesterId)
                        ->select('p.code as program_code', DB::raw('count(distinct ct.unit_id) as count'))
                        ->groupBy('p.code')
                        ->pluck('count', 'program_code')
                        ->toArray();
                }
            } catch (\Exception $e) {
                Log::warning('Error getting units by program', ['error' => $e->getMessage()]);
                $unitsByProgram = [];
            }

            return [
                'units_count' => $unitsCount,
                'enrollments_count' => $enrollmentsCount,
                'class_timetables_count' => $classTimetablesCount,
                'exam_timetables_count' => $examTimetablesCount,
                'units_by_school' => $unitsBySchool,
                'units_by_program' => $unitsByProgram,
            ];

        } catch (\Exception $e) {
            Log::error('Error getting semester stats', [
                'semester_id' => $semesterId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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