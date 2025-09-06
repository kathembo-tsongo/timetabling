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
    public function index(Request $request)
    {
        // Start with base query
        $query = Enrollment::with([
            'student.school',
            'student.program',
            'unit.school',
            'unit.program',
            'semester',
            'class',
            'program',
            'school'
        ]);

        // Apply filters if provided
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('student_code', 'like', '%' . $search . '%')
                  ->orWhereHas('unit', function($unitQuery) use ($search) {
                      $unitQuery->where('code', 'like', '%' . $search . '%')
                               ->orWhere('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('class', function($classQuery) use ($search) {
                      $classQuery->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        if ($request->filled('semester_id')) {
            $query->where('semester_id', $request->semester_id);
        }

        if ($request->filled('school_id')) {
            $query->where('school_id', $request->school_id);
        }

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->program_id);
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('student_code')) {
            $query->where('student_code', $request->student_code);
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        // Order by latest enrollments first
        $query->orderBy('created_at', 'desc');

        // Paginate results (15 per page)
        $enrollments = $query->paginate(10)->withQueryString();
        
        $students = User::with(['school', 'program'])
            ->role('Student')
            ->orderByRaw("CONCAT(first_name, ' ', last_name)")
            ->get();

            // ADD THIS: Fetch lecturers from users table
    $lecturers = User::role('Lecturer')
        ->select(['id', 'code', 'first_name', 'last_name', 'email', 'schools'])
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
        
        // Calculate statistics based on ALL enrollments (not just paginated ones)
        $allEnrollments = Enrollment::all();
        $stats = [
            'total' => $allEnrollments->pluck('student_code')->unique()->count(), // Unique students enrolled
            'active' => $allEnrollments->where('status', 'enrolled')->pluck('student_code')->unique()->count(), // Unique active students
            'dropped' => $allEnrollments->where('status', 'dropped')->pluck('student_code')->unique()->count(), // Unique dropped students
            'completed' => $allEnrollments->where('status', 'completed')->pluck('student_code')->unique()->count(), // Unique completed students
            // Optional: Add total enrollment records for reference
            'total_enrollments' => $allEnrollments->count(), // Total enrollment records
            'active_enrollments' => $allEnrollments->where('status', 'enrolled')->count(), // Total active enrollment records
        ];
        
        return Inertia::render('Admin/Enrollments/Index', [
        'enrollments' => $enrollments,
        'students' => $students,
        'lecturers' => $lecturers,  // ADD THIS LINE
        'units' => $units,
        'schools' => $schools,
        'programs' => $programs,
        'semesters' => $semesters,
        'classes' => $classes,
        'stats' => $stats,
        'filters' => $request->only(['search', 'semester_id', 'school_id', 'program_id', 'class_id', 'status', 'student_code', 'unit_id']),
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
            $student = User::with(['school', 'program'])->where('code', $request->student_code)
                ->role('Student')
                ->first();

            if (!$student) {
                return redirect()->back()
                    ->with('error', 'Student with code "' . $request->student_code . '" not found.');
            }

            $class = ClassModel::with(['program.school'])->find($request->class_id);
            $semester = Semester::find($request->semester_id);
            
            // **FIX: CHECK CLASS CAPACITY USING UNIQUE STUDENT COUNT**
            // Count current UNIQUE enrolled students in this class for this semester
            $currentEnrollments = Enrollment::where('class_id', $request->class_id)
                ->where('semester_id', $request->semester_id)
                ->where('status', 'enrolled')
                ->distinct('student_code')
                ->count();

            // Check if adding this student would exceed capacity
            if ($currentEnrollments >= $class->capacity) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', "Cannot enroll student. Class \"{$class->name} Section {$class->section}\" has reached its capacity of {$class->capacity} students. Currently enrolled: {$currentEnrollments} students.");
            }

            // Check if student is already enrolled in this class for this semester
            $existingClassEnrollment = Enrollment::where('student_code', $request->student_code)
                ->where('class_id', $request->class_id)
                ->where('semester_id', $request->semester_id)
                ->exists();

            if ($existingClassEnrollment) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'Student is already enrolled in this class for the selected semester.');
            }
            
            $createdCount = 0;
            $skippedCount = 0;
            $skippedUnits = [];

            foreach ($request->unit_ids as $unitId) {
                // Check if student is already enrolled in this unit for this semester
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

                // **FIXED: PROPER school_id ASSIGNMENT**
                // Priority order: student's school_id > class program's school_id > unit's school_id
                $schoolId = $student->school_id ?? $class->program->school_id ?? $unit->school_id;

                // Create enrollment with correct database schema
                Enrollment::create([
                    'student_code' => $request->student_code,
                    'lecturer_code' => '', // You'll need to determine how to get this
                    'group_id' => '', // You'll need to determine how to get this  
                    'unit_id' => $unitId,
                    'class_id' => $request->class_id,
                    'semester_id' => $request->semester_id,
                    'program_id' => $class->program_id,
                    'school_id' => $schoolId, // Fixed: Now properly assigns school_id
                    'status' => $request->status,
                    'enrollment_date' => now()
                ]);

                $createdCount++;
            }

            // **NEW: UPDATE CLASS STUDENT COUNT**
            $this->updateClassStudentCount($request->class_id, $request->semester_id);

            DB::commit();

            if ($createdCount === 0) {
                return redirect()->back()
                    ->with('error', 'No enrollments were created. All selected units were either already enrolled or not available.');
            }

            $message = "{$createdCount} enrollments created for {$student->first_name} in {$class->name} Section {$class->section}.";
            if ($skippedCount > 0) {
                $message .= " {$skippedCount} units were skipped: " . implode(', ', $skippedUnits);
            }

            // Add capacity warning if getting close to limit
            $newTotalEnrollments = $currentEnrollments + 1; // +1 for this student
            $remainingCapacity = $class->capacity - $newTotalEnrollments;
            
            if ($remainingCapacity <= 2 && $remainingCapacity > 0) {
                $message .= " Warning: Only {$remainingCapacity} spots remaining in this class.";
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating enrollment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error creating enrollment. Please try again.');
        }
    }

    public function update(Request $request, $id)
    {
        $enrollment = Enrollment::find($id);

        if (!$enrollment) {
            return redirect()->back()->with('error', 'Enrollment not found.');
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:enrolled,dropped,completed'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Invalid status provided.');
        }

        try {
            DB::beginTransaction();
            
            $oldStatus = $enrollment->status;
            
            $enrollment->update([
                'status' => $request->status
            ]);

            // **NEW: UPDATE CLASS STUDENT COUNT WHEN STATUS CHANGES**
            // If status changed between enrolled/not-enrolled states, update count
            if (($oldStatus === 'enrolled') !== ($request->status === 'enrolled')) {
                $this->updateClassStudentCount($enrollment->class_id, $enrollment->semester_id);
            }

            DB::commit();
            return redirect()->back()->with('success', 'Enrollment status updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating enrollment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update enrollment.');
        }
    }

    public function destroy($id)
    {
        $enrollment = Enrollment::find($id);

        if (!$enrollment) {
            return redirect()->back()->with('error', 'Enrollment not found.');
        }

        try {
            DB::beginTransaction();
            
            $classId = $enrollment->class_id;
            $semesterId = $enrollment->semester_id;
            
            $enrollment->delete();
            
            // **NEW: UPDATE CLASS STUDENT COUNT AFTER DELETION**
            $this->updateClassStudentCount($classId, $semesterId);
            
            DB::commit();
            return redirect()->back()->with('success', 'Enrollment deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting enrollment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete enrollment.');
        }
    }

    // **NEW: METHOD TO UPDATE CLASS STUDENT COUNT**
    private function updateClassStudentCount($classId, $semesterId)
    {
        try {
            // Count unique enrolled students in this class for this semester
            $studentCount = Enrollment::where('class_id', $classId)
                ->where('semester_id', $semesterId)
                ->where('status', 'enrolled')
                ->distinct('student_code')
                ->count();

            // Update the class record
            ClassModel::where('id', $classId)
                ->update(['students_count' => $studentCount]);
                
            Log::info("Updated class {$classId} student count to {$studentCount}");
            
        } catch (\Exception $e) {
            Log::error('Error updating class student count: ' . $e->getMessage());
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

    // **UPDATED: Get class capacity info with CORRECT student count**
    public function getClassCapacityInfo(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        try {
            $class = ClassModel::find($request->class_id);
            
            // **FIXED: Count unique enrolled students correctly**
            $currentEnrollments = Enrollment::where('class_id', $request->class_id)
                ->where('semester_id', $request->semester_id)
                ->where('status', 'enrolled')
                ->distinct('student_code')
                ->count();

            return response()->json([
                'capacity' => $class->capacity,
                'current_enrollments' => $currentEnrollments,
                'available_spots' => $class->capacity - $currentEnrollments,
                'is_full' => $currentEnrollments >= $class->capacity
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching class capacity info: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch capacity info'], 500);
        }
    }

    // **NEW: Method to refresh all class student counts (useful for fixing existing data)**
    public function refreshAllClassCounts()
    {
        try {
            $classes = ClassModel::all();
            
            foreach ($classes as $class) {
                // Get the most recent semester for this class
                $latestSemester = Enrollment::where('class_id', $class->id)
                    ->orderBy('created_at', 'desc')
                    ->value('semester_id');
                
                if ($latestSemester) {
                    $this->updateClassStudentCount($class->id, $latestSemester);
                }
            }
            
            return response()->json(['message' => 'All class student counts refreshed successfully']);
        } catch (\Exception $e) {
            Log::error('Error refreshing class counts: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to refresh counts'], 500);
        }
    }
}