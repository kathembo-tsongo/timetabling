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
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LecturerAssignmentsExport;

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

        // ✅ NEW: Fetch lecturer assignments with PAGINATION
        $lecturerAssignments = $this->getLecturerAssignments($request);

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
                'assign_lecturer' => auth()->user()->can('view-lecturer-assignments') || 
                                    auth()->user()->can('create-lecturer-assignments'),
                'download_lecturer_assignments' => auth()->user()->can('view-lecturer-assignments'),
            ]
        ]);
    }

    /**
     * ✅ NEW METHOD: Get lecturer assignments with independent pagination
     */
    private function getLecturerAssignments(Request $request)
    {
        $query = UnitAssignment::with([
            'unit.school',
            'unit.program', 
            'class.program.school',
            'semester'
        ])
        ->whereNotNull('lecturer_code')
        ->whereNotNull('class_id');

        // Apply filters for lecturer assignments
        if ($request->filled('lecturer_search')) {
            $search = $request->lecturer_search;
            $query->where(function($q) use ($search) {
                $q->whereHas('unit', function($unitQuery) use ($search) {
                    $unitQuery->where('code', 'like', '%' . $search . '%')
                             ->orWhere('name', 'like', '%' . $search . '%');
                })
                ->orWhere('lecturer_code', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('lecturer_semester_id')) {
            $query->where('semester_id', $request->lecturer_semester_id);
        }

        if ($request->filled('lecturer_program_id')) {
            $query->whereHas('unit', function($q) use ($request) {
                $q->where('program_id', $request->lecturer_program_id);
            });
        }

        if ($request->filled('lecturer_class_id')) {
            $query->where('class_id', $request->lecturer_class_id);
        }

        if ($request->filled('lecturer_code_filter')) {
            $query->where('lecturer_code', $request->lecturer_code_filter);
        }

        $query->orderBy('created_at', 'desc');

        // Use lecturer_page parameter for independent pagination
        $page = $request->input('lecturer_page', 1);
        $perPage = $request->input('lecturer_per_page', 10);
        
        // Paginate with custom page parameter
        $lecturerAssignments = $query->paginate($perPage, ['*'], 'lecturer_page', $page);
        
        // Add lecturer information to each assignment
        $lecturerAssignments->getCollection()->transform(function ($assignment) {
            $lecturer = User::where('code', $assignment->lecturer_code)->first();
            $assignment->lecturer = $lecturer;
            return $assignment;
        });

        // Set path and append query parameters
        $lecturerAssignments->withPath($request->url());
        $lecturerAssignments->appends($request->except('lecturer_page'));

        return $lecturerAssignments;
    }

    /**
     * ✅ NEW METHOD: Download lecturer assignments as CSV/Excel
     */
    public function downloadLecturerAssignments(Request $request)
    {
        try {
            $query = UnitAssignment::with([
                'unit.school',
                'unit.program', 
                'class.program.school',
                'semester'
            ])
            ->whereNotNull('lecturer_code')
            ->whereNotNull('class_id');

            // Apply same filters as the view
            if ($request->filled('lecturer_search')) {
                $search = $request->lecturer_search;
                $query->where(function($q) use ($search) {
                    $q->whereHas('unit', function($unitQuery) use ($search) {
                        $unitQuery->where('code', 'like', '%' . $search . '%')
                                 ->orWhere('name', 'like', '%' . $search . '%');
                    })
                    ->orWhere('lecturer_code', 'like', '%' . $search . '%');
                });
            }

            if ($request->filled('lecturer_semester_id')) {
                $query->where('semester_id', $request->lecturer_semester_id);
            }

            if ($request->filled('lecturer_program_id')) {
                $query->whereHas('unit', function($q) use ($request) {
                    $q->where('program_id', $request->lecturer_program_id);
                });
            }

            if ($request->filled('lecturer_class_id')) {
                $query->where('class_id', $request->lecturer_class_id);
            }

            if ($request->filled('lecturer_code_filter')) {
                $query->where('lecturer_code', $request->lecturer_code_filter);
            }

            $query->orderBy('created_at', 'desc');

            $assignments = $query->get()->map(function ($assignment) {
                $lecturer = User::where('code', $assignment->lecturer_code)->first();
                
                return [
                    'Lecturer Code' => $assignment->lecturer_code,
                    'Lecturer Name' => $lecturer ? "{$lecturer->first_name} {$lecturer->last_name}" : 'N/A',
                    'Unit Code' => $assignment->unit->code ?? 'N/A',
                    'Unit Name' => $assignment->unit->name ?? 'N/A',
                    'Credit Hours' => $assignment->unit->credit_hours ?? 'N/A',
                    'Class' => $assignment->class ? "{$assignment->class->name} Section {$assignment->class->section}" : 'N/A',
                    'Year Level' => $assignment->class->year_level ?? 'N/A',
                    'Semester' => $assignment->semester->name ?? 'N/A',
                    'Program' => $assignment->unit->program->name ?? 'N/A',
                    'School' => $assignment->unit->school->name ?? 'N/A',
                    'Assigned Date' => $assignment->created_at->format('Y-m-d H:i:s'),
                ];
            });

            $filename = 'lecturer_assignments_' . now()->format('Y-m-d_His') . '.csv';

            // Create CSV
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function() use ($assignments) {
                $file = fopen('php://output', 'w');
                
                // Add BOM for Excel UTF-8 support
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // Add headers
                if ($assignments->count() > 0) {
                    fputcsv($file, array_keys($assignments->first()));
                }
                
                // Add data
                foreach ($assignments as $assignment) {
                    fputcsv($file, $assignment);
                }
                
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Error downloading lecturer assignments: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to download lecturer assignments.');
        }
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
        ->take(10)
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
    'assign_lecturer' => auth()->user()->can('view-lecturer-assignments') || 
                        auth()->user()->can('create-lecturer-assignments'),
]
        ]);
    }

    // special functions for handling enrollments and lecturer assignments can be added here
    /**
 * Get enrollments for a specific school (for School Admins)
 */
public function schoolEnrollments(Request $request, $schoolCode)
{
    $school = School::where('code', $schoolCode)->firstOrFail();
    $user = auth()->user();
    
    // Check if user has access to this school
    if (!$user->hasRole('Admin') && !$user->hasRole("Faculty Admin - {$school->code}")) {
        abort(403, 'Unauthorized access to this school.');
    }

    $perPage = $request->per_page ?? 15;
    $search = $request->search ?? '';
    $semesterId = $request->semester_id;
    $programId = $request->program_id;
    $classId = $request->class_id;
    $unitId = $request->unit_id;
    $studentCode = $request->student_code;
    $status = $request->status;

    // Build query for school-specific enrollments
    $query = Enrollment::with(['student', 'unit.program.school', 'class.program', 'semester'])
        ->whereHas('unit', function($q) use ($school) {
            $q->where('school_id', $school->id);
        })
        ->when($search, function($q) use ($search) {
            $q->where(function($subQ) use ($search) {
                $subQ->where('student_code', 'like', '%' . $search . '%')
                     ->orWhereHas('student', function($sq) use ($search) {
                         $sq->where('name', 'like', '%' . $search . '%');
                     })
                     ->orWhereHas('unit', function($sq) use ($search) {
                         $sq->where('code', 'like', '%' . $search . '%')
                            ->orWhere('name', 'like', '%' . $search . '%');
                     });
            });
        })
        ->when($semesterId, function($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        })
        ->when($programId, function($q) use ($programId) {
            $q->whereHas('class', function($sq) use ($programId) {
                $sq->where('program_id', $programId);
            });
        })
        ->when($classId, function($q) use ($classId) {
            $q->where('class_id', $classId);
        })
        ->when($unitId, function($q) use ($unitId) {
            $q->where('unit_id', $unitId);
        })
        ->when($studentCode, function($q) use ($studentCode) {
            $q->where('student_code', $studentCode);
        })
        ->when($status, function($q) use ($status) {
            $q->where('status', $status);
        })
        ->orderBy('created_at', 'desc');

    $enrollments = $query->paginate($perPage)->withQueryString();

    // Get related data for filters
    $students = Student::where('school_id', $school->id)
        ->select('id', 'student_code', 'name', 'email')
        ->orderBy('student_code')
        ->get();

    $units = Unit::where('school_id', $school->id)
        ->with('program')
        ->select('id', 'code', 'name', 'credit_hours', 'school_id', 'program_id')
        ->orderBy('code')
        ->get();

    $programs = Program::where('school_id', $school->id)
        ->select('id', 'code', 'name', 'school_id')
        ->orderBy('code')
        ->get();

    $classes = ClassModel::whereHas('program', function($q) use ($school) {
            $q->where('school_id', $school->id);
        })
        ->with('program')
        ->select('id', 'name', 'section', 'year_level', 'capacity', 'program_id', 'semester_id')
        ->orderBy('name')
        ->orderBy('section')
        ->get();

    $semesters = Semester::where('is_active', true)
        ->select('id', 'name', 'is_active')
        ->orderBy('name')
        ->get();

    // Get lecturers for this school
    $lecturers = User::role('Lecturer')
        ->whereHas('schools', function($q) use ($school) {
            $q->where('schools.code', $school->code);
        })
        ->with('lecturer')
        ->get()
        ->map(function($user) {
            return [
                'id' => $user->id,
                'code' => $user->lecturer->code ?? null,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'display_name' => "{$user->first_name} {$user->last_name}",
                'schools' => $school->code,
            ];
        });

    // Get lecturer assignments for this school
    $lecturerAssignments = DB::table('lecturer_assignments')
        ->join('units', 'lecturer_assignments.unit_id', '=', 'units.id')
        ->join('semesters', 'lecturer_assignments.semester_id', '=', 'semesters.id')
        ->join('classes', 'lecturer_assignments.class_id', '=', 'classes.id')
        ->join('programs', 'classes.program_id', '=', 'programs.id')
        ->join('lecturers', 'lecturer_assignments.lecturer_code', '=', 'lecturers.code')
        ->join('users', 'lecturers.user_id', '=', 'users.id')
        ->where('units.school_id', $school->id)
        ->select(
            'lecturer_assignments.*',
            'units.code as unit_code',
            'units.name as unit_name',
            'units.credit_hours',
            'semesters.name as semester_name',
            'classes.name as class_name',
            'classes.section',
            'classes.year_level',
            'lecturers.code as lecturer_code',
            'users.first_name as lecturer_first_name',
            'users.last_name as lecturer_last_name'
        )
        ->get()
        ->map(function($assignment) {
            return [
                'unit_id' => $assignment->unit_id,
                'semester_id' => $assignment->semester_id,
                'class_id' => $assignment->class_id,
                'lecturer_code' => $assignment->lecturer_code,
                'unit' => [
                    'id' => $assignment->unit_id,
                    'code' => $assignment->unit_code,
                    'name' => $assignment->unit_name,
                    'credit_hours' => $assignment->credit_hours,
                ],
                'semester' => [
                    'id' => $assignment->semester_id,
                    'name' => $assignment->semester_name,
                ],
                'class' => [
                    'id' => $assignment->class_id,
                    'name' => $assignment->class_name,
                    'section' => $assignment->section,
                    'year_level' => $assignment->year_level,
                ],
                'lecturer' => [
                    'code' => $assignment->lecturer_code,
                    'first_name' => $assignment->lecturer_first_name,
                    'last_name' => $assignment->lecturer_last_name,
                ],
                'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at,
            ];
        });

    // Calculate stats
    $stats = [
        'total' => $enrollments->total(),
        'active' => Enrollment::whereHas('unit', function($q) use ($school) {
                $q->where('school_id', $school->id);
            })
            ->where('status', 'enrolled')
            ->count(),
        'dropped' => Enrollment::whereHas('unit', function($q) use ($school) {
                $q->where('school_id', $school->id);
            })
            ->where('status', 'dropped')
            ->count(),
        'completed' => Enrollment::whereHas('unit', function($q) use ($school) {
                $q->where('school_id', $school->id);
            })
            ->where('status', 'completed')
            ->count(),
        'total_enrollments' => Enrollment::whereHas('unit', function($q) use ($school) {
                $q->where('school_id', $school->id);
            })->count(),
    ];

    return Inertia::render('Schools/' . strtoupper($school->code) . '/Enrollments/Index', [
        'enrollments' => $enrollments,
        'students' => $students,
        'units' => $units,
        'lecturers' => $lecturers,
        'lecturerAssignments' => $lecturerAssignments,
        'schools' => [$school], // Single school for this context
        'programs' => $programs,
        'classes' => $classes,
        'semesters' => $semesters,
        'stats' => $stats,
        'school' => $school,
        'schoolCode' => $schoolCode,
        'can' => [
            'create' => $user->hasRole('Admin') || 
                       $user->can('manage-enrollments') || 
                       $user->can('create-enrollments'),
            
            'update' => $user->hasRole('Admin') || 
                       $user->can('manage-enrollments') || 
                       $user->can('edit-enrollments'),
            
            'delete' => $user->hasRole('Admin') || 
                       $user->can('manage-enrollments') || 
                       $user->can('delete-enrollments'),
            
            'assign_lecturer' => $user->hasRole('Admin') || 
                                $user->can('manage-lecturer-assignments') || 
                                $user->can('create-lecturer-assignments'),
        ],
        'filters' => [
            'search' => $search,
            'semester_id' => $semesterId ? (int) $semesterId : null,
            'program_id' => $programId ? (int) $programId : null,
            'class_id' => $classId ? (int) $classId : null,
            'unit_id' => $unitId ? (int) $unitId : null,
            'student_code' => $studentCode,
            'status' => $status,
            'per_page' => (int) $perPage,
        ],
        'flash' => [
            'success' => session('success'),
            'error' => session('error'),
        ],
    ]);
}

/**
 * Store enrollment for specific school
 */
public function storeSchoolEnrollment(Request $request, $schoolCode)
{
    $school = School::where('code', $schoolCode)->firstOrFail();
    $user = auth()->user();
    
    // Check permissions
    if (!$user->hasRole('Admin') && 
        !$user->hasRole("Faculty Admin - {$school->code}") &&
        !$user->can('create-enrollments')) {
        abort(403, 'Unauthorized.');
    }

    // Use the existing store logic
    return $this->store($request);
}

/**
 * Update enrollment for specific school
 */
public function updateSchoolEnrollment(Request $request, $schoolCode, $enrollmentId)
{
    $school = School::where('code', $schoolCode)->firstOrFail();
    $user = auth()->user();
    
    // Check permissions
    if (!$user->hasRole('Admin') && 
        !$user->hasRole("Faculty Admin - {$school->code}") &&
        !$user->can('edit-enrollments')) {
        abort(403, 'Unauthorized.');
    }

    // Use the existing update logic
    return $this->update($request, $enrollmentId);
}

/**
 * Delete enrollment for specific school
 */
public function destroySchoolEnrollment($schoolCode, $enrollmentId)
{
    $school = School::where('code', $schoolCode)->firstOrFail();
    $user = auth()->user();
    
    // Check permissions
    if (!$user->hasRole('Admin') && 
        !$user->hasRole("Faculty Admin - {$school->code}") &&
        !$user->can('delete-enrollments')) {
        abort(403, 'Unauthorized.');
    }

    // Use the existing destroy logic
    return $this->destroy($enrollmentId);
}
}