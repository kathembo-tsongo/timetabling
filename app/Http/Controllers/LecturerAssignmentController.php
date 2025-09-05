<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LecturerAssignment;
use App\Models\Unit;
use App\Models\Semester;
use App\Models\School;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LecturerAssignmentController extends Controller
{
    /**
     * Display a listing of lecturer assignments.
     */
    public function index(Request $request)
    {
        $query = Unit::with(['school', 'program', 'semester'])
            ->where('is_active', true);

        // Apply filters
        if ($request->semester_id) {
            // For lecturer assignments, we want to show all units and their lecturer assignment status
            // for the selected semester, regardless of the unit's semester_id
            $query->withLecturerForSemester($request->semester_id);
        }

        if ($request->school_id) {
            $query->where('school_id', $request->school_id);
        }

        if ($request->program_id) {
            $query->where('program_id', $request->program_id);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('code', 'like', '%' . $request->search . '%')
                  ->orWhere('name', 'like', '%' . $request->search . '%');
            });
        }

        $units = $query->paginate(15);

        // Transform units to include lecturer assignment information
        $units->through(function ($unit) use ($request) {
            $lecturerAssignment = null;
            if ($request->semester_id) {
                $lecturerAssignment = $unit->getLecturerAssignmentForSemester($request->semester_id);
            }

            return [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'credit_hours' => $unit->credit_hours,
                'school_id' => $unit->school_id,
                'program_id' => $unit->program_id,
                'lecturer_code' => $lecturerAssignment->lecturer_code ?? null,
                'lecturer_name' => $lecturerAssignment->lecturer_name ?? null,
                'lecturer_email' => $lecturerAssignment->lecturer_email ?? null,
                'assigned_semester_id' => $lecturerAssignment->semester_id ?? null,
                'school' => $unit->school,
                'program' => $unit->program,
                'semester' => $unit->semester,
            ];
        });

        // Get statistics - Fixed to use Spatie roles properly
        if ($request->semester_id) {
            $stats = Unit::getLecturerAssignmentStatistics($request->semester_id);
        } else {
            // Count lecturers using Spatie role system
            $lecturerCount = User::whereHas('roles', function ($query) {
                $query->where('name', 'lecturer');
            })->count();

            $stats = [
                'total_units' => Unit::where('is_active', true)->count(),
                'assigned_units' => 0,
                'unassigned_units' => 0,
                'total_lecturers' => $lecturerCount,
            ];
        }

        // FIXED: Corrected the lecturers query - removed incorrect 'first_name', 'last_name' from whereHas
        $lecturers = User::whereHas('roles', function ($query) {
            $query->where('name', 'lecturer');
        })->get(['id', 'code', 'first_name', 'last_name', 'email']);

        return Inertia::render('Admin/LecturerAssignment/Index', [
            'assignments' => $units,
            'semesters' => Semester::where('is_active', true)->get(),
            'schools' => School::where('is_active', true)->get(),
            'programs' => Program::with('school')->where('is_active', true)->get(),
            'lecturers' => $lecturers,
            'stats' => $stats,
            'filters' => $request->only(['search', 'semester_id', 'school_id', 'program_id']),
            'can' => [
                'create' => auth()->user()->can('manage-faculty-lecturers-sces') || 
                           auth()->user()->can('manage-faculty-lecturers-sbs') ||
                           auth()->user()->can('create-faculty-lecturers-sces') ||
                           auth()->user()->can('create-faculty-lecturers-sbs') ||
                           auth()->user()->hasRole('Admin'),
                'update' => auth()->user()->can('manage-faculty-lecturers-sces') || 
                           auth()->user()->can('manage-faculty-lecturers-sbs') ||
                           auth()->user()->can('edit-faculty-lecturers-sces') ||
                           auth()->user()->can('edit-faculty-lecturers-sbs') ||
                           auth()->user()->hasRole('Admin'),
                'delete' => auth()->user()->can('manage-faculty-lecturers-sces') || 
                           auth()->user()->can('manage-faculty-lecturers-sbs') ||
                           auth()->user()->can('delete-faculty-lecturers-sces') ||
                           auth()->user()->can('delete-faculty-lecturers-sbs') ||
                           auth()->user()->hasRole('Admin'),
            ],
        ]);
    }

    /**
     * Store a newly created lecturer assignment.
     */
    public function store(Request $request)
    {
        $request->validate([
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'lecturer_code' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            // Get unit and lecturer information using Spatie roles
            $unit = Unit::with(['school', 'program'])->findOrFail($request->unit_id);
            
            $lecturer = User::whereHas('roles', function ($query) {
                $query->where('name', 'lecturer');
            })->where('code', $request->lecturer_code)->firstOrFail();

            // Check if assignment already exists
            $existingAssignment = LecturerAssignment::where('unit_id', $request->unit_id)
                ->where('semester_id', $request->semester_id)
                ->where('is_active', true)
                ->first();

            if ($existingAssignment) {
                return back()->withErrors(['error' => 'This unit is already assigned to a lecturer for this semester.']);
            }

            // FIXED: Use proper name concatenation
            $lecturerFullName = trim($lecturer->first_name . ' ' . $lecturer->last_name);

            // Create the assignment
            LecturerAssignment::create([
                'unit_id' => $request->unit_id,
                'semester_id' => $request->semester_id,
                'lecturer_code' => $lecturer->code,
                'lecturer_name' => $lecturerFullName,
                'lecturer_email' => $lecturer->email,
                'school_id' => $unit->school_id,
                'program_id' => $unit->program_id,
                'credit_hours' => $unit->credit_hours,
                'assigned_by' => auth()->id(),
                'is_active' => true, // FIXED: Explicitly set is_active
            ]);

            DB::commit();

            return back()->with('success', 'Lecturer assigned successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to assign lecturer: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified lecturer assignment.
     */
    public function update(Request $request, $unitId, $semesterId)
    {
        $request->validate([
            'lecturer_code' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $unit = Unit::findOrFail($unitId);
            
            // FIXED: Find assignment directly instead of using unit method that may not exist
            $assignment = LecturerAssignment::where('unit_id', $unitId)
                ->where('semester_id', $semesterId)
                ->where('is_active', true)
                ->first();

            if (!$assignment) {
                return back()->withErrors(['error' => 'No assignment found for this unit and semester.']);
            }

            $lecturer = User::whereHas('roles', function ($query) {
                $query->where('name', 'lecturer');
            })->where('code', $request->lecturer_code)->firstOrFail();

            // FIXED: Use proper name concatenation
            $lecturerFullName = trim($lecturer->first_name . ' ' . $lecturer->last_name);

            $assignment->update([
                'lecturer_code' => $lecturer->code,
                'lecturer_name' => $lecturerFullName,
                'lecturer_email' => $lecturer->email,
                'assigned_by' => auth()->id(),
            ]);

            DB::commit();

            return back()->with('success', 'Lecturer assignment updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to update assignment: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified lecturer assignment.
     */
    public function destroy($unitId, $semesterId)
    {
        try {
            // FIXED: Find assignment directly
            $assignment = LecturerAssignment::where('unit_id', $unitId)
                ->where('semester_id', $semesterId)
                ->where('is_active', true)
                ->first();

            if (!$assignment) {
                return back()->withErrors(['error' => 'No assignment found for this unit and semester.']);
            }

            $assignment->delete();

            return back()->with('success', 'Lecturer assignment removed successfully!');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to remove assignment: ' . $e->getMessage()]);
        }
    }

    /**
     * Bulk assign lecturer to multiple units.
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'lecturer_code' => 'required|string',
        ]);

        try {
            DB::beginTransaction();

            $lecturer = User::whereHas('roles', function ($query) {
                $query->where('name', 'lecturer');
            })->where('code', $request->lecturer_code)->firstOrFail();

            $successCount = 0;
            $skippedCount = 0;

            // FIXED: Use proper name concatenation
            $lecturerFullName = trim($lecturer->first_name . ' ' . $lecturer->last_name);

            foreach ($request->unit_ids as $unitId) {
                $unit = Unit::with(['school', 'program'])->findOrFail($unitId);
                
                // Check if assignment already exists
                $existingAssignment = LecturerAssignment::where('unit_id', $unitId)
                    ->where('semester_id', $request->semester_id)
                    ->where('is_active', true)
                    ->first();

                if ($existingAssignment) {
                    $skippedCount++;
                    continue;
                }

                LecturerAssignment::create([
                    'unit_id' => $unitId,
                    'semester_id' => $request->semester_id,
                    'lecturer_code' => $lecturer->code,
                    'lecturer_name' => $lecturerFullName,
                    'lecturer_email' => $lecturer->email,
                    'school_id' => $unit->school_id,
                    'program_id' => $unit->program_id,
                    'credit_hours' => $unit->credit_hours,
                    'assigned_by' => auth()->id(),
                    'is_active' => true, // FIXED: Explicitly set is_active
                ]);

                $successCount++;
            }

            DB::commit();

            $message = "Successfully assigned {$successCount} units.";
            if ($skippedCount > 0) {
                $message .= " {$skippedCount} units were skipped (already assigned).";
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to complete bulk assignment: ' . $e->getMessage()]);
        }
    }

    /**
     * Get available units for bulk assignment.
     */
    public function getAvailableUnits(Request $request)
    {
        // Enhanced debugging
        \Log::info('=== getAvailableUnits Debug Start ===');
        \Log::info('Request parameters:', $request->all());
        \Log::info('User ID:', auth()->id());
        
        try {
            // Check if basic tables exist and have data
            $totalUnits = Unit::count();
            $activeUnits = Unit::where('is_active', true)->count();
            $totalSchools = School::count();
            $totalPrograms = Program::count();
            
            \Log::info('Database counts:', [
                'total_units' => $totalUnits,
                'active_units' => $activeUnits,
                'total_schools' => $totalSchools,
                'total_programs' => $totalPrograms
            ]);
            
            if ($activeUnits === 0) {
                \Log::warning('No active units found in database');
                return response()->json([
                    'units' => [],
                    'error' => 'No active units found in database',
                    'debug' => ['active_units' => $activeUnits]
                ]);
            }
            
            // Build query step by step
            $query = Unit::with(['school', 'program'])->where('is_active', true);
            
            // Apply filters and log each step
            if ($request->school_id) {
                \Log::info('Filtering by school_id:', $request->school_id);
                $query->where('school_id', $request->school_id);
            }
            
            if ($request->program_id) {
                \Log::info('Filtering by program_id:', $request->program_id);
                $query->where('program_id', $request->program_id);
            }
            
            // Get raw SQL for debugging
            $sql = $query->toSql();
            $bindings = $query->getBindings();
            \Log::info('Query SQL:', ['sql' => $sql, 'bindings' => $bindings]);
            
            // Execute query
            $allUnits = $query->get();
            \Log::info('Units after filters:', ['count' => $allUnits->count()]);
            
            if ($allUnits->count() === 0) {
                \Log::warning('No units found after applying filters');
                return response()->json([
                    'units' => [],
                    'error' => 'No units found matching the selected criteria',
                    'debug' => [
                        'school_id' => $request->school_id,
                        'program_id' => $request->program_id,
                        'semester_id' => $request->semester_id
                    ]
                ]);
            }
            
            // Process each unit
            $units = $allUnits->map(function ($unit) use ($request) {
                $lecturerAssignment = null;
                $isAssigned = false;
                
                // Check for lecturer assignment if semester is provided
                if ($request->semester_id) {
                    $lecturerAssignment = LecturerAssignment::where('unit_id', $unit->id)
                        ->where('semester_id', $request->semester_id)
                        ->where('is_active', true)
                        ->first();
                    $isAssigned = $lecturerAssignment !== null;
                    
                    \Log::debug("Unit {$unit->code} assignment check:", [
                        'unit_id' => $unit->id,
                        'semester_id' => $request->semester_id,
                        'is_assigned' => $isAssigned
                    ]);
                }
                
                return [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'credit_hours' => $unit->credit_hours,
                    'school' => $unit->school ? [
                        'id' => $unit->school->id,
                        'name' => $unit->school->name,
                        'code' => $unit->school->code ?? null
                    ] : null,
                    'program' => $unit->program ? [
                        'id' => $unit->program->id,
                        'name' => $unit->program->name,
                        'code' => $unit->program->code ?? null
                    ] : null,
                    'is_assigned' => $isAssigned,
                    'assignment' => $lecturerAssignment ? [
                        'lecturer_name' => $lecturerAssignment->lecturer_name,
                        'lecturer_code' => $lecturerAssignment->lecturer_code,
                        'lecturer_email' => $lecturerAssignment->lecturer_email,
                    ] : null
                ];
            });
            
            \Log::info('Final units processed:', ['count' => $units->count()]);
            \Log::info('=== getAvailableUnits Debug End ===');
            
            return response()->json([
                'success' => true,
                'units' => $units,
                'total_count' => $units->count(),
                'debug_info' => [
                    'request_params' => $request->all(),
                    'total_active_units' => $activeUnits,
                    'filtered_units' => $allUnits->count(),
                    'final_units' => $units->count()
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error in getAvailableUnits:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Server error occurred',
                'message' => $e->getMessage(),
                'units' => []
            ], 500);
        }
    }

    /**
     * Get lecturer workload for a specific semester.
     */
    public function getLecturerWorkload(Request $request)
    {
        $lecturerCode = $request->lecturer_code;
        $semesterId = $request->semester_id;

        if (!$lecturerCode || !$semesterId) {
            return response()->json(['error' => 'Lecturer code and semester ID are required'], 400);
        }

        $assignments = LecturerAssignment::where('lecturer_code', $lecturerCode)
            ->where('semester_id', $semesterId)
            ->where('is_active', true)
            ->with(['unit', 'school', 'program'])
            ->get();

        $statistics = [
            'total_units' => $assignments->count(),
            'total_credit_hours' => $assignments->sum('credit_hours'),
            'total_students' => 0, // You can calculate this based on your enrollment data
        ];

        return response()->json([
            'assignments' => $assignments,
            'statistics' => $statistics,
        ]);
    }
}