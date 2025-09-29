<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class AcademicYearController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of academic years.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-academic-settings')) {
            abort(403, 'Unauthorized access to academic years.');
        }

        try {
            $query = AcademicYear::query();

            // Search filter
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('year', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Active filter
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Sorting
            $sortField = $request->input('sort_field', 'sort_order');
            $sortDirection = $request->input('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);

            $perPage = $request->input('per_page', 15);
            $academicYears = $query->paginate($perPage)->withQueryString();

            // Transform data
            $academicYears->getCollection()->transform(function ($year) {
                return [
                    'id' => $year->id,
                    'year' => $year->year,
                    'start_date' => $year->start_date?->format('Y-m-d'),
                    'end_date' => $year->end_date?->format('Y-m-d'),
                    'is_active' => $year->is_active,
                    'description' => $year->description,
                    'sort_order' => $year->sort_order,
                    'status' => $year->status,
                    'semesters_count' => $year->semesters()->count(),
                    'intake_types_count' => $year->intakeTypes()->count(),
                    'created_at' => $year->created_at,
                    'updated_at' => $year->updated_at,
                ];
            });

            return Inertia::render('Admin/AcademicYears/Index', [
                'academicYears' => $academicYears,
                'filters' => $request->only(['search', 'is_active', 'sort_field', 'sort_direction']),
                'can' => [
                    'create' => $user->hasRole('Admin') || $user->can('manage-academic-settings'),
                    'update' => $user->hasRole('Admin') || $user->can('manage-academic-settings'),
                    'delete' => $user->hasRole('Admin') || $user->can('manage-academic-settings'),
                ],
                'flash' => [
                    'success' => session('success'),
                    'error' => session('error'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching academic years', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return Inertia::render('Admin/AcademicYears/Index', [
                'academicYears' => collect(['data' => [], 'links' => [], 'meta' => ['total' => 0]]),
                'error' => 'Unable to load academic years.',
                'filters' => $request->only(['search', 'is_active', 'sort_field', 'sort_direction']),
            ]);
        }
    }

    /**
     * Store a newly created academic year.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-academic-settings')) {
            abort(403, 'Unauthorized to create academic years.');
        }

        $validator = Validator::make($request->all(), [
            'year' => 'required|string|max:10|unique:academic_years,year',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }

        try {
            $data = $validator->validated();
            $data['is_active'] = $data['is_active'] ?? true;
            $data['sort_order'] = $data['sort_order'] ?? 0;

            $academicYear = AcademicYear::create($data);

            Log::info('Academic year created', [
                'academic_year_id' => $academicYear->id,
                'year' => $academicYear->year,
                'created_by' => $user->id
            ]);

            return redirect()->route('admin.academic-years.index')
                ->with('success', 'Academic year created successfully!');

        } catch (\Exception $e) {
            Log::error('Error creating academic year', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Failed to create academic year.'])
                ->withInput();
        }
    }

    /**
     * Update the specified academic year.
     */
    public function update(Request $request, AcademicYear $academicYear)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-academic-settings')) {
            abort(403, 'Unauthorized to update academic years.');
        }

        $validator = Validator::make($request->all(), [
            'year' => [
                'required',
                'string',
                'max:10',
                Rule::unique('academic_years', 'year')->ignore($academicYear->id),
            ],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }

        try {
            $academicYear->update($validator->validated());

            Log::info('Academic year updated', [
                'academic_year_id' => $academicYear->id,
                'updated_by' => $user->id,
                'changes' => $academicYear->getChanges()
            ]);

            return redirect()->route('admin.academic-years.index')
                ->with('success', 'Academic year updated successfully!');

        } catch (\Exception $e) {
            Log::error('Error updating academic year', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Failed to update academic year.'])
                ->withInput();
        }
    }

    /**
     * Display the specified academic year
     */
    public function show(AcademicYear $academicYear)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('view-academic-settings') && !$user->can('manage-academic-settings')) {
            abort(403, 'Unauthorized to view academic year details.');
        }

        try {
            return Inertia::render('Admin/AcademicYears/Show', [
                'academicYear' => [
                    'id' => $academicYear->id,
                    'year' => $academicYear->year,
                    'start_date' => $academicYear->start_date?->format('Y-m-d'),
                    'end_date' => $academicYear->end_date?->format('Y-m-d'),
                    'is_active' => $academicYear->is_active,
                    'description' => $academicYear->description,
                    'sort_order' => $academicYear->sort_order,
                    'status' => $academicYear->status,
                    'created_at' => $academicYear->created_at,
                    'updated_at' => $academicYear->updated_at,
                ],
                'semesters' => $academicYear->semesters()->get(),
                'intakeTypes' => $academicYear->intakeTypes()->get(),
                'can' => [
                    'update' => $user->hasRole('Admin') || $user->can('manage-academic-settings'),
                    'delete' => $user->hasRole('Admin') || $user->can('manage-academic-settings'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing academic year', [
                'academic_year_id' => $academicYear->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->route('admin.academic-years.index')
                ->withErrors(['error' => 'Unable to load academic year details.']);
        }
    }

    /**
     * Remove the specified academic year.
     */
    public function destroy(AcademicYear $academicYear)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-academic-settings')) {
            abort(403, 'Unauthorized to delete academic years.');
        }

        try {
            // Check if academic year has associated semesters
            if ($academicYear->semesters()->exists()) {
                return redirect()->route('admin.academic-years.index')
                    ->with('error', 'Cannot delete academic year with associated semesters.');
            }

            $year = $academicYear->year;
            $academicYear->delete();

            Log::info('Academic year deleted', [
                'year' => $year,
                'deleted_by' => $user->id
            ]);

            return redirect()->route('admin.academic-years.index')
                ->with('success', "Academic year '{$year}' deleted successfully!");

        } catch (\Exception $e) {
            Log::error('Error deleting academic year', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->route('admin.academic-years.index')
                ->with('error', 'Failed to delete academic year.');
        }
    }

    /**
     * Bulk delete academic years
     */
    public function bulkDelete(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-academic-settings')) {
            abort(403, 'Unauthorized to delete academic years.');
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:academic_years,id'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Invalid selection.');
        }

        try {
            // Check for academic years with associated data
            $yearsWithData = AcademicYear::whereIn('id', $request->ids)
                ->whereHas('semesters')
                ->pluck('year');

            if ($yearsWithData->isNotEmpty()) {
                return redirect()->back()->with('error', 
                    'Cannot delete academic years with associated semesters: ' . $yearsWithData->implode(', '));
            }

            $deleted = AcademicYear::whereIn('id', $request->ids)->delete();
            
            Log::info('Bulk academic years deletion', [
                'ids' => $request->ids,
                'count' => $deleted,
                'deleted_by' => $user->id
            ]);

            return redirect()->back()
                ->with('success', "Successfully deleted {$deleted} academic year(s).");

        } catch (\Exception $e) {
            Log::error('Error bulk deleting academic years', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->back()
                ->with('error', 'Error deleting academic years.');
        }
    }

    /**
     * API endpoint to get active academic years.
     */
    public function getActive()
    {
        try {
            $academicYears = AcademicYear::active()
                ->ordered()
                ->select('id', 'year', 'start_date', 'end_date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $academicYears
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching active academic years', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch academic years'
            ], 500);
        }
    }

    /**
     * API endpoint to get all academic years for dropdowns
     */
    public function getAll()
    {
        try {
            $academicYears = AcademicYear::ordered()
                ->select('id', 'year', 'is_active', 'start_date', 'end_date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $academicYears
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching all academic years', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch academic years'
            ], 500);
        }
    }
}