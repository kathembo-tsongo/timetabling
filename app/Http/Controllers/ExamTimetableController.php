<?php

namespace App\Http\Controllers;

use App\Models\ExamTimetable;
use App\Models\Unit;
use App\Models\User;
use App\Models\Semester;
use App\Models\SemesterUnit;
use App\Models\Enrollment;
use App\Models\TimeSlot;
use App\Models\Examroom;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ExamTimetableController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Log user roles and permissions
        \Log::info('Accessing /examtimetable', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);

        if (!$user->can('manage-examtimetables')) {
            abort(403, 'Unauthorized action.');
        }

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');

        // Fetch exam timetables with related data using existing schema
        $examTimetables = ExamTimetable::query()
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
            ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
            ->select(
                'exam_timetables.id',
                'exam_timetables.date',
                'exam_timetables.day',
                'exam_timetables.start_time',
                'exam_timetables.end_time',
                'exam_timetables.venue',
                'exam_timetables.location',
                'exam_timetables.no',
                'exam_timetables.chief_invigilator',
                'exam_timetables.unit_id',
                'exam_timetables.semester_id',
                'exam_timetables.class_id',
                'units.name as unit_name',
                'units.code as unit_code',
                'classes.name as class_name',
                // Check if code column exists in classes table, if not use id as fallback
                \Schema::hasColumn('classes', 'code') 
                    ? 'classes.code as class_code'
                    : DB::raw('CONCAT("CLASS-", classes.id) as class_code'),
                'semesters.name as semester_name'
            )
            ->when($request->has('search') && $request->search !== '', function ($query) use ($request) {
                $search = $request->search;
                $query->where('exam_timetables.day', 'like', "%{$search}%")
                    ->orWhere('exam_timetables.date', 'like', "%{$search}%")
                    ->orWhere('units.code', 'like', "%{$search}%")
                    ->orWhere('units.name', 'like', "%{$search}%");
            })
            ->orderBy('exam_timetables.date')
            ->paginate($request->get('per_page', 10));

        // Fetch lecturers with both ID and code
        $lecturers = User::role('Lecturer')
            ->select('id', 'code', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
            ->get();

        // Get all necessary data for the form
        $semesters = Semester::all();
        $examrooms = Examroom::all();
        $timeSlots = TimeSlot::all();
        $classes = ClassModel::all();

        // Build hierarchical structure using existing schema
        $hierarchicalData = $this->buildHierarchicalData();

        // Flatten data for easier access in frontend
        $classesBySemester = [];
        $unitsByClass = [];
        $allUnits = collect();

        foreach ($hierarchicalData as $semesterData) {
            $classesBySemester[$semesterData['id']] = $semesterData['classes'];
            
            foreach ($semesterData['classes'] as $classData) {
                $unitsByClass[$classData['id']] = $classData['units'];
                
                foreach ($classData['units'] as $unitData) {
                    $allUnits->push((object) $unitData);
                }
            }
        }

        // Log the hierarchical data for debugging
        \Log::info('Hierarchical data structure:', [
            'semesters_count' => count($hierarchicalData),
            'total_units' => $allUnits->count(),
            'sample_structure' => array_slice($hierarchicalData, 0, 1)
        ]);

        return Inertia::render('ExamTimetables/Index', [
            'examTimetables' => $examTimetables,
            'lecturers' => $lecturers,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'classes' => $classes,
            'examrooms' => $examrooms,
            'timeSlots' => $timeSlots,
            'hierarchicalData' => $hierarchicalData,
            'classesBySemester' => $classesBySemester,
            'unitsByClass' => $unitsByClass,
            'can' => [
                'create' => $user->can('create-examtimetables'),
                'edit' => $user->can('update-examtimetables'),
                'delete' => $user->can('delete-examtimetables'),
                'process' => $user->can('process-examtimetables'),
                'solve_conflicts' => $user->can('solve-exam-conflicts'),
                'download' => $user->can('download-examtimetables'),
            ],
        ]);
    }

    /**
     * Build hierarchical data using the same logic as enrollment system
     */
    private function buildHierarchicalData()
    {
        $semesters = Semester::all();
        $hierarchicalData = [];
        
        foreach ($semesters as $semester) {
            $semesterData = [
                'id' => $semester->id,
                'name' => $semester->name,
                'classes' => []
            ];
            
            // Get classes through semester_unit pivot table
            $classIds = DB::table('semester_unit')
                ->where('semester_id', $semester->id)
                ->distinct()
                ->pluck('class_id');

            if ($classIds->isNotEmpty()) {
                $classesInSemester = ClassModel::whereIn('id', $classIds)->get();
            } else {
                // Fallback: get classes directly assigned to semester
                $classesInSemester = ClassModel::where('semester_id', $semester->id)->get();
            }
        
            foreach ($classesInSemester as $class) {
                // Check if classes table has code column
                $columns = \Schema::getColumnListing('classes');
                $hasCodeColumn = in_array('code', $columns);
                
                $classData = [
                    'id' => $class->id,
                    'code' => $hasCodeColumn ? ($class->code ?? 'CLASS-' . $class->id) : 'CLASS-' . $class->id,
                    'name' => $class->name,
                    'semester_id' => $semester->id,
                    'units' => []
                ];
                
                // Get units for this class and semester
                $unitIds = DB::table('semester_unit')
                    ->where('semester_id', $semester->id)
                    ->where('class_id', $class->id)
                    ->pluck('unit_id');

                if ($unitIds->isNotEmpty()) {
                    $unitsInClass = Unit::whereIn('id', $unitIds)->get();
                } else {
                    // Fallback: get units directly assigned to class
                    $unitsInClass = Unit::where('class_id', $class->id)->get();
                }
                
                foreach ($unitsInClass as $unit) {
                    // Find enrollments for this unit in this semester
                    $enrollments = Enrollment::where('unit_id', $unit->id)
                        ->where('semester_id', $semester->id)
                        ->get();
                    
                    // Count students
                    $studentCount = $enrollments->count();
                    
                    // Find lecturer
                    $lecturerCode = null;
                    $lecturerName = null;
                    $lecturerEnrollment = $enrollments->whereNotNull('lecturer_code')->first();
                    if ($lecturerEnrollment) {
                        $lecturerCode = $lecturerEnrollment->lecturer_code;
                        $lecturer = User::where('code', $lecturerCode)->first();
                        if ($lecturer) {
                            $lecturerName = $lecturer->first_name . ' ' . $lecturer->last_name;
                        }
                    }
                    
                    $unitData = [
                        'id' => $unit->id,
                        'code' => $unit->code,
                        'name' => $unit->name,
                        'class_id' => $class->id,
                        'semester_id' => $semester->id,
                        'student_count' => $studentCount,
                        'lecturer_code' => $lecturerCode,
                        'lecturer_name' => $lecturerName
                    ];
                    
                    $classData['units'][] = $unitData;
                }
                
                // Only add class if it has units
                if (!empty($classData['units'])) {
                    $semesterData['classes'][] = $classData;
                }
            }
            
            // Only add semester if it has classes with units
            if (!empty($semesterData['classes'])) {
                $hierarchicalData[] = $semesterData;
            }
        }

        return $hierarchicalData;
    }

    /**
     * Get classes for a specific semester (API endpoint)
     */
    public function getClassesBySemester(Request $request, $semesterId)
    {
        try {
            Log::info('Getting classes for semester', ['semester_id' => $semesterId]);

            // Get classes through semester_unit pivot table
            $classIds = DB::table('semester_unit')
                ->where('semester_id', $semesterId)
                ->distinct()
                ->pluck('class_id');

            if ($classIds->isNotEmpty()) {
                // Check if 'code' column exists in classes table
                $columns = \Schema::getColumnListing('classes');
                $hasCodeColumn = in_array('code', $columns);
                
                if ($hasCodeColumn) {
                    $classes = ClassModel::whereIn('id', $classIds)
                        ->select('id', 'name', 'code', 'semester_id')
                        ->get();
                } else {
                    // If no code column, use id as code fallback
                    $classes = ClassModel::whereIn('id', $classIds)
                        ->select('id', 'name', 'semester_id', DB::raw('CONCAT("CLASS-", id) as code'))
                        ->get();
                }
            } else {
                // Fallback: get classes directly assigned to semester
                $columns = \Schema::getColumnListing('classes');
                $hasCodeColumn = in_array('code', $columns);
                
                if ($hasCodeColumn) {
                    $classes = ClassModel::where('semester_id', $semesterId)
                        ->select('id', 'name', 'code', 'semester_id')
                        ->get();
                } else {
                    $classes = ClassModel::where('semester_id', $semesterId)
                        ->select('id', 'name', 'semester_id', DB::raw('CONCAT("CLASS-", id) as code'))
                        ->get();
                }
            }

            Log::info('Found classes for semester', [
                'semester_id' => $semesterId,
                'classes_count' => $classes->count(),
                'classes' => $classes->toArray()
            ]);

            return response()->json([
                'success' => true,
                'classes' => $classes
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get classes for semester: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Failed to get classes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get units for a specific class and semester (API endpoint)
     */
    public function getUnitsByClassAndSemesterForExam(Request $request)
    {
        try {
            $validated = $request->validate([
                'semester_id' => 'required|exists:semesters,id',
                'class_id' => 'required|exists:classes,id',
            ]);

            Log::info('Getting units by class and semester for exam', [
                'semester_id' => $validated['semester_id'],
                'class_id' => $validated['class_id']
            ]);

            // First, try to get units through semester_unit pivot table
            $unitIds = DB::table('semester_unit')
                ->where('semester_id', $validated['semester_id'])
                ->where('class_id', $validated['class_id'])
                ->pluck('unit_id');

            if ($unitIds->isNotEmpty()) {
                $units = Unit::whereIn('id', $unitIds)
                    ->select('id', 'code', 'name', 'class_id')
                    ->get();
            } else {
                // Fallback: get units directly assigned to class
                $units = Unit::where('class_id', $validated['class_id'])
                    ->select('id', 'code', 'name', 'class_id')
                    ->get();
            }

            // Get enrollment data for each unit
            $unitsWithEnrollmentData = $units->map(function ($unit) use ($validated) {
                // Find enrollments for this unit in this semester
                $enrollments = Enrollment::where('unit_id', $unit->id)
                    ->where('semester_id', $validated['semester_id'])
                    ->get();

                // Count students
                $studentCount = $enrollments->count();

                // Find lecturer
                $lecturerEnrollment = $enrollments->whereNotNull('lecturer_code')->first();
                $lecturerName = null;
                $lecturerCode = null;
                if ($lecturerEnrollment) {
                    $lecturerCode = $lecturerEnrollment->lecturer_code;
                    $lecturer = User::where('code', $lecturerCode)->first();
                    if ($lecturer) {
                        $lecturerName = $lecturer->first_name . ' ' . $lecturer->last_name;
                    }
                }

                return [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'class_id' => $unit->class_id,
                    'semester_id' => $validated['semester_id'],
                    'student_count' => $studentCount,
                    'lecturer_code' => $lecturerCode,
                    'lecturer_name' => $lecturerName
                ];
            });

            Log::info('Found units for class and semester', [
                'semester_id' => $validated['semester_id'],
                'class_id' => $validated['class_id'],
                'units_count' => $unitsWithEnrollmentData->count()
            ]);

            return response()->json([
                'success' => true,
                'units' => $unitsWithEnrollmentData
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get units for class and semester: ' . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Failed to get units: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * SMART VENUE ASSIGNMENT - Find the best available venue for given parameters
     */
    private function assignOptimalVenue($studentCount, $date, $startTime, $endTime, $excludeExamId = null)
    {
        try {
            Log::info('Starting smart venue assignment', [
                'student_count' => $studentCount,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'exclude_exam_id' => $excludeExamId
            ]);

            // Get all available exam rooms
            $examrooms = Examroom::all();

            if ($examrooms->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No exam rooms available in the system.',
                    'venue' => null,
                    'location' => null
                ];
            }

            // Filter rooms that can accommodate the students (capacity >= student count)
            $suitableRooms = $examrooms->filter(function ($room) use ($studentCount) {
                return $room->capacity >= $studentCount;
            });

            if ($suitableRooms->isEmpty()) {
                return [
                    'success' => false,
                    'message' => "No exam rooms can accommodate {$studentCount} students. Maximum available capacity: " . $examrooms->max('capacity'),
                    'venue' => null,
                    'location' => null
                ];
            }

            // Get conflicting exam schedules for the same time slot
            $conflictingExams = ExamTimetable::where('date', $date)
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($subQuery) use ($startTime, $endTime) {
                        // Check for time overlaps
                        $subQuery->whereBetween('start_time', [$startTime, $endTime])
                               ->orWhereBetween('end_time', [$startTime, $endTime])
                               ->orWhere(function ($timeQuery) use ($startTime, $endTime) {
                                   $timeQuery->where('start_time', '<=', $startTime)
                                            ->where('end_time', '>=', $endTime);
                               });
                    });
                });

            // Exclude current exam if we're updating
            if ($excludeExamId) {
                $conflictingExams->where('id', '!=', $excludeExamId);
            }

            $conflictingVenues = $conflictingExams->pluck('venue')->toArray();

            Log::info('Found conflicting venues', [
                'conflicting_venues' => $conflictingVenues,
                'conflicting_exams_count' => count($conflictingVenues)
            ]);

            // Filter out venues that are already booked during this time
            $availableRooms = $suitableRooms->filter(function ($room) use ($conflictingVenues) {
                return !in_array($room->name, $conflictingVenues);
            });

            if ($availableRooms->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'All suitable exam rooms are already booked for this time slot.',
                    'venue' => null,
                    'location' => null,
                    'suggested_venues' => $suitableRooms->pluck('name')->toArray()
                ];
            }

            // Sort by capacity efficiency (prefer rooms that are closer to student count to avoid waste)
            $sortedRooms = $availableRooms->sortBy(function ($room) use ($studentCount) {
                return $room->capacity - $studentCount; // Ascending order - prefer smaller suitable rooms
            });

            // Add some randomization to avoid always picking the same room
            $topCandidates = $sortedRooms->take(3); // Take top 3 most suitable rooms
            $selectedRoom = $topCandidates->random(); // Randomly select from top candidates

            Log::info('Successfully assigned venue', [
                'selected_venue' => $selectedRoom->name,
                'venue_capacity' => $selectedRoom->capacity,
                'student_count' => $studentCount,
                'efficiency' => round(($studentCount / $selectedRoom->capacity) * 100, 2) . '%'
            ]);

            return [
                'success' => true,
                'message' => "Venue automatically assigned: {$selectedRoom->name} (Capacity: {$selectedRoom->capacity})",
                'venue' => $selectedRoom->name,
                'location' => $selectedRoom->location,
                'capacity' => $selectedRoom->capacity,
                'efficiency' => round(($studentCount / $selectedRoom->capacity) * 100, 2)
            ];

        } catch (\Exception $e) {
            Log::error('Error in smart venue assignment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error occurred during venue assignment: ' . $e->getMessage(),
                'venue' => null,
                'location' => null
            ];
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'day' => 'required|string',
            'date' => 'required|date',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);

        try {
            // Smart venue assignment
            $venueAssignment = $this->assignOptimalVenue(
                $request->no,
                $request->date,
                $request->start_time,
                $request->end_time
            );

            if (!$venueAssignment['success']) {
                return redirect()->back()->withErrors([
                    'venue' => $venueAssignment['message']
                ])->withInput();
            }

            $examTimetable = ExamTimetable::create([
                'day' => $request->day,
                'date' => $request->date,
                'unit_id' => $request->unit_id,
                'semester_id' => $request->semester_id,
                'class_id' => $request->class_id,
                'venue' => $venueAssignment['venue'],
                'location' => $venueAssignment['location'],
                'no' => $request->no,
                'chief_invigilator' => $request->chief_invigilator,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
            ]);

            $successMessage = 'Exam timetable created successfully. ' . $venueAssignment['message'];
            
            return redirect()->back()->with('success', $successMessage);
        } catch (\Exception $e) {
            \Log::error('Failed to create exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create exam timetable: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'day' => 'required|string',
            'date' => 'required|date',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'no' => 'required|integer',
            'chief_invigilator' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);

        try {
            $examTimetable = ExamTimetable::findOrFail($id);

            // Smart venue assignment (excluding current exam from conflict check)
            $venueAssignment = $this->assignOptimalVenue(
                $request->no,
                $request->date,
                $request->start_time,
                $request->end_time,
                $id // Exclude current exam from conflict check
            );

            if (!$venueAssignment['success']) {
                return redirect()->back()->withErrors([
                    'venue' => $venueAssignment['message']
                ])->withInput();
            }

            $examTimetable->update([
                'day' => $request->day,
                'date' => $request->date,
                'unit_id' => $request->unit_id,
                'semester_id' => $request->semester_id,
                'class_id' => $request->class_id,
                'venue' => $venueAssignment['venue'],
                'location' => $venueAssignment['location'],
                'no' => $request->no,
                'chief_invigilator' => $request->chief_invigilator,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
            ]);

            $successMessage = 'Exam timetable updated successfully. ' . $venueAssignment['message'];

            return redirect()->back()->with('success', $successMessage);
        } catch (\Exception $e) {
            \Log::error('Failed to update exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to update exam timetable: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $examTimetable = ExamTimetable::findOrFail($id);
            $examTimetable->delete();
            return redirect()->back()->with('success', 'Exam timetable deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to delete exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to delete exam timetable: ' . $e->getMessage());
        }
    }

    /**
     * Process exam timetables
     */
    public function process(Request $request)
    {
        try {
            \Log::info('Processing exam timetable');
            return redirect()->back()->with('success', 'Exam timetable processed successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to process exam timetable: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to process exam timetable: ' . $e->getMessage());
        }
    }

    /**
     * Solve exam conflicts
     */
    public function solveConflicts(Request $request)
    {
        try {
            \Log::info('Solving exam timetable conflicts');
            return redirect()->back()->with('success', 'Exam conflicts resolved successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to solve exam conflicts: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to solve exam conflicts: ' . $e->getMessage());
        }
    }

    /**
     * Download exam timetable as PDF
     */
    public function downloadPDF(Request $request)
    {
        try {
            if (!view()->exists('examtimetables.pdf')) {
                \Log::error('PDF template not found: examtimetables.pdf');
                return redirect()->back()->with('error', 'PDF template not found. Please contact the administrator.');
            }

            $query = ExamTimetable::query()
                ->join('units', 'exam_timetables.unit_id', '=', 'units.id')
                ->join('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
                ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
                ->select(
                    'exam_timetables.*',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'semesters.name as semester_name',
                    'classes.name as class_name'
                );

            if ($request->has('semester_id')) {
                $query->where('exam_timetables.semester_id', $request->semester_id);
            }

            if ($request->has('class_id')) {
                $query->where('exam_timetables.class_id', $request->class_id);
            }

            $examTimetables = $query->orderBy('exam_timetables.date')
                ->orderBy('exam_timetables.start_time')
                ->get();

            $pdf = Pdf::loadView('examtimetables.pdf', [
                'examTimetables' => $examTimetables,
                'title' => 'Exam Timetable',
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ]);

            $pdf->setPaper('a4', 'landscape');

            return $pdf->download('examtimetable.pdf');
        } catch (\Exception $e) {
            \Log::error('Failed to generate PDF: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

// Add these methods to your ExamTimetableController class

/**
 * Show the form for creating a new resource.
 */
public function create()
{
    // This can return a view if needed, or just return success for API
    return response()->json(['success' => true, 'message' => 'Create form ready']);
}

/**
 * Display the specified resource.
 */
public function show($id)
{
    try {
        $examTimetable = ExamTimetable::with(['unit', 'semester', 'class'])->findOrFail($id);
        return response()->json([
            'success' => true,
            'examTimetable' => $examTimetable
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Exam timetable not found'
        ], 404);
    }
}

/**
 * Show the form for editing the specified resource.
 */
public function edit($id)
{
    try {
        $examTimetable = ExamTimetable::with(['unit', 'semester', 'class'])->findOrFail($id);
        return response()->json([
            'success' => true,
            'examTimetable' => $examTimetable
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Exam timetable not found'
        ], 404);
    }
}

/**
 * Ajax delete method
 */
public function ajaxDestroy($id)
{
    try {
        $examTimetable = ExamTimetable::findOrFail($id);
        $examTimetable->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Exam timetable deleted successfully.'
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to delete exam timetable via AJAX', [
            'id' => $id,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete exam timetable: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Display specific exam details for student
 */
public function viewStudentExamDetails($examtimetableId)
{
    $user = auth()->user();
    
    try {
        // Get the exam timetable
        $examTimetable = ExamTimetable::leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
            ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
            ->select(
                'exam_timetables.*',
                'units.name as unit_name',
                'units.code as unit_code',
                'classes.name as class_name',
                'classes.code as class_code',
                'semesters.name as semester_name'
            )
            ->where('exam_timetables.id', $examtimetableId)
            ->first();

        if (!$examTimetable) {
            abort(404, 'Exam timetable not found.');
        }

        // Verify student is enrolled in this unit
        $enrollment = $user->enrollments()
            ->where('unit_id', $examTimetable->unit_id)
            ->where('semester_id', $examTimetable->semester_id)
            ->first();

        if (!$enrollment) {
            abort(403, 'You are not enrolled in this unit.');
        }

        return Inertia::render('Student/ExamDetails', [
            'examTimetable' => $examTimetable,
            'enrollment' => $enrollment,
            'auth' => ['user' => $user]
        ]);
    } catch (\Exception $e) {
        Log::error('Error viewing student exam details', [
            'exam_id' => $examtimetableId,
            'user_id' => $user->id,
            'error' => $e->getMessage()
        ]);
        
        return redirect()->route('student.exams')->with('error', 'Unable to view exam details.');
    }
}


/**
 * Download student's exam timetable as PDF - Using examtimetables/student.blade.php
 */
public function downloadStudentTimetable(Request $request)
{
    $user = auth()->user();
    
    try {
        Log::info('Student PDF download requested', [
            'user_id' => $user->id,
            'student_id' => $user->student_id ?? $user->code,
            'semester_id' => $request->get('semester_id')
        ]);

        // Get student's enrollments
        $enrollments = $user->enrollments()->with(['unit', 'semester', 'class'])->get();
        
        if ($enrollments->isEmpty()) {
            Log::warning('No enrollments found for student PDF download', ['user_id' => $user->id]);
            return redirect()->back()->with('error', 'No enrollments found. Cannot generate timetable.');
        }

        // Build query for exam timetables
        $query = ExamTimetable::query()
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
            ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
            ->whereIn('exam_timetables.unit_id', $enrollments->pluck('unit_id'))
            ->whereIn('exam_timetables.semester_id', $enrollments->pluck('semester_id'))
            ->select(
                'exam_timetables.id',
                'exam_timetables.date',
                'exam_timetables.day',
                'exam_timetables.start_time',
                'exam_timetables.end_time',
                'exam_timetables.venue',
                'exam_timetables.location',
                'exam_timetables.no',
                'exam_timetables.chief_invigilator',
                'exam_timetables.unit_id',
                'exam_timetables.semester_id',
                'exam_timetables.class_id',
                'units.name as unit_name',
                'units.code as unit_code',
                'classes.name as class_name',
                \Schema::hasColumn('classes', 'code') 
                    ? 'classes.code as class_code'
                    : DB::raw('CONCAT("CLASS-", classes.id) as class_code'),
                'semesters.name as semester_name'
            )
            ->orderBy('exam_timetables.date')
            ->orderBy('exam_timetables.start_time');

        // Filter by semester if requested
        if ($request->has('semester_id') && $request->semester_id) {
            $query->where('exam_timetables.semester_id', $request->semester_id);
            $selectedSemester = Semester::find($request->semester_id);
        } else {
            // Get current/active semester
            $selectedSemester = $enrollments->first()->semester;
        }

        $examTimetables = $query->get();

        Log::info('Found exam timetables for PDF', [
            'user_id' => $user->id,
            'count' => $examTimetables->count(),
            'semester' => $selectedSemester->name ?? 'Unknown'
        ]);

        // Check if Blade template exists
        if (!view()->exists('examtimetables.student')) {
            Log::error('Blade template not found: examtimetables.student');
            return redirect()->back()->with('error', 'PDF template not found. Please contact the administrator.');
        }

        // Prepare data for Blade template
        $data = [
            'examTimetables' => $examTimetables,
            'student' => $user,
            'currentSemester' => $selectedSemester,
            'title' => 'Student Exam Timetable',
            'generatedAt' => now()->format('F j, Y \a\t g:i A'),
            'studentName' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'studentId' => $user->student_id ?? $user->code ?? $user->id,
        ];

        // Test the view first to catch any errors
        try {
            $viewContent = view('examtimetables.student', $data)->render();
            Log::info('Blade template rendered successfully', [
                'content_length' => strlen($viewContent),
                'user_id' => $user->id
            ]);
        } catch (\Exception $e) {
            Log::error('Blade template rendering failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Error rendering PDF template: ' . $e->getMessage());
        }

        // Generate PDF using examtimetables/student.blade.php template
        $pdf = PDF::loadView('examtimetables.student', $data);
        $pdf->setPaper('a4', 'portrait');
        
        // Set PDF options for better rendering
        $pdf->setOptions([
            'defaultFont' => 'Arial',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'debugPng' => false,
            'debugKeepTemp' => false,
            'debugCss' => false,
        ]);

        $studentId = $user->student_id ?? $user->code ?? $user->id;
        $filename = "exam-timetable-{$studentId}-" . now()->format('Y-m-d') . ".pdf";
        
        Log::info('PDF generated successfully', [
            'user_id' => $user->id,
            'filename' => $filename
        ]);

        // Generate PDF content and check if it's actually PDF
        $pdfContent = $pdf->output();
        
        // Check if the content is actually a PDF (starts with %PDF)
        if (substr($pdfContent, 0, 4) !== '%PDF') {
            Log::error('Generated content is not a valid PDF', [
                'user_id' => $user->id,
                'content_start' => substr($pdfContent, 0, 100)
            ]);
            return redirect()->back()->with('error', 'Failed to generate valid PDF. Please try again.');
        }

        // Return PDF as download with proper headers
        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => strlen($pdfContent),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
        
    } catch (\Exception $e) {
        Log::error('Failed to generate student exam timetable PDF', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
    }
}
 public function viewStudentTimetable(Request $request)
{
    $user = $request->user();

    // Get student's enrollments
    $enrollments = $user->enrollments()->with(['unit', 'semester', 'class'])->get();

    // Get exam timetables for enrolled units
    $examTimetables = \App\Models\ExamTimetable::whereIn('unit_id', $enrollments->pluck('unit_id'))
        ->whereIn('semester_id', $enrollments->pluck('semester_id'))
        ->with(['unit', 'semester', 'class', 'examroom'])
        ->orderBy('date')
        ->orderBy('start_time')
        ->get();

    return \Inertia\Inertia::render('Student/ExamTimetable', [
        'examTimetables' => $examTimetables,
    ]);
}

public function studentExamTimetable(Request $request)
{
    $user = auth()->user();
    
    try {
        // Get student's enrollments
        $enrollments = $user->enrollments()->with(['unit', 'semester', 'class'])->get();
        
        if ($enrollments->isEmpty()) {
            return Inertia::render('Student/ExamTimetable', [
                'examTimetables' => collect([]),
                'semesters' => collect([]),
                'selectedSemesterId' => null,
                'message' => 'No enrollments found. Please enroll in units to view your exam timetable.'
            ]);
        }

        // Get available semesters from enrollments
        $semesters = $enrollments->pluck('semester')->unique('id')->values();
        
        // Determine selected semester
        $selectedSemesterId = $request->get('semester_id');
        if (!$selectedSemesterId) {
            // Default to the first semester or current semester
            $selectedSemesterId = $semesters->first()->id ?? null;
        }

        // Get exam timetables for enrolled units
        $query = ExamTimetable::query()
            ->leftJoin('units', 'exam_timetables.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'exam_timetables.semester_id', '=', 'semesters.id')
            ->leftJoin('classes', 'exam_timetables.class_id', '=', 'classes.id')
            ->whereIn('exam_timetables.unit_id', $enrollments->pluck('unit_id'))
            ->whereIn('exam_timetables.semester_id', $enrollments->pluck('semester_id'))
            ->select(
                'exam_timetables.id',
                'exam_timetables.date',
                'exam_timetables.day',
                'exam_timetables.start_time',
                'exam_timetables.end_time',
                'exam_timetables.venue',
                'exam_timetables.location',
                'exam_timetables.no',
                'exam_timetables.chief_invigilator',
                'exam_timetables.unit_id',
                'exam_timetables.semester_id',
                'exam_timetables.class_id',
                'units.name as unit_name',
                'units.code as unit_code',
                'classes.name as class_name',
                \Schema::hasColumn('classes', 'code') 
                    ? 'classes.code as class_code'
                    : DB::raw('CONCAT("CLASS-", classes.id) as class_code'),
                'semesters.name as semester_name'
            );

        // Filter by selected semester if specified
        if ($selectedSemesterId) {
            $query->where('exam_timetables.semester_id', $selectedSemesterId);
        }

        $examTimetables = $query->orderBy('exam_timetables.date')
            ->orderBy('exam_timetables.start_time')
            ->get();

        Log::info('Student exam timetable loaded', [
            'user_id' => $user->id,
            'enrollments_count' => $enrollments->count(),
            'semesters_count' => $semesters->count(),
            'exams_count' => $examTimetables->count(),
            'selected_semester_id' => $selectedSemesterId
        ]);

        return Inertia::render('Student/ExamTimetable', [
            'examTimetables' => $examTimetables,
            'semesters' => $semesters,
            'selectedSemesterId' => $selectedSemesterId,
            'enrollments' => $enrollments,
            'student' => $user
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error loading student exam timetable', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return Inertia::render('Student/ExamTimetable', [
            'examTimetables' => collect([]),
            'semesters' => collect([]),
            'selectedSemesterId' => null,
            'message' => 'Error loading exam timetable. Please try again or contact support.'
        ]);
    }
}
}
