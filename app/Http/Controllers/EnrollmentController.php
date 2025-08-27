<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Unit;
use App\Models\School;
use App\Models\Program;
use App\Models\Semester;
use App\Models\ClassModel;
use App\Models\User;
use App\Models\UnitAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EnrollmentController extends Controller
{
  public function index()
{
    $enrollments = Enrollment::with([
        'student.school',
        'student.program',
        'unit.school',
        'unit.program',
        'semester',
        'class',
        'program',
        'school'
    ])->get();
    
    $students = User::with(['school', 'program'])
        ->role('Student')
        ->orderByRaw("CONCAT(first_name, ' ', last_name)")
        ->get();
    
    // Get units that are assigned to classes
    $units = Unit::with(['school', 'program'])
        ->whereHas('assignments')
        ->where('is_active', true)
        ->get();
        
    $schools = School::orderBy('name')->get();
    $programs = Program::with('school')->orderBy('name')->get();
    $semesters = Semester::orderBy('name')->get();
    $classes = ClassModel::with(['program', 'semester'])
        ->where('is_active', true)
        ->orderBy('name')
        ->orderBy('section')
        ->get();
    
    $stats = [
        'total' => $enrollments->count(),
        'active' => $enrollments->where('status', 'enrolled')->count(),
        'dropped' => $enrollments->where('status', 'dropped')->count(),
        'completed' => $enrollments->where('status', 'completed')->count(),
    ];
    
    return Inertia::render('Admin/Enrollments/Index', [
        'enrollments' => $enrollments,
        'students' => $students,
        'units' => $units,
        'schools' => $schools,
        'programs' => $programs,
        'semesters' => $semesters,
        'classes' => $classes,
        'stats' => $stats,
        'can' => [
            'create' => auth()->user()->can('create-enrollments'),
            'update' => auth()->user()->can('edit-enrollments'),
            'delete' => auth()->user()->can('delete-enrollments'),
        ]
    ]);
}

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_code' => 'required|string',
            'class_id' => 'required|exists:classes,id',
            'semester_id' => 'required|exists:semesters,id',
            'unit_ids' => 'required|array|min:1',
            'unit_ids.*' => 'exists:units,id',
            'status' => 'required|in:enrolled,dropped,completed'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Please check the form for errors.');
        }

        try {
            DB::beginTransaction();

            // Find student by code
            $student = User::where('code', $request->student_code)
                ->role('Student')
                ->first();

            if (!$student) {
                return redirect()->back()
                    ->with('error', 'Student with code "' . $request->student_code . '" not found.');
            }

            $class = ClassModel::with('program')->find($request->class_id);
            $semester = Semester::find($request->semester_id);
            
            $createdCount = 0;
            $skippedCount = 0;
            $skippedUnits = [];

            foreach ($request->unit_ids as $unitId) {
                // Check if student is already enrolled in this unit for this semester
                // Updated to use student_code instead of student_id
                $existingEnrollment = Enrollment::where('student_code', $request->student_code)
                    ->where('unit_id', $unitId)
                    ->where('semester_id', $request->semester_id)
                    ->first();

                if ($existingEnrollment) {
                    $skippedCount++;
                    $unit = Unit::find($unitId);
                    $skippedUnits[] = $unit->name;
                    continue;
                }

                // Verify unit is assigned to this class
                $unitAssignment = UnitAssignment::where('unit_id', $unitId)
                    ->where('class_id', $request->class_id)
                    ->where('semester_id', $request->semester_id)
                    ->first();

                if (!$unitAssignment) {
                    $skippedCount++;
                    $unit = Unit::find($unitId);
                    $skippedUnits[] = $unit->name . ' (not assigned to this class)';
                    continue;
                }

                // Get unit details for additional fields
                $unit = Unit::find($unitId);

                // Create enrollment with correct database schema
                Enrollment::create([
                    'student_code' => $request->student_code,
                    'lecturer_code' => '', // You'll need to determine how to get this
                    'group_id' => '', // You'll need to determine how to get this  
                    'unit_id' => $unitId,
                    'class_id' => $request->class_id,
                    'semester_id' => $request->semester_id,
                    'program_id' => $class->program_id,
                    'school_id' => $unit->school_id,
                    'status' => $request->status,
                    'enrollment_date' => now()
                ]);

                $createdCount++;
            }

            DB::commit();

            $message = "{$createdCount} enrollments created for {$student->first_name} in {$class->name} Section {$class->section}.";
            if ($skippedCount > 0) {
                $message .= " {$skippedCount} units were skipped: " . implode(', ', $skippedUnits);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating enrollment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error creating enrollment. Please try again.');
        }
    }

    // Add method to get units for a specific class
    public function getUnitsForClass(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        try {
            $units = Unit::whereHas('assignments', function($query) use ($request) {
                $query->where('class_id', $request->class_id)
                      ->where('semester_id', $request->semester_id)
                      ->where('is_active', true); // Only check if assignment is active
            })
            ->with(['school', 'program'])
            ->get();

            return response()->json($units);
        } catch (\Exception $e) {
            Log::error('Error fetching units for class: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch units'], 500);
        }
    }
}