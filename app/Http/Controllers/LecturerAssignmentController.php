<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\User;
use App\Models\Semester;
use App\Models\School;
use App\Models\Program;
use App\Models\Enrollment;
use App\Models\UnitAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LecturerAssignmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display lecturer assignments for units in semesters
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Check permissions
        if (!$user->can('manage-lecturer-assignments') && !$user->hasRole('Admin')) {
            abort(403, 'Unauthorized to manage lecturer assignments.');
        }

        try {
            // Get filters
            $semesterId = $request->input('semester_id');
            $schoolId = $request->input('school_id');
            $programId = $request->input('program_id');
            $search = $request->input('search', '');

            // Build query for unit assignments with lecturer information
            $query = Unit::with(['school', 'program', 'semester'])
                ->leftJoin('enrollments', function($join) use ($semesterId) {
                    $join->on('units.id', '=', 'enrollments.unit_id')
                         ->where('enrollments.lecturer_code', '!=', '')
                         ->whereNotNull('enrollments.lecturer_code');
                    if ($semesterId) {
                        $join->where('enrollments.semester_id', $semesterId);
                    }
                })
                ->leftJoin('users', 'users.code', '=', 'enrollments.lecturer_code')
                ->select(
                    'units.*',
                    'enrollments.lecturer_code',
                    'enrollments.semester_id as assigned_semester_id',
                    DB::raw("CONCAT(users.first_name, ' ', users.last_name) as lecturer_name"),
                    'users.email as lecturer_email'
                )
                ->where('units.is_active', true);

            // Apply filters
            if ($semesterId) {
                $query->where(function($q) use ($semesterId) {
                    $q->where('enrollments.semester_id', $semesterId)
                      ->orWhereNull('enrollments.semester_id');
                });
            }

            if ($schoolId) {
                $query->where('units.school_id', $schoolId);
            }

            if ($programId) {
                $query->where('units.program_id', $programId);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('units.code', 'like', "%{$search}%")
                      ->orWhere('units.name', 'like', "%{$search}%")
                      ->orWhere(DB::raw("CONCAT(users.first_name, ' ', users.last_name)"), 'like', "%{$search}%");
                });
            }

            $assignments = $query->orderBy('units.code')->paginate(15)->withQueryString();

            // Get dropdown data
            $semesters = Semester::orderBy('name')->get();
            $schools = School::orderBy('name')->get();
            $programs = Program::with('school')->orderBy('name')->get();
            $lecturers = User::role('Lecturer')
                ->select('id', 'code', DB::raw("CONCAT(first_name, ' ', last_name) as name"), 'email')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();

            // Get statistics
            $stats = [
                'total_units' => Unit::where('is_active', true)->count(),
                'assigned_units' => Unit::whereHas('enrollments', function($q) use ($semesterId) {
                    $q->whereNotNull('lecturer_code')->where('lecturer_code', '!=', '');
                    if ($semesterId) {
                        $q->where('semester_id', $semesterId);
                    }
                })->count(),
                'unassigned_units' => Unit::whereDoesntHave('enrollments', function($q) use ($semesterId) {
                    $q->whereNotNull('lecturer_code')->where('lecturer_code', '!=', '');
                    if ($semesterId) {
                        $q->where('semester_id', $semesterId);
                    }
                })->where('is_active', true)->count(),
                'total_lecturers' => User::role('Lecturer')->count(),
            ];

            return Inertia::render('Admin/LecturerAssignment/Index', [
                'assignments' => $assignments,
                'semesters' => $semesters,
                'schools' => $schools,
                'programs' => $programs,
                'lecturers' => $lecturers,
                'stats' => $stats,
                'filters' => $request->only(['semester_id', 'school_id', 'program_id', 'search']),
                'can' => [
                    'create' => $user->can('create-lecturer-assignments') || $user->hasRole('Admin'),
                    'update' => $user->can('update-lecturer-assignments') || $user->hasRole('Admin'),
                    'delete' => $user->can('delete-lecturer-assignments') || $user->hasRole('Admin'),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading lecturer assignments: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors(['error' => 'Failed to load lecturer assignments. Please try again.']);
        }
    }

    /**
     * Store or update lecturer assignment for a unit in a semester
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user->can('create-lecturer-assignments') && !$user->hasRole('Admin')) {
            abort(403, 'Unauthorized to create lecturer assignments.');
        }

        $validator = \Validator::make($request->all(), [
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'lecturer_code' => 'required|exists:users,code',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->with('error', 'Please check the form for errors.');
        }

        try {
            DB::beginTransaction();

            $unit = Unit::findOrFail($request->unit_id);
            $semester = Semester::findOrFail($request->semester_id);
            $lecturer = User::where('code', $request->lecturer_code)->firstOrFail();

            // Check if lecturer has the 'Lecturer' role
            if (!$lecturer->hasRole('Lecturer')) {
                return back()->with('error', 'Selected user is not a lecturer.');
            }

            // Check for existing assignment
            $existingAssignment = Enrollment::where('unit_id', $request->unit_id)
                ->where('semester_id', $request->semester_id)
                ->whereNotNull('lecturer_code')
                ->where('lecturer_code', '!=', '')
                ->first();

            if ($existingAssignment && $existingAssignment->lecturer_code !== $request->lecturer_code) {
                return back()->with('error', 'This unit is already assigned to another lecturer for this semester.');
            }

            // Update or create enrollments with lecturer assignment
            $affected = Enrollment::where('unit_id', $request->unit_id)
                ->where('semester_id', $request->semester_id)
                ->update(['lecturer_code' => $request->lecturer_code]);

            // If no enrollments exist yet, we'll track this assignment for future enrollments
            if ($affected === 0) {
                // Create a placeholder assignment record (you might want to create a separate table for this)
                // For now, we'll just log it
                Log::info('Lecturer assigned to unit with no current enrollments', [
                    'unit_id' => $request->unit_id,
                    'unit_code' => $unit->code,
                    'semester_id' => $request->semester_id,
                    'lecturer_code' => $request->lecturer_code,
                    'lecturer_name' => $lecturer->first_name . ' ' . $lecturer->last_name,
                    'assigned_by' => $user->id
                ]);
            }

            DB::commit();

            $message = "Lecturer {$lecturer->first_name} {$lecturer->last_name} assigned to {$unit->code} - {$unit->name} for {$semester->name}.";
            if ($affected > 0) {
                $message .= " Updated {$affected} existing enrollment(s).";
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating lecturer assignment: ' . $e->getMessage(), [
                'data' => $request->all(),
                'user_id' => $user->id
            ]);

            return back()->with('error', 'Failed to assign lecturer. Please try again.');
        }
    }

    /**
     * Update lecturer assignment
     */
    public function update(Request $request, $unitId, $semesterId)
    {
        $user = auth()->user();

        if (!$user->can('update-lecturer-assignments') && !$user->hasRole('Admin') && !$user->hasRole('Admin')) {
            abort(403, 'Unauthorized to update lecturer assignments.');
        }

        $validator = \Validator::make($request->all(), [
            'lecturer_code' => 'required|exists:users,code',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->with('error', 'Please check the form for errors.');
        }

        try {
            DB::beginTransaction();

            $unit = Unit::findOrFail($unitId);
            $semester = Semester::findOrFail($semesterId);
            $lecturer = User::where('code', $request->lecturer_code)->firstOrFail();

            // Check if lecturer has the 'Lecturer' role
            if (!$lecturer->hasRole('Lecturer')) {
                return back()->with('error', 'Selected user is not a lecturer.');
            }

            // Update all enrollments for this unit in this semester
            $affected = Enrollment::where('unit_id', $unitId)
                ->where('semester_id', $semesterId)
                ->update(['lecturer_code' => $request->lecturer_code]);

            DB::commit();

            Log::info('Lecturer assignment updated', [
                'unit_id' => $unitId,
                'unit_code' => $unit->code,
                'semester_id' => $semesterId,
                'old_lecturer' => request()->input('old_lecturer_code'),
                'new_lecturer_code' => $request->lecturer_code,
                'affected_enrollments' => $affected,
                'updated_by' => $user->id
            ]);

            return back()->with('success', "Lecturer updated for {$unit->code} - {$unit->name}. {$affected} enrollment(s) updated.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating lecturer assignment: ' . $e->getMessage(), [
                'unit_id' => $unitId,
                'semester_id' => $semesterId,
                'data' => $request->all(),
                'user_id' => $user->id
            ]);

            return back()->with('error', 'Failed to update lecturer assignment. Please try again.');
        }
    }

    /**
     * Remove lecturer assignment
     */
    public function destroy($unitId, $semesterId)
    {
        $user = auth()->user();

        if (!$user->can('delete-lecturer-assignments') && !$user->hasRole('Admin')) {
            abort(403, 'Unauthorized to delete lecturer assignments.');
        }

        try {
            DB::beginTransaction();

            $unit = Unit::findOrFail($unitId);
            $semester = Semester::findOrFail($semesterId);

            // Remove lecturer from all enrollments for this unit in this semester
            $affected = Enrollment::where('unit_id', $unitId)
                ->where('semester_id', $semesterId)
                ->update(['lecturer_code' => null]);

            DB::commit();

            Log::info('Lecturer assignment removed', [
                'unit_id' => $unitId,
                'unit_code' => $unit->code,
                'semester_id' => $semesterId,
                'affected_enrollments' => $affected,
                'removed_by' => $user->id
            ]);

            return back()->with('success', "Lecturer removed from {$unit->code} - {$unit->name}. {$affected} enrollment(s) updated.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error removing lecturer assignment: ' . $e->getMessage(), [
                'unit_id' => $unitId,
                'semester_id' => $semesterId,
                'user_id' => $user->id
            ]);

            return back()->with('error', 'Failed to remove lecturer assignment. Please try again.');
        }
    }

    /**
     * Get available lecturers for assignment
     */
    public function getAvailableLecturers(Request $request)
    {
        try {
            $semesterId = $request->input('semester_id');
            $schoolId = $request->input('school_id');

            $query = User::role('Lecturer')
                ->select('id', 'code', DB::raw("CONCAT(first_name, ' ', last_name) as name"), 'email', 'school_id');

            // Filter by school if provided
            if ($schoolId) {
                $query->where('school_id', $schoolId);
            }

            $lecturers = $query->orderBy('first_name')->orderBy('last_name')->get();

            // Add workload information for each lecturer
            $lecturersWithWorkload = $lecturers->map(function($lecturer) use ($semesterId) {
                $workload = 0;
                if ($semesterId) {
                    $workload = Enrollment::where('lecturer_code', $lecturer->code)
                        ->where('semester_id', $semesterId)
                        ->distinct('unit_id')
                        ->count();
                }

                return [
                    'id' => $lecturer->id,
                    'code' => $lecturer->code,
                    'name' => $lecturer->name,
                    'email' => $lecturer->email,
                    'school_id' => $lecturer->school_id,
                    'current_workload' => $workload
                ];
            });

            return response()->json($lecturersWithWorkload);

        } catch (\Exception $e) {
            Log::error('Error fetching available lecturers: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch lecturers'], 500);
        }
    }

    /**
     * Get lecturer workload summary
     */
    public function getLecturerWorkload(Request $request)
    {
        try {
            $semesterId = $request->input('semester_id');
            $lecturerCode = $request->input('lecturer_code');

            if (!$semesterId || !$lecturerCode) {
                return response()->json(['error' => 'Semester ID and Lecturer Code are required'], 400);
            }

            $lecturer = User::where('code', $lecturerCode)->first();
            if (!$lecturer) {
                return response()->json(['error' => 'Lecturer not found'], 404);
            }

            // Get assigned units
            $assignedUnits = Unit::whereHas('enrollments', function($q) use ($lecturerCode, $semesterId) {
                $q->where('lecturer_code', $lecturerCode)
                  ->where('semester_id', $semesterId);
            })->with(['school', 'program'])->get();

            // Get total students taught
            $totalStudents = Enrollment::where('lecturer_code', $lecturerCode)
                ->where('semester_id', $semesterId)
                ->count();

            // Get total credit hours
            $totalCreditHours = $assignedUnits->sum('credit_hours');

            return response()->json([
                'lecturer' => [
                    'code' => $lecturer->code,
                    'name' => $lecturer->first_name . ' ' . $lecturer->last_name,
                    'email' => $lecturer->email
                ],
                'assigned_units' => $assignedUnits,
                'statistics' => [
                    'total_units' => $assignedUnits->count(),
                    'total_students' => $totalStudents,
                    'total_credit_hours' => $totalCreditHours
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching lecturer workload: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch workload'], 500);
        }
    }

    /**
     * Bulk assign lecturer to multiple units
     */
    public function bulkAssign(Request $request)
    {
        $user = auth()->user();

        if (!$user->can('create-lecturer-assignments') && !$user->hasRole('Admin')) {
            abort(403, 'Unauthorized to create lecturer assignments.');
        }

        $validator = \Validator::make($request->all(), [
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'lecturer_code' => 'required|exists:users,code',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->with('error', 'Please check the form for errors.');
        }

        try {
            DB::beginTransaction();

            $semester = Semester::findOrFail($request->semester_id);
            $lecturer = User::where('code', $request->lecturer_code)->firstOrFail();

            if (!$lecturer->hasRole('Lecturer')) {
                return back()->with('error', 'Selected user is not a lecturer.');
            }

            $successCount = 0;
            $errors = [];

            foreach ($request->unit_ids as $unitId) {
                $unit = Unit::find($unitId);
                if (!$unit) continue;

                // Check for existing assignment
                $existingAssignment = Enrollment::where('unit_id', $unitId)
                    ->where('semester_id', $request->semester_id)
                    ->whereNotNull('lecturer_code')
                    ->where('lecturer_code', '!=', '')
                    ->where('lecturer_code', '!=', $request->lecturer_code)
                    ->exists();

                if ($existingAssignment) {
                    $errors[] = "{$unit->code} is already assigned to another lecturer";
                    continue;
                }

                // Update enrollments
                Enrollment::where('unit_id', $unitId)
                    ->where('semester_id', $request->semester_id)
                    ->update(['lecturer_code' => $request->lecturer_code]);

                $successCount++;
            }

            DB::commit();

            $message = "Successfully assigned {$lecturer->first_name} {$lecturer->last_name} to {$successCount} units.";
            if (!empty($errors)) {
                $message .= " Issues: " . implode(', ', $errors);
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in bulk lecturer assignment: ' . $e->getMessage(), [
                'data' => $request->all(),
                'user_id' => $user->id
            ]);

            return back()->with('error', 'Failed to assign lecturers. Please try again.');
        }
    }
}