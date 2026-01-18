<?php

namespace App\Http\Controllers;

use App\Models\UnitAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Models\ClassModel;
use App\Models\Semester;
use App\Models\School;
use App\Models\Program;
use App\Models\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;


class LecturerAssignmentController extends Controller
{
    public function index(Request $request)
    {
        // Base query for unit assignments with lecturer information
        $query = UnitAssignment::with([
            'unit.school',
            'unit.program', 
            'class.program.school',
            'semester',
            'lecturer' // Assuming you have a lecturer relationship
        ]);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('unit', function($unitQuery) use ($search) {
                    $unitQuery->where('code', 'like', '%' . $search . '%')
                             ->orWhere('name', 'like', '%' . $search . '%');
                })
                ->orWhere('lecturer_code', 'like', '%' . $search . '%')
                ->orWhereHas('lecturer', function($lecturerQuery) use ($search) {
                    $lecturerQuery->where('first_name', 'like', '%' . $search . '%')
                                  ->orWhere('last_name', 'like', '%' . $search . '%');
                });
            });
        }

        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->semester_id);
        }

        if ($request->filled('school_id')) {
            $query->whereHas('unit', function($q) use ($request) {
                $q->where('school_id', $request->school_id);
            });
        }

        if ($request->filled('program_id')) {
            $query->whereHas('unit', function($q) use ($request) {
                $q->where('program_id', $request->program_id);
            });
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->filled('lecturer_code')) {
            $query->where('lecturer_code', $request->lecturer_code);
        }

        if ($request->filled('assignment_status')) {
            if ($request->assignment_status === 'assigned') {
                $query->whereNotNull('lecturer_code');
            } elseif ($request->assignment_status === 'unassigned') {
                $query->whereNull('lecturer_code');
            }
        }

        // Order by latest first
        $query->orderBy('created_at', 'desc');

        // Paginate results
        $assignments = $query->paginate(15)->withQueryString();

        // Get lecturer assignments separately with independent pagination
        $lecturerAssignments = $this->getLecturerAssignments($request);

        // Get lecturers (users with lecturer role)
        $lecturers = User::with(['school', 'program'])
            ->role('Lecturer')
            ->orderByRaw("CONCAT(first_name, ' ', last_name)")
            ->get();

        $schools = School::orderBy('name')->get();
        $programs = Program::with('school')->orderBy('name')->get();
        $semesters = Semester::orderBy('name')->get();
        $classes = ClassModel::with(['program', 'semester'])
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        // Calculate statistics
        $allAssignments = UnitAssignment::all();
        $stats = [
            'total_assignments' => $allAssignments->count(),
            'assigned' => $allAssignments->whereNotNull('lecturer_code')->count(),
            'unassigned' => $allAssignments->whereNull('lecturer_code')->count(),
            'unique_lecturers' => $allAssignments->whereNotNull('lecturer_code')->pluck('lecturer_code')->unique()->count(),
        ];

        return Inertia::render('Admin/LecturerAssignments/Index', [
            'assignments' => $assignments,
            'lecturerAssignments' => $lecturerAssignments, // Added separate lecturer assignments with pagination
            'lecturers' => $lecturers,
            'schools' => $schools,
            'programs' => $programs,
            'semesters' => $semesters,
            'classes' => $classes,
            'stats' => $stats,
            'filters' => $request->only(['search', 'semester_id', 'school_id', 'program_id', 'class_id', 'lecturer_code', 'assignment_status']),
            'can' => [
                'assign' => auth()->user()->can('assign-lecturers'),
                'unassign' => auth()->user()->can('unassign-lecturers'),
                'bulk_assign' => auth()->user()->can('bulk-assign-lecturers'),
            ]
        ]);
    }

    // NEW METHOD: Get lecturer assignments with independent pagination
    public function getLecturerAssignments(Request $request)
    {
        // Base query for lecturer assignments (only those with assigned lecturers)
        $query = UnitAssignment::with([
            'unit.school',
            'unit.program', 
            'class.program.school',
            'semester',
            'lecturer'
        ])->whereNotNull('lecturer_code')
          ->where('lecturer_code', '!=', '');

        // Apply filters if provided (you can customize these based on your needs)
        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->semester_id);
        }

        if ($request->filled('school_id')) {
            $query->whereHas('unit', function($q) use ($request) {
                $q->where('school_id', $request->school_id);
            });
        }

        if ($request->filled('program_id')) {
            $query->whereHas('unit', function($q) use ($request) {
                $q->where('program_id', $request->program_id);
            });
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->filled('lecturer_code')) {
            $query->where('lecturer_code', $request->lecturer_code);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('unit', function($unitQuery) use ($search) {
                    $unitQuery->where('code', 'like', '%' . $search . '%')
                             ->orWhere('name', 'like', '%' . $search . '%');
                })
                ->orWhere('lecturer_code', 'like', '%' . $search . '%')
                ->orWhereHas('lecturer', function($lecturerQuery) use ($search) {
                    $lecturerQuery->where('first_name', 'like', '%' . $search . '%')
                                  ->orWhere('last_name', 'like', '%' . $search . '%');
                });
            });
        }

        // Order by latest first
        $query->orderBy('created_at', 'desc');

        // Use lecturer_page parameter for independent pagination
        $page = $request->input('lecturer_page', 1);
        
        // Paginate with custom page parameter and page name
        $lecturerAssignments = $query->paginate(100, ['*'], 'lecturer_page', $page);
        
        // Manually set the path and append query parameters
        $lecturerAssignments->withPath($request->url());
        $lecturerAssignments->appends($request->except('lecturer_page'));

        return $lecturerAssignments;
    }

    public function store(Request $request){

         Log::info('Lecturer assignment request received:', $request->all());

    $validator = Validator::make($request->all(), [
        'unit_id' => 'required|exists:units,id',
        'lecturer_code' => 'required|string',
        'semester_id' => 'required|exists:semesters,id',
        'class_id' => 'required|exists:classes,id'  // Add this validation
    ]);

    if ($validator->fails()) {
        return redirect()->back()
            ->withErrors($validator)
            ->with('error', 'Please check the form for errors.');
    }

    try {
        DB::beginTransaction();

        // Find lecturer by code with school information
        $lecturer = User::with(['school', 'program'])
            ->where('code', $request->lecturer_code)
            ->role('Lecturer')
            ->first();

        if (!$lecturer) {
            return redirect()->back()
                ->with('error', 'Lecturer with code "' . $request->lecturer_code . '" not found.');
        }

        // Get unit and semester details
        $unit = Unit::with(['school', 'program'])->find($request->unit_id);
        $semester = Semester::find($request->semester_id);
        $class = ClassModel::find($request->class_id);

        // Check if this exact assignment already exists
        $existingAssignment = UnitAssignment::where('unit_id', $request->unit_id)
            ->where('semester_id', $request->semester_id)
            ->where('class_id', $request->class_id)  // Include class_id
            ->where('lecturer_code', $request->lecturer_code)
            ->first();

        if ($existingAssignment) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'This lecturer is already assigned to this unit for the selected class and semester.');
        }

        // Check if unit already has a different lecturer assigned for this class and semester
        $unitHasLecturer = UnitAssignment::where('unit_id', $request->unit_id)
            ->where('semester_id', $request->semester_id)
            ->where('class_id', $request->class_id)  // Include class_id
            ->whereNotNull('lecturer_code')
            ->where('lecturer_code', '!=', '')
            ->where('lecturer_code', '!=', $request->lecturer_code)
            ->first();

        if ($unitHasLecturer) {
            DB::rollBack();
            $existingLecturer = User::where('code', $unitHasLecturer->lecturer_code)->first();
            $existingLecturerName = $existingLecturer ? 
                "{$existingLecturer->first_name} {$existingLecturer->last_name}" : 
                $unitHasLecturer->lecturer_code;
                
            return redirect()->back()
                ->with('error', "This unit already has a lecturer assigned ({$existingLecturerName}) for the selected class and semester.");
        }

        // Update or create unit assignment with lecturer - INCLUDE CLASS_ID
        UnitAssignment::updateOrCreate(
            [
                'unit_id' => $request->unit_id,
                'semester_id' => $request->semester_id,
                'class_id' => $request->class_id  // Add this line
            ],
            [
                'lecturer_code' => $request->lecturer_code,
                'updated_at' => now()
            ]
        );

        DB::commit();

        return redirect()->back()->with('success', 
            "Lecturer {$lecturer->first_name} {$lecturer->last_name} ({$lecturer->code}) assigned to {$unit->code} - {$unit->name} for {$class->name} in {$semester->name}."
        );

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error assigning lecturer: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Error assigning lecturer. Please try again.');
    }
}

    public function update(Request $request, $unitId, $semesterId)
    {
        $validator = Validator::make($request->all(), [
            'lecturer_code' => 'required|string'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Please provide a valid lecturer code.');
        }

        try {
            DB::beginTransaction();

            // Find lecturer by code with school information
            $lecturer = User::with(['school', 'program'])
                ->where('code', $request->lecturer_code)
                ->role('Lecturer')
                ->first();

            if (!$lecturer) {
                return redirect()->back()
                    ->with('error', 'Lecturer with code "' . $request->lecturer_code . '" not found.');
            }

            // Find the unit assignment
            $assignment = UnitAssignment::with([
                'unit.school',
                'unit.program',
                'semester'
            ])->where('unit_id', $unitId)
              ->where('semester_id', $semesterId)
              ->first();

            if (!$assignment) {
                return redirect()->back()
                    ->with('error', 'Unit assignment not found.');
            }

            // Check if trying to assign the same lecturer (no change needed)
            if ($assignment->lecturer_code === $request->lecturer_code) {
                return redirect()->back()
                    ->with('error', 'This lecturer is already assigned to this unit.');
            }

            // Get old lecturer info for the success message
            $oldLecturer = null;
            if ($assignment->lecturer_code) {
                $oldLecturer = User::where('code', $assignment->lecturer_code)->first();
            }

            // Update the assignment
            $assignment->update([
                'lecturer_code' => $request->lecturer_code,
                'updated_at' => now()
            ]);

            DB::commit();

            $unit = $assignment->unit;
            $semester = $assignment->semester;

            $message = "Lecturer assignment updated for {$unit->code} - {$unit->name} in {$semester->name}. ";
            if ($oldLecturer) {
                $message .= "Changed from {$oldLecturer->first_name} {$oldLecturer->last_name} to ";
            } else {
                $message .= "Assigned ";
            }
            $message .= "{$lecturer->first_name} {$lecturer->last_name} ({$lecturer->code}).";

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating lecturer assignment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update lecturer assignment.');
        }
    }


public function destroy($unitId, $semesterId)
{
    try {
        DB::beginTransaction();

        $assignment = UnitAssignment::with([
            'unit.school',
            'unit.program',
            'semester',
            'lecturer' // Make sure this relationship exists in your UnitAssignment model
        ])->where('unit_id', $unitId)
          ->where('semester_id', $semesterId)
          ->first();

        if (!$assignment) {
            return redirect()->back()
                ->with('error', 'Unit assignment not found.');
        }

        if (!$assignment->lecturer_code) {
            return redirect()->back()
                ->with('error', 'No lecturer is currently assigned to this unit.');
        }

        // Get lecturer info for success message
        $lecturer = User::where('code', $assignment->lecturer_code)->first();
        $lecturerName = $lecturer ? 
            "{$lecturer->first_name} {$lecturer->last_name}" : 
            $assignment->lecturer_code;

        // Remove lecturer assignment (but keep the unit assignment record)
        $assignment->update([
            'lecturer_code' => null,
            'updated_at' => now()
        ]);

        DB::commit();

        $unit = $assignment->unit;
        $semester = $assignment->semester;

        return redirect()->back()->with('success', 
            "Lecturer assignment removed. {$lecturerName} is no longer assigned to {$unit->code} - {$unit->name} for {$semester->name}."
        );

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error removing lecturer assignment: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Failed to remove lecturer assignment.');
    }
}


    public function assign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignment_ids' => 'required|array|min:1',
            'assignment_ids.*' => 'exists:unit_assignments,id',
            'lecturer_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Please check the form for errors.');
        }

        try {
            DB::beginTransaction();

            // Find lecturer by code
            $lecturer = User::where('code', $request->lecturer_code)
                ->role('Lecturer')
                ->first();

            if (!$lecturer) {
                return redirect()->back()
                    ->with('error', 'Lecturer with code "' . $request->lecturer_code . '" not found.');
            }

            $assignedCount = 0;
            $skippedCount = 0;
            $skippedUnits = [];

            foreach ($request->assignment_ids as $assignmentId) {
                $assignment = UnitAssignment::find($assignmentId);
                
                if (!$assignment) {
                    $skippedCount++;
                    continue;
                }

                // Check if already assigned to someone
                if ($assignment->lecturer_code && $assignment->lecturer_code !== $request->lecturer_code) {
                    $skippedCount++;
                    $skippedUnits[] = $assignment->unit->code . ' (already assigned)';
                    continue;
                }

                // Check lecturer's workload (optional - you can set max units per lecturer)
                $currentWorkload = UnitAssignment::where('lecturer_code', $request->lecturer_code)
                    ->where('semester_id', $assignment->semester_id)
                    ->count();

                $maxWorkload = 10; // You can make this configurable
                if ($currentWorkload >= $maxWorkload) {
                    $skippedCount++;
                    $skippedUnits[] = $assignment->unit->code . ' (lecturer workload exceeded)';
                    continue;
                }

                // Assign lecturer
                $assignment->update([
                    'lecturer_code' => $request->lecturer_code,
                    'assigned_at' => now()
                ]);

                $assignedCount++;
            }

            DB::commit();

            if ($assignedCount === 0) {
                return redirect()->back()
                    ->with('error', 'No assignments were made. ' . implode(', ', $skippedUnits));
            }

            $message = "{$assignedCount} unit(s) assigned to {$lecturer->first_name} {$lecturer->last_name}.";
            if ($skippedCount > 0) {
                $message .= " {$skippedCount} units were skipped: " . implode(', ', $skippedUnits);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error assigning lecturer: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error assigning lecturer. Please try again.');
        }
    }

    public function unassign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'assignment_ids' => 'required|array|min:1',
            'assignment_ids.*' => 'exists:unit_assignments,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Please select valid assignments.');
        }

        try {
            DB::beginTransaction();

            $unassignedCount = 0;

            foreach ($request->assignment_ids as $assignmentId) {
                $assignment = UnitAssignment::find($assignmentId);
                
                if ($assignment && $assignment->lecturer_code) {
                    $assignment->update([
                        'lecturer_code' => null,
                        'assigned_at' => null
                    ]);
                    $unassignedCount++;
                }
            }

            DB::commit();

            if ($unassignedCount === 0) {
                return redirect()->back()
                    ->with('error', 'No lecturers were unassigned. Selected units may not have been assigned.');
            }

            return redirect()->back()
                ->with('success', "{$unassignedCount} unit(s) unassigned successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error unassigning lecturer: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error unassigning lecturer. Please try again.');
        }
    }

    public function bulkAssign(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'semester_id' => 'required|exists:semesters,id',
            'assignments' => 'required|array|min:1',
            'assignments.*.assignment_id' => 'required|exists:unit_assignments,id',
            'assignments.*.lecturer_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Please check the form for errors.');
        }

        try {
            DB::beginTransaction();

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($request->assignments as $assignmentData) {
                $assignment = UnitAssignment::find($assignmentData['assignment_id']);
                $lecturer = User::where('code', $assignmentData['lecturer_code'])
                    ->role('Lecturer')
                    ->first();

                if (!$assignment || !$lecturer) {
                    $errorCount++;
                    $errors[] = "Invalid assignment or lecturer code";
                    continue;
                }

                // Check if already assigned
                if ($assignment->lecturer_code) {
                    $errorCount++;
                    $errors[] = "{$assignment->unit->code} already assigned";
                    continue;
                }

                $assignment->update([
                    'lecturer_code' => $assignmentData['lecturer_code'],
                    'assigned_at' => now()
                ]);

                $successCount++;
            }

            DB::commit();

            $message = "{$successCount} assignments completed successfully.";
            if ($errorCount > 0) {
                $message .= " {$errorCount} failed: " . implode(', ', array_slice($errors, 0, 3));
                if (count($errors) > 3) {
                    $message .= " and " . (count($errors) - 3) . " more.";
                }
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in bulk assignment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error in bulk assignment. Please try again.');
        }
    }

    // Get available lecturers for a specific unit (considering their expertise/school/program)
    public function getAvailableLecturers(Request $request)
    {
        $request->validate([
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        try {
            $unit = Unit::find($request->unit_id);
            
            // Get lecturers who can teach this unit (same school or qualified)
            $lecturers = User::with(['school', 'program'])
                ->role('Lecturer')
                ->where(function($query) use ($unit) {
                    $query->where('school_id', $unit->school_id)
                          ->orWhere('program_id', $unit->program_id);
                })
                ->get()
                ->map(function($lecturer) use ($request) {
                    // Calculate current workload
                    $workload = UnitAssignment::where('lecturer_code', $lecturer->code)
                        ->where('semester_id', $request->semester_id)
                        ->count();
                    
                    return [
                        'code' => $lecturer->code,
                        'name' => $lecturer->first_name . ' ' . $lecturer->last_name,
                        'email' => $lecturer->email,
                        'school' => $lecturer->school->name ?? '',
                        'program' => $lecturer->program->name ?? '',
                        'current_workload' => $workload,
                        'is_available' => $workload < 10 // Max workload threshold
                    ];
                });

            return response()->json($lecturers);
        } catch (\Exception $e) {
            Log::error('Error fetching available lecturers: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch lecturers'], 500);
        }
    }

    // Get lecturer's current assignments
    public function getLecturerWorkload(Request $request)
    {
        $request->validate([
            'lecturer_code' => 'required|string',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        try {
            $assignments = UnitAssignment::with(['unit', 'class'])
                ->where('lecturer_code', $request->lecturer_code)
                ->where('semester_id', $request->semester_id)
                ->get();

            $workload = [
                'total_units' => $assignments->count(),
                'total_credit_hours' => $assignments->sum(function($assignment) {
                    return $assignment->unit->credit_hours;
                }),
                'assignments' => $assignments->map(function($assignment) {
                    return [
                        'unit_code' => $assignment->unit->code,
                        'unit_name' => $assignment->unit->name,
                        'class_name' => $assignment->class->name . ' Section ' . $assignment->class->section,
                        'credit_hours' => $assignment->unit->credit_hours,
                    ];
                })
            ];

            return response()->json($workload);
        } catch (\Exception $e) {
            Log::error('Error fetching lecturer workload: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch workload'], 500);
        }
    }

 
    public function assignElectives(Request $request)
{
    Log::info('🚀 Elective assignment request:', $request->all());

    $validated = $request->validate([
        'lecturer_code' => 'required|exists:users,code',
        'semester_id' => 'required|exists:semesters,id',
        'elective_ids' => 'required|array|min:1',
        'elective_ids.*' => 'required|exists:units,id',
    ]);

    // Verify the user has lecturer role
    $lecturer = User::where('code', $validated['lecturer_code'])
        ->whereHas('roles', function($query) {
            $query->where('name', 'Lecturer');
        })
        ->first();

    if (!$lecturer) {
        Log::error('❌ Lecturer not found:', ['code' => $validated['lecturer_code']]);
        return redirect()->back()->withErrors(['error' => 'User is not a lecturer']);
    }

    DB::beginTransaction();
    try {
        $assignedCount = 0;
        $errors = [];
        
        foreach ($validated['elective_ids'] as $unitId) {
            // Get the unit
            $unit = Unit::find($unitId);
            
            if (!$unit) {
                Log::warning('⚠️ Unit not found:', ['unit_id' => $unitId]);
                $errors[] = "Unit ID {$unitId} not found";
                continue;
            }

            Log::info('📚 Processing unit:', [
                'unit_id' => $unitId,
                'unit_code' => $unit->code,
                'unit_name' => $unit->name
            ]);

            // ✅ Get the elective record to determine category
            $elective = DB::table('electives')
                ->where('unit_id', $unitId)
                ->where('is_active', 1)
                ->first();
            
            if (!$elective) {
                Log::warning('⚠️ Unit is not an elective:', ['unit_id' => $unitId, 'code' => $unit->code]);
                $errors[] = "Unit {$unit->code} is not configured as an elective";
                continue;
            }
            
            // ✅ Determine which elective class name based on category
            $className = ($elective->category === 'language') ? 'Languages' : 'Others';
            
            Log::info('🔍 Looking for elective class:', [
                'unit_id' => $unitId,
                'unit_code' => $unit->code,
                'category' => $elective->category,
                'class_name' => $className,
                'semester_id' => $validated['semester_id']
            ]);
            
            // ✅ Find the elective class by name and semester
            $electiveClass = ClassModel::where('name', $className)
                ->where('semester_id', $validated['semester_id'])
                ->where('is_elective_class', 1)
                ->first();
            
            if (!$electiveClass) {
                Log::error('❌ Elective class not found:', [
                    'class_name' => $className,
                    'semester_id' => $validated['semester_id']
                ]);
                $errors[] = "Elective class '{$className}' not found for semester {$validated['semester_id']}";
                continue;
            }

            Log::info('✅ Found elective class:', [
                'class_id' => $electiveClass->id,
                'class_name' => $electiveClass->name,
                'section' => $electiveClass->section,
                'program_id' => $electiveClass->program_id
            ]);
            
            // ✅ Check if unit_assignment already exists for this unit+class+semester
            $existingAssignment = UnitAssignment::where('unit_id', $unitId)
                ->where('class_id', $electiveClass->id)
                ->where('semester_id', $validated['semester_id'])
                ->first();

            if ($existingAssignment) {
                Log::info('📝 Found existing assignment, updating lecturer:', [
                    'assignment_id' => $existingAssignment->id,
                    'old_lecturer_code' => $existingAssignment->lecturer_code,
                    'new_lecturer_code' => $validated['lecturer_code']
                ]);
                
                // Update the existing assignment
                $existingAssignment->update([
                    'lecturer_code' => $validated['lecturer_code'],
                    'is_active' => 1,
                ]);
                
                $assignment = $existingAssignment;
            } else {
                Log::info('✨ Creating new assignment:', [
                    'unit_id' => $unitId,
                    'class_id' => $electiveClass->id,
                    'semester_id' => $validated['semester_id'],
                    'lecturer_code' => $validated['lecturer_code']
                ]);
                
                // Create new assignment
                $assignment = UnitAssignment::create([
                    'unit_id' => $unitId,
                    'semester_id' => $validated['semester_id'],
                    'class_id' => $electiveClass->id,
                    'lecturer_code' => $validated['lecturer_code'],
                    'is_active' => 1,
                ]);
            }

            // ✅ Verify the assignment was saved correctly
            $assignment->refresh();
            
            Log::info('✅ Assignment saved successfully:', [
                'assignment_id' => $assignment->id,
                'unit_id' => $assignment->unit_id,
                'unit_code' => $unit->code,
                'class_id' => $assignment->class_id,
                'class_name' => $electiveClass->name,
                'semester_id' => $assignment->semester_id,
                'lecturer_code' => $assignment->lecturer_code,
                'is_active' => $assignment->is_active
            ]);
            
            // ✅ Double-check the database
            $verifyAssignment = UnitAssignment::where('unit_id', $unitId)
                ->where('class_id', $electiveClass->id)
                ->where('semester_id', $validated['semester_id'])
                ->first();
                
            if ($verifyAssignment && $verifyAssignment->lecturer_code === $validated['lecturer_code']) {
                Log::info('✅ VERIFIED: Lecturer code saved in database');
                $assignedCount++;
            } else {
                Log::error('❌ VERIFICATION FAILED: Lecturer code not in database', [
                    'expected' => $validated['lecturer_code'],
                    'actual' => $verifyAssignment->lecturer_code ?? 'NULL'
                ]);
                $errors[] = "Failed to verify assignment for unit {$unit->code}";
            }
        }
        
        if ($assignedCount === 0) {
            $errorMessage = 'No electives were assigned.';
            if (!empty($errors)) {
                $errorMessage .= ' Errors: ' . implode('; ', $errors);
            }
            throw new \Exception($errorMessage);
        }
        
        DB::commit();
        
        $successMessage = "✅ {$assignedCount} elective(s) assigned to {$lecturer->first_name} {$lecturer->last_name} successfully!";
        if (!empty($errors)) {
            $successMessage .= ' (with ' . count($errors) . ' warnings)';
        }
        
        Log::info('🎉 Elective assignment completed successfully:', [
            'count' => $assignedCount,
            'lecturer_code' => $validated['lecturer_code'],
            'lecturer_name' => "{$lecturer->first_name} {$lecturer->last_name}",
            'errors_count' => count($errors)
        ]);
        
        return redirect()->back()->with('success', $successMessage);
        
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('❌ Elective assignment error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'validated' => $validated
        ]);
        return redirect()->back()->withErrors(['error' => 'Failed to assign electives: ' . $e->getMessage()]);
    }
}
/**
 * Get or create the university-wide elective class for a semester
 */
private function getUniversityElectiveClass($semesterId)
{
    // Get the University-Wide school
    $universitySchool = School::where('code', 'UNI')->first();
    
    if (!$universitySchool) {
        // Create it if it doesn't exist
        $universitySchool = School::create([
            'code' => 'UNI',
            'name' => 'University-Wide Programs',
            'description' => 'Programs and units available across all schools',
            'is_active' => true,
        ]);
    }

    // Get or create the Electives program
    $electiveProgram = Program::firstOrCreate(
        [
            'school_id' => $universitySchool->id,
            'code' => 'ELECTIVES',
        ],
        [
            'name' => 'University Electives',
            'description' => 'Elective courses available to all students across all programs',
            'duration' => 0,
            'is_active' => true,
        ]
    );

    // Get or create the elective class for this semester
    $electiveClass = ClassModel::firstOrCreate(
        [
            'program_id' => $electiveProgram->id,
            'semester_id' => $semesterId,
            'is_elective_class' => true,
        ],
        [
            'name' => 'University Electives',
            'section' => 'ALL',
            'year_level' => 0,
            'capacity' => 99999, // Unlimited capacity
            'is_active' => true,
            'students_count' => 0,
        ]
    );

    return $electiveClass;
}
}