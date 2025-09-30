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

        // Paginate results
        $enrollments = $query->paginate(5)->withQueryString();
        
        $students = User::with(['school', 'program'])
            ->role('Student')
            ->orderByRaw("CONCAT(first_name, ' ', last_name)")
            ->get();

        $lecturers = User::role('Lecturer')
            ->select(['id', 'code', 'first_name', 'last_name', 'email', 'schools'])
            ->orderByRaw("CONCAT(first_name, ' ', last_name)")
            ->get();

        // Fetch lecturer assignments
        $lecturerAssignments = UnitAssignment::with([
            'unit.school',
            'unit.program', 
            'class.program.school',
            'semester'
        ])
        ->whereNotNull('lecturer_code')
        ->whereNotNull('class_id')
        ->orderBy('created_at', 'desc')
        ->take(20)
        ->get()
        ->map(function ($assignment) {
            $lecturer = User::where('code', $assignment->lecturer_code)->first();
            $assignment->lecturer = $lecturer;
            return $assignment;
        });

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
        
        // Calculate statistics
        $allEnrollments = Enrollment::all();
        $stats = [
            'total' => $allEnrollments->pluck('student_code')->unique()->count(),
            'active' => $allEnrollments->where('status', 'enrolled')->pluck('student_code')->unique()->count(),
            'dropped' => $allEnrollments->where('status', 'dropped')->pluck('student_code')->unique()->count(),
            'completed' => $allEnrollments->where('status', 'completed')->pluck('student_code')->unique()->count(),
            'total_enrollments' => $allEnrollments->count(),
            'active_enrollments' => $allEnrollments->where('status', 'enrolled')->count(),
        ];
        
        return Inertia::render('Schools/SCES/Programs/Enrollments/Index', [
            'enrollments' => $enrollments,
            'students' => $students,
            'lecturers' => $lecturers,
            'lecturerAssignments' => $lecturerAssignments,
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

            $student = User::with(['school', 'program'])->where('code', $request->student_code)
                ->role('Student')
                ->first();

            if (!$student) {
                return redirect()->back()
                    ->with('error', 'Student with code "' . $request->student_code . '" not found.');
            }

            $class = ClassModel::with(['program.school'])->find($request->class_id);
            $semester = Semester::find($request->semester_id);
            
            $currentEnrollments = Enrollment::where('class_id', $request->class_id)
                ->where('semester_id', $request->semester_id)
                ->where('status', 'enrolled')
                ->distinct('student_code')
                ->count();

            if ($currentEnrollments >= $class->capacity) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', "Cannot enroll student. Class \"{$class->name} Section {$class->section}\" has reached its capacity of {$class->capacity} students. Currently enrolled: {$currentEnrollments} students.");
            }

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

                $unit = Unit::find($unitId);
                $schoolId = $student->school_id ?? $class->program->school_id ?? $unit->school_id;

                Enrollment::create([
                    'student_code' => $request->student_code,
                    'lecturer_code' => $unitAssignment->lecturer_code ?? '',
                    'group_id' => '',
                    'unit_id' => $unitId,
                    'class_id' => $request->class_id,
                    'semester_id' => $request->semester_id,
                    'program_id' => $class->program_id,
                    'school_id' => $schoolId,
                    'status' => $request->status,
                    'enrollment_date' => now()
                ]);

                $createdCount++;
            }

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

            $newTotalEnrollments = $currentEnrollments + 1;
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
            
            $this->updateClassStudentCount($classId, $semesterId);
            
            DB::commit();
            return redirect()->back()->with('success', 'Enrollment deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting enrollment: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete enrollment.');
        }
    }

    private function updateClassStudentCount($classId, $semesterId)
    {
        try {
            $studentCount = Enrollment::where('class_id', $classId)
                ->where('semester_id', $semesterId)
                ->where('status', 'enrolled')
                ->distinct('student_code')
                ->count();

            ClassModel::where('id', $classId)
                ->update(['students_count' => $studentCount]);
                
            Log::info("Updated class {$classId} student count to {$studentCount}");
            
        } catch (\Exception $e) {
            Log::error('Error updating class student count: ' . $e->getMessage());
        }
    }

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
                      ->where('is_active', true);
            })
            ->with(['school', 'program'])
            ->get();

            return response()->json($units);
        } catch (\Exception $e) {
            Log::error('Error fetching units for class: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch units'], 500);
        }
    }

    public function getClassCapacityInfo(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        try {
            $class = ClassModel::find($request->class_id);
            
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

    public function refreshAllClassCounts()
    {
        try {
            $classes = ClassModel::all();
            
            foreach ($classes as $class) {
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

    public function programEnrollments(Program $program, Request $request, $schoolCode)
    {
        $school = School::where('code', $schoolCode)->firstOrFail();
        
        if ($program->school_id !== $school->id) {
            abort(403, 'Program does not belong to this school');
        }

        $query = Enrollment::with([
            'student.school',
            'student.program',
            'unit.school',
            'unit.program',
            'semester',
            'class',
            'program',
            'school'
        ])->where('program_id', $program->id);

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

        $query->orderBy('created_at', 'desc');
        $enrollments = $query->paginate(5)->withQueryString();
        
        $students = User::with(['school', 'program'])
            ->where('programs', $program->id)
            ->role('Student')
            ->orderByRaw("CONCAT(first_name, ' ', last_name)")
            ->get();

        $lecturers = User::role('Lecturer')
            ->select(['id', 'code', 'first_name', 'last_name', 'email', 'schools'])
            ->orderByRaw("CONCAT(first_name, ' ', last_name)")
            ->get();

        $lecturerAssignments = UnitAssignment::with([
            'unit.school',
            'unit.program', 
            'class.program.school',
            'semester'
        ])
        ->whereHas('unit', function($q) use ($program) {
            $q->where('program_id', $program->id);
        })
        ->whereNotNull('lecturer_code')
        ->whereNotNull('class_id')
        ->orderBy('created_at', 'desc')
        ->take(20)
        ->get()
        ->map(function ($assignment) {
            $lecturer = User::where('code', $assignment->lecturer_code)->first();
            $assignment->lecturer = $lecturer;
            return $assignment;
        });

        $units = Unit::with(['school', 'program'])
            ->where('program_id', $program->id)
            ->whereHas('assignments')
            ->where('is_active', true)
            ->get();
            
        $schools = School::orderBy('name')->get();
        $programs = Program::where('school_id', $school->id)->orderBy('name')->get();
        $semesters = Semester::orderBy('name')->get();
        $classes = ClassModel::with(['program', 'semester'])
            ->where('program_id', $program->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('section')
            ->get();
        
        $allEnrollments = Enrollment::where('program_id', $program->id)->get();
        $stats = [
            'total' => $allEnrollments->pluck('student_code')->unique()->count(),
            'active' => $allEnrollments->where('status', 'enrolled')->pluck('student_code')->unique()->count(),
            'dropped' => $allEnrollments->where('status', 'dropped')->pluck('student_code')->unique()->count(),
            'completed' => $allEnrollments->where('status', 'completed')->pluck('student_code')->unique()->count(),
            'total_enrollments' => $allEnrollments->count(),
            'active_enrollments' => $allEnrollments->where('status', 'enrolled')->count(),
        ];

        return Inertia::render('Schools/SCES/Programs/Enrollments/Index', [
            'program' => $program->load('school'),
            'schoolCode' => $schoolCode,
            'enrollments' => $enrollments,
            'students' => $students,
            'lecturers' => $lecturers,
            'lecturerAssignments' => $lecturerAssignments,
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
}