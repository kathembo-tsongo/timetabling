<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Elective;
use App\Models\Enrollment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;


class ElectiveController extends Controller
{
    /**
     * Display a listing of the electives.
     */
    public function index(Request $request)
    {
        $query = Elective::with(['unit.school', 'unit.enrollments' => function ($query) {
            $query->where('status', 'enrolled');
        }]);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('unit', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Year level filter
        if ($request->filled('year_level')) {
            $query->where('year_level', $request->year_level);
        }

        // Status filter
        if ($request->filled('status')) {
            $isActive = $request->status === 'active';
            $query->where('is_active', $isActive);
        }

        $electives = $query->orderBy('category')
                        ->orderBy('year_level')
                        ->orderBy('created_at', 'desc')
                        ->paginate(10)
                        ->through(function ($elective) {
                            $currentEnrollment = $elective->unit->enrollments->count();
                            $availableSpots = $elective->max_students 
                                ? max(0, $elective->max_students - $currentEnrollment)
                                : null;

                            return [
                                'id' => $elective->id,
                                'unit_id' => $elective->unit_id,
                                'category' => $elective->category,
                                'year_level' => $elective->year_level,
                                'semester_offered' => $elective->semester_offered,
                                'max_students' => $elective->max_students,
                                'min_students' => $elective->min_students,
                                'is_active' => $elective->is_active,
                                'description' => $elective->description,
                                'prerequisites' => $elective->prerequisites,
                                'unit' => [
                                    'id' => $elective->unit->id,
                                    'code' => $elective->unit->code,
                                    'name' => $elective->unit->name,
                                    'credit_hours' => $elective->unit->credit_hours,
                                ],
                                'current_enrollment' => $currentEnrollment,
                                'available_spots' => $availableSpots,
                                'created_at' => $elective->created_at->toDateTimeString(),
                                'updated_at' => $elective->updated_at->toDateTimeString(),
                            ];
                        });

        // ✅ FIXED: Get SHSS school ID from database, not from user
        $shssSchool = \App\Models\School::where('code', 'SHSS')->first();
        
        if (!$shssSchool) {
            \Log::error('SHSS School not found in database');
            $allUnits = collect(); // Empty collection
        } else {
            // Get ALL units for SHSS school
            $allUnits = Unit::where('school_id', $shssSchool->id)
                            ->orderBy('code')
                            ->get(['id', 'code', 'name', 'credit_hours']);
            
            \Log::info('Fetched units for SHSS', [
                'school_id' => $shssSchool->id,
                'units_count' => $allUnits->count()
            ]);
        }

        return Inertia::render('Schools/SHSS/Programs/Electives/Index', [
            'electives' => $electives,
            'units' => $allUnits,
            'filters' => $request->only(['search', 'category', 'year_level', 'status']),
        ]);
    }
    /**
     * Store a newly created elective.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id|unique:electives,unit_id',
            'category' => 'required|in:language,other',
            'year_level' => 'required|integer|min:1|max:4',
            'semester_offered' => 'nullable|string|max:50',
            'max_students' => 'nullable|integer|min:1',
            'min_students' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
            'prerequisites' => 'nullable|string',
        ]);

        try {
            // Validate that min_students is not greater than max_students
            if (isset($validated['min_students']) && isset($validated['max_students'])) {
                if ($validated['min_students'] > $validated['max_students']) {
                    return back()->withErrors([
                        'min_students' => 'Minimum students cannot be greater than maximum students.'
                    ]);
                }
            }

            $elective = Elective::create($validated);

            return redirect()->route('schools.shss.electives.index') 
                 ->with('success', 'Elective created successfully!');
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Failed to create elective: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Display the specified elective.
     */
    public function show(Elective $elective)
    {
        $elective->load(['unit', 'unit.enrollments.student']);

        return Inertia::render('School/ElectiveDetail', [
            'elective' => $elective,
        ]);
    }

    /**
     * Update the specified elective.
     */
    public function update(Request $request, Elective $elective)
    {
        $validated = $request->validate([
            'category' => 'required|in:language,other',
            'year_level' => 'required|integer|min:1|max:4',
            'semester_offered' => 'nullable|string|max:50',
            'max_students' => 'nullable|integer|min:1',
            'min_students' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
            'prerequisites' => 'nullable|string',
        ]);

        try {
            // Validate capacity constraints
            if (isset($validated['min_students']) && isset($validated['max_students'])) {
                if ($validated['min_students'] > $validated['max_students']) {
                    return back()->withErrors([
                        'min_students' => 'Minimum students cannot be greater than maximum students.'
                    ]);
                }
            }

            // If reducing max_students, check current enrollment
            if (isset($validated['max_students'])) {
                $currentEnrollment = $elective->unit->enrollments()
                    ->where('status', 'enrolled')
                    ->count();
                
                if ($validated['max_students'] < $currentEnrollment) {
                    return back()->withErrors([
                        'max_students' => "Cannot set maximum students below current enrollment ({$currentEnrollment})."
                    ]);
                }
            }

            $elective->update($validated);

            return redirect()->route('schools.shss.electives.index')
                           ->with('success', 'Elective updated successfully!');
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Failed to update elective: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Toggle the active status of the elective.
     */
    public function toggleStatus(Elective $elective)
    {
        try {
            $elective->update([
                'is_active' => !$elective->is_active
            ]);

            $status = $elective->is_active ? 'activated' : 'deactivated';
            
            return back()->with('success', "Elective {$status} successfully!");
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Failed to update status: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Remove the specified elective.
     */
    public function destroy(Elective $elective)
    {
        try {
            // Check if there are any enrollments
            $enrollmentCount = $elective->unit->enrollments()
                ->where('status', 'enrolled')
                ->count();

            if ($enrollmentCount > 0) {
                return back()->withErrors([
                    'error' => "Cannot delete elective with active enrollments ({$enrollmentCount} students enrolled)."
                ]);
            }

            $elective->delete();

            return redirect()->route('schools.shss.electives.index')
                           ->with('success', 'Elective deleted successfully!');
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Failed to delete elective: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get electives statistics for dashboard
     */
    public function getStats()
    {
        $stats = [
            'total' => Elective::count(),
            'active' => Elective::where('is_active', true)->count(),
            'language' => Elective::where('category', 'language')->count(),
            'other' => Elective::where('category', 'other')->count(),
            'by_year' => Elective::select('year_level', DB::raw('count(*) as count'))
                ->groupBy('year_level')
                ->orderBy('year_level')
                ->pluck('count', 'year_level'),
            'enrollment_summary' => $this->getEnrollmentSummary(),
        ];

        return response()->json($stats);
    }

    /**
     * Get enrollment summary for all electives
     */
    private function getEnrollmentSummary()
    {
        $electives = Elective::with(['unit.enrollments' => function ($query) {
            $query->where('status', 'enrolled');
        }])->get();

        $summary = [];
        foreach ($electives as $elective) {
            $currentEnrollment = $elective->unit->enrollments->count();
            $utilization = $elective->max_students 
                ? ($currentEnrollment / $elective->max_students) * 100 
                : null;

            $summary[] = [
                'unit_code' => $elective->unit->code,
                'category' => $elective->category,
                'enrollment' => $currentEnrollment,
                'capacity' => $elective->max_students,
                'utilization' => $utilization,
            ];
        }

        return $summary;
    }

    public function getAvailableElectivesForStudent(Request $request)
    {
        try {
            $studentCode = $request->input('student_code');
            
            if (!$studentCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student code is required'
                ], 400);
            }

            // Find student by code in users table
            $student = User::where('code', $studentCode)->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            // Get student's any enrollment to find their class info
            $enrollment = Enrollment::where('student_id', $student->id)
                ->with(['class.program.school', 'class.semester'])
                ->first();

            if (!$enrollment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student has no active enrollments'
                ], 404);
            }

            $class = $enrollment->class;
            $program = $class->program;
            $school = $program->school;

            // Get units student is already enrolled in
            $enrolledUnitIds = Enrollment::where('student_id', $student->id)
                ->pluck('unit_id')
                ->toArray();

            // ✅ Get ALL active electives from ALL schools (not just SHSS)
            $electives = Elective::where('is_active', true)
                ->whereNotIn('unit_id', $enrolledUnitIds)
                ->with('unit')
                ->get();

            // Separate by category
            $languageElectives = $electives->where('category', 'language')->map(function($elective) {
                return [
                    'id' => $elective->unit_id,
                    'code' => $elective->unit->code,
                    'name' => $elective->unit->name,
                    'credit_hours' => $elective->unit->credit_hours ?? 0,
                    'category' => $elective->category
                ];
            })->values();

            $otherElectives = $electives->where('category', 'other')->map(function($elective) {
                return [
                    'id' => $elective->unit_id,
                    'code' => $elective->unit->code,
                    'name' => $elective->unit->name,
                    'credit_hours' => $elective->unit->credit_hours ?? 0,
                    'category' => $elective->category
                ];
            })->values();

            return response()->json([
                'success' => true,
                'student' => [
                    'code' => $student->code,
                    'name' => $student->name,
                    'school' => $school->name,
                    'program' => $program->name,
                    'class' => $class->name . ' Section ' . $class->section,
                    'year_level' => $class->year_level
                ],
                'language_electives' => $languageElectives,
                'other_electives' => $otherElectives
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching available electives: ' . $e->getMessage(), [
                'student_code' => $request->input('student_code'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available electives: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enroll student in selected electives - SIMPLIFIED VERSION
     */
    public function enrollStudentInElectives(Request $request)
    {
        try {
            $validated = $request->validate([
                'student_code' => 'required|string',
                'elective_ids' => 'required|array|min:1',
                'elective_ids.*' => 'required|exists:units,id'
            ]);

            $studentCode = $validated['student_code'];
            $electiveIds = $validated['elective_ids'];

            // Find student by code
            $student = User::where('code', $studentCode)->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            // Get student's existing enrollment to get class and semester info
            $existingEnrollment = Enrollment::where('student_id', $student->id)
                ->with(['class'])
                ->first();

            if (!$existingEnrollment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student has no active enrollments'
                ], 404);
            }

            DB::beginTransaction();

            $enrolled = [];
            $errors = [];

            foreach ($electiveIds as $unitId) {
                // Check if already enrolled
                $alreadyEnrolled = Enrollment::where('student_id', $student->id)
                    ->where('unit_id', $unitId)
                    ->exists();

                if ($alreadyEnrolled) {
                    $unit = Unit::find($unitId);
                    $errors[] = "Already enrolled in " . ($unit ? $unit->code : "unit ID {$unitId}");
                    continue;
                }

                // Create enrollment
                Enrollment::create([
                    'student_id' => $student->id,
                    'unit_id' => $unitId,
                    'class_id' => $existingEnrollment->class_id,
                    'semester_id' => $existingEnrollment->semester_id,
                    'enrollment_date' => now(),
                    'status' => 'active'
                ]);

                $enrolled[] = $unitId;
            }

            DB::commit();

            $message = count($enrolled) > 0 
                ? 'Successfully enrolled in ' . count($enrolled) . ' elective(s)!'
                : 'No new electives were enrolled.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'enrolled_count' => count($enrolled),
                'errors' => $errors
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error enrolling student in electives: ' . $e->getMessage(), [
                'student_code' => $request->input('student_code'),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to enroll student: ' . $e->getMessage()
            ], 500);
        }
    } 
}