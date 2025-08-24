<?php

namespace App\Http\Controllers;

use App\Models\ClassTimetable;
use App\Models\Unit;
use App\Models\User;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\ClassTimeSlot;
use App\Models\Classroom;
use App\Models\ClassModel;
use App\Models\Program;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class AutoGenerateTimetableController extends Controller
{
    /**
     * Show the auto-generate timetable page.
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $semesters = Semester::orderBy('name')->get();
            
            return Inertia::render('ClassTimetables/AutoGenerate', [
                'semesters' => $semesters,
                'programs' => [], // Will be loaded dynamically
                'classes' => [], // Will be loaded dynamically
                'groups' => [], // Will be loaded dynamically
                'can' => [
                    'generate' => $user->can('manage-classtimetables'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading auto-generate form: ' . $e->getMessage());
            return Inertia::render('ClassTimetables/AutoGenerate', [
                'semesters' => [],
                'programs' => [],
                'classes' => [],
                'groups' => [],
                'error' => 'Failed to load form data.',
                'can' => ['generate' => false],
            ]);
        }
    }

    /**
     * Get programs by semester (API endpoint)
     */
    public function getProgramsBySemester(Request $request)
    {
        try {
            $semesterId = $request->input('semester_id');
            
            Log::info('Fetching programs for semester', ['semester_id' => $semesterId]);
            
            if (!$semesterId) {
                return response()->json([]);
            }

            // Method 1: Get programs through semester_unit table
            $programIds = DB::table('semester_unit')
                ->join('classes', 'semester_unit.class_id', '=', 'classes.id')
                ->where('semester_unit.semester_id', $semesterId)
                ->select('classes.program_id')
                ->distinct()
                ->pluck('program_id')
                ->toArray();

            Log::info('Program IDs found through semester_unit', ['program_ids' => $programIds]);

            // Method 2: Get programs through classes directly
            if (empty($programIds)) {
                $programIds = ClassModel::where('semester_id', $semesterId)
                    ->distinct()
                    ->pluck('program_id')
                    ->toArray();
                
                Log::info('Program IDs found through classes', ['program_ids' => $programIds]);
            }

            // Method 3: Get programs through enrollments
            if (empty($programIds)) {
                $programIds = Enrollment::where('semester_id', $semesterId)
                    ->whereNotNull('program_id')
                    ->distinct()
                    ->pluck('program_id')
                    ->toArray();
                
                Log::info('Program IDs found through enrollments', ['program_ids' => $programIds]);
            }

            if (empty($programIds)) {
                Log::warning('No programs found for semester', ['semester_id' => $semesterId]);
                return response()->json([]);
            }

            $programs = Program::whereIn('id', $programIds)
                ->orderBy('name')
                ->get();

            Log::info('Programs found', ['count' => $programs->count(), 'programs' => $programs->toArray()]);

            return response()->json($programs);
        } catch (\Exception $e) {
            Log::error('Error fetching programs by semester: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([]);
        }
    }

    /**
     * Get classes by program and semester (API endpoint)
     */
    public function getClassesByProgramAndSemester(Request $request)
    {
        try {
            $programId = $request->input('program_id');
            $semesterId = $request->input('semester_id');

            Log::info('Fetching classes', ['program_id' => $programId, 'semester_id' => $semesterId]);

            if (!$programId || !$semesterId) {
                return response()->json([]);
            }

            // Method 1: Get classes through direct relationship
            $classes = ClassModel::where('program_id', $programId)
                ->where('semester_id', $semesterId)
                ->orderBy('name')
                ->get();

            Log::info('Classes found through direct relationship', ['count' => $classes->count()]);

            // Method 2: If no classes found, try through semester_unit table
            if ($classes->isEmpty()) {
                $classIds = DB::table('semester_unit')
                    ->join('classes', 'semester_unit.class_id', '=', 'classes.id')
                    ->where('semester_unit.semester_id', $semesterId)
                    ->where('classes.program_id', $programId)
                    ->select('classes.id')
                    ->distinct()
                    ->pluck('id')
                    ->toArray();

                $classes = ClassModel::whereIn('id', $classIds)
                    ->orderBy('name')
                    ->get();

                Log::info('Classes found through semester_unit', ['count' => $classes->count(), 'class_ids' => $classIds]);
            }

            // Method 3: If still no classes, try through enrollments
            if ($classes->isEmpty()) {
                $classIds = Enrollment::where('semester_id', $semesterId)
                    ->where('program_id', $programId)
                    ->whereNotNull('class_id')
                    ->distinct()
                    ->pluck('class_id')
                    ->toArray();

                $classes = ClassModel::whereIn('id', $classIds)
                    ->orderBy('name')
                    ->get();

                Log::info('Classes found through enrollments', ['count' => $classes->count(), 'class_ids' => $classIds]);
            }

            return response()->json($classes);
        } catch (\Exception $e) {
            Log::error('Error fetching classes: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([]);
        }
    }

    /**
     * Get groups by class (API endpoint)
     */
    public function getGroupsByClass(Request $request)
    {
        try {
            $classId = $request->input('class_id');

            Log::info('Fetching groups', ['class_id' => $classId]);

            if (!$classId) {
                return response()->json([]);
            }

            $groups = Group::where('class_id', $classId)
                ->orderBy('name')
                ->get();

            Log::info('Groups found', ['count' => $groups->count(), 'groups' => $groups->toArray()]);

            return response()->json($groups);
        } catch (\Exception $e) {
            Log::error('Error fetching groups: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([]);
        }
    }

    /**
     * Distribute credit hours into sessions based on requirements
     */
    private function distributeCreditHours($creditHours)
    {
        switch ($creditHours) {
            case 2:
                return [['hours' => 2, 'mode' => 'Physical']];
            case 3:
                return [
                    ['hours' => 2, 'mode' => 'Physical'],
                    ['hours' => 1, 'mode' => 'Online']
                ];
            case 4:
                return [
                    ['hours' => 2, 'mode' => 'Physical'],
                    ['hours' => 2, 'mode' => 'Physical']
                ];
            default:
                return [['hours' => $creditHours, 'mode' => 'Physical']];
        }
    }

    /**
     * Check if two time slots are consecutive
     */
    private function areConsecutiveSlots($slot1, $slot2)
    {
        if ($slot1->day !== $slot2->day) {
            return false;
        }

        $slot1End = Carbon::parse($slot1->end_time);
        $slot2Start = Carbon::parse($slot2->start_time);

        // Check if slot2 starts within 15 minutes of slot1 ending
        return abs($slot1End->diffInMinutes($slot2Start)) <= 15;
    }

    /**
     * Get slot duration in hours
     */
    private function getSlotDuration($startTime, $endTime)
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);
        return $end->diffInHours($start);
    }

    /**
     * Find available time slots for a lecturer avoiding consecutive classes
     */
    private function findAvailableSlotsForLecturer($lecturerCode, $requiredHours, $mode, $timeSlots, $lecturerSchedule)
    {
        $availableSlots = [];

        foreach ($timeSlots as $slot) {
            // Check if slot matches required duration and mode
            $slotDuration = $this->getSlotDuration($slot->start_time, $slot->end_time);
            if ($slotDuration != $requiredHours || $slot->status !== $mode) {
                continue;
            }

            // Check if lecturer is already busy at this time
            $slotKey = $slot->day . '_' . $slot->start_time . '_' . $slot->end_time;
            if (isset($lecturerSchedule[$lecturerCode][$slotKey])) {
                continue;
            }

            // Check for consecutive slot conflicts
            $hasConsecutiveConflict = false;
            foreach ($timeSlots as $otherSlot) {
                $otherSlotKey = $otherSlot->day . '_' . $otherSlot->start_time . '_' . $otherSlot->end_time;
                if (isset($lecturerSchedule[$lecturerCode][$otherSlotKey])) {
                    if ($this->areConsecutiveSlots($slot, $otherSlot)) {
                        $hasConsecutiveConflict = true;
                        break;
                    }
                }
            }

            if (!$hasConsecutiveConflict) {
                $availableSlots[] = $slot;
            }
        }

        return $availableSlots;
    }

    /**
     * Handle the enhanced auto-generation of the timetable.
     */
    public function autoGenerate(Request $request)
    {
        try {
            Log::info('Auto-Generate Timetable Request', [
                'data' => $request->all()
            ]);

            $validated = $request->validate([
                'semester_id' => 'required|exists:semesters,id',
                'program_id' => 'required|exists:programs,id',
                'class_id' => 'required|exists:classes,id',
                'group_id' => 'nullable|exists:groups,id',
            ]);

            $semesterId = $validated['semester_id'];
            $programId = $validated['program_id'];
            $classId = $validated['class_id'];
            $groupId = $validated['group_id'] ?? null;

            DB::beginTransaction();

            // Find units for this class and semester
            // Method 1: Through semester_unit table
            $unitIds = DB::table('semester_unit')
                ->where('semester_id', $semesterId)
                ->where('class_id', $classId)
                ->pluck('unit_id')
                ->toArray();

            Log::info('Units found through semester_unit', ['count' => count($unitIds)]);

            // Method 2: If no units found, try through enrollments
            if (empty($unitIds)) {
                $unitIds = Enrollment::where('semester_id', $semesterId)
                    ->where('class_id', $classId)
                    ->whereNotNull('unit_id')
                    ->distinct()
                    ->pluck('unit_id')
                    ->toArray();
                
                Log::info('Units found through enrollments', ['count' => count($unitIds)]);
            }

            if ($groupId) {
                // Filter by group if specified
                $groupUnitIds = Enrollment::where('group_id', $groupId)
                    ->whereIn('unit_id', $unitIds)
                    ->distinct()
                    ->pluck('unit_id')
                    ->toArray();
                
                Log::info('Units filtered by group', ['before' => count($unitIds), 'after' => count($groupUnitIds)]);
                
                $unitIds = $groupUnitIds;
            }

            $units = Unit::whereIn('id', $unitIds)->get();
            $venues = Classroom::all();
            $timeSlots = ClassTimeSlot::all();

            if ($units->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No units found for the selected criteria.'
                ], 404);
            }

            if ($venues->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No classrooms available. Please add classrooms first.'
                ], 404);
            }

            if ($timeSlots->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No time slots available. Please add time slots first.'
                ], 404);
            }

            $generatedTimetable = [];
            $lecturerSchedule = []; // Track lecturer schedules
            $venueSchedule = []; // Track venue schedules

            foreach ($units as $unit) {
                // Get enrollments and lecturer info
                $enrollments = Enrollment::where('unit_id', $unit->id)
                    ->where('semester_id', $semesterId)
                    ->when($classId, fn($q) => $q->where('class_id', $classId))
                    ->when($groupId, fn($q) => $q->where('group_id', $groupId))
                    ->get();

                $studentCount = $enrollments->whereNotNull('student_code')->count();
                $lecturerEnrollment = $enrollments->whereNotNull('lecturer_code')->first();
                
                if (!$lecturerEnrollment) {
                    Log::warning("No lecturer assigned to unit {$unit->code}");
                    continue;
                }

                $lecturer = User::where('code', $lecturerEnrollment->lecturer_code)->first();
                if (!$lecturer) {
                    Log::warning("Lecturer not found for code {$lecturerEnrollment->lecturer_code}");
                    continue;
                }

                $lecturerName = $lecturer->first_name . ' ' . $lecturer->last_name;
                $lecturerCode = $lecturer->code;

                // Distribute credit hours into sessions
                $sessions = $this->distributeCreditHours($unit->credit_hours ?? 3);

                foreach ($sessions as $sessionIndex => $session) {
                    // Find available slots for this lecturer
                    $availableSlots = $this->findAvailableSlotsForLecturer(
                        $lecturerCode,
                        $session['hours'],
                        $session['mode'],
                        $timeSlots,
                        $lecturerSchedule
                    );

                    if (empty($availableSlots)) {
                        Log::warning("No available time slots for lecturer {$lecturerName} for unit {$unit->code} (Session " . ($sessionIndex + 1) . ")");
                        continue; // Skip this session instead of throwing exception
                    }

                    // Select random available slot
                    $selectedSlot = $availableSlots[array_rand($availableSlots)];

                    // Find available venue
                    $selectedVenue = null;
                    if ($session['mode'] === 'Physical') {
                        $availableVenues = $venues->filter(function ($venue) use ($selectedSlot, $venueSchedule, $studentCount) {
                            if ($venue->name === 'Remote') return false;
                            
                            $slotKey = $selectedSlot->day . '_' . $selectedSlot->start_time . '_' . $selectedSlot->end_time;
                            $venueKey = $venue->id . '_' . $slotKey;
                            
                            return !isset($venueSchedule[$venueKey]) && $venue->capacity >= $studentCount;
                        });

                        if ($availableVenues->isEmpty()) {
                            Log::warning("No available venues for unit {$unit->code} on {$selectedSlot->day} at {$selectedSlot->start_time}");
                            continue; // Skip this session instead of throwing exception
                        }

                        $selectedVenue = $availableVenues->random();
                    } else {
                        // For online sessions, use Remote venue or create virtual venue
                        $selectedVenue = $venues->where('name', 'Remote')->first();
                        if (!$selectedVenue) {
                            // Create a virtual venue object for online sessions
                            $selectedVenue = (object) [
                                'id' => 0,
                                'name' => 'Online Platform',
                                'location' => 'Virtual',
                                'capacity' => 1000
                            ];
                        }
                    }

                    // Create timetable entry
                    $timetableEntry = [
                        'day' => $selectedSlot->day,
                        'start_time' => $selectedSlot->start_time,
                        'end_time' => $selectedSlot->end_time,
                        'unit_id' => $unit->id,
                        'semester_id' => $semesterId,
                        'program_id' => $programId,
                        'venue' => $selectedVenue->name,
                        'location' => $selectedVenue->location,
                        'no' => $studentCount,
                        'lecturer' => $lecturerName,
                        'status' => $session['mode'], // Physical or Online
                        'group' => $groupId ? Group::find($groupId)->name : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $generatedTimetable[] = $timetableEntry;

                    // Mark lecturer as busy
                    $slotKey = $selectedSlot->day . '_' . $selectedSlot->start_time . '_' . $selectedSlot->end_time;
                    $lecturerSchedule[$lecturerCode][$slotKey] = true;

                    // Mark venue as busy for physical sessions
                    if ($session['mode'] === 'Physical' && isset($selectedVenue->id)) {
                        $venueKey = $selectedVenue->id . '_' . $slotKey;
                        $venueSchedule[$venueKey] = true;
                    }
                }
            }

            if (empty($generatedTimetable)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Could not generate any timetable entries. Please check if lecturers are assigned to units and sufficient time slots/venues are available.'
                ], 422);
            }

            // Save all timetable entries
            ClassTimetable::insert($generatedTimetable);

            DB::commit();

            $summary = [
                'total_sessions' => count($generatedTimetable),
                'units_processed' => $units->count(),
                'physical_sessions' => collect($generatedTimetable)->where('status', 'Physical')->count(),
                'online_sessions' => collect($generatedTimetable)->where('status', 'Online')->count(),
            ];

            Log::info('Timetable auto-generated successfully', [
                'entries_created' => count($generatedTimetable),
                'summary' => $summary
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Timetable auto-generated successfully! Created ' . count($generatedTimetable) . ' entries.',
                'summary' => $summary,
                'data' => $generatedTimetable
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', $e->validator->errors()->all()),
                'errors' => $e->validator->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Timetable generation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate timetable: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download the auto-generated timetable as PDF.
     */
    public function downloadPDF(Request $request)
    {
        try {
            $semesterId = $request->input('semester_id');
            $classId = $request->input('class_id');
            $groupId = $request->input('group_id');

            $query = ClassTimetable::query()
                ->join('units', 'class_timetable.unit_id', '=', 'units.id')
                ->join('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
                ->leftJoin('programs', 'class_timetable.program_id', '=', 'programs.id')
                ->select(
                    'class_timetable.day',
                    'units.code as unit_code',
                    'units.name as unit_name',
                    'units.credit_hours',
                    'semesters.name as semester_name',
                    'programs.name as program_name',
                    'class_timetable.group as group_name',
                    'class_timetable.start_time',
                    'class_timetable.end_time',
                    'class_timetable.venue',
                    'class_timetable.location',
                    'class_timetable.lecturer',
                    'class_timetable.status as delivery_mode',
                    'class_timetable.no as student_count'
                );

            if ($semesterId) {
                $query->where('class_timetable.semester_id', $semesterId);
            }
            if ($classId) {
                $query->where('class_timetable.class_id', $classId);
            }
            if ($groupId) {
                $query->where('class_timetable.group', Group::find($groupId)->name);
            }

            $classTimetables = $query->orderBy('class_timetable.day')
                ->orderBy('class_timetable.start_time')
                ->get()
                ->toArray();

            $pdf = Pdf::loadView('timetables/auto-generated-pdf', [
                'classTimetables' => $classTimetables,
                'title' => 'Auto-Generated Class Timetable',
                'generatedAt' => now()->format('Y-m-d H:i:s'),
                'semester' => $semesterId ? Semester::find($semesterId)->name : 'All Semesters',
                'program' => $request->input('program_id') ? Program::find($request->input('program_id'))->name : 'All Programs',
                'class' => $classId ? ClassModel::find($classId)->name : 'All Classes',
                'group' => $groupId ? Group::find($groupId)->name : 'All Groups',
            ]);

            $pdf->setPaper('a4', 'landscape');
            return $pdf->download('auto-generated-timetable-' . now()->format('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error downloading auto-generated timetables: ' . $e->getMessage());
            return back()->with('error', 'Failed to download PDF.');
        }
    }

    /**
     * Get timetable data for display (API endpoint)
     */
    public function getTimetableData(Request $request)
    {
        try {
            $semesterId = $request->get('semester_id');
            $programId = $request->get('program_id');
            $classId = $request->get('class_id');
            $groupName = null;
            
            if ($request->get('group_id')) {
                $group = Group::find($request->get('group_id'));
                $groupName = $group ? $group->name : null;
            }

            $query = ClassTimetable::with(['unit', 'semester', 'program'])
                ->where('semester_id', $semesterId);
                
            if ($programId) {
                $query->where('program_id', $programId);
            }
            
            if ($classId) {
                $query->where('class_id', $classId);
            }
            
            if ($groupName) {
                $query->where('group', $groupName);
            }

            $timetables = $query->orderBy('day')
                ->orderBy('start_time')
                ->get()
                ->map(function ($timetable) {
                    return [
                        'id' => $timetable->id,
                        'day' => $timetable->day,
                        'start_time' => $timetable->start_time,
                        'end_time' => $timetable->end_time,
                        'unit_code' => $timetable->unit->code,
                        'unit_name' => $timetable->unit->name,
                        'credit_hours' => $timetable->unit->credit_hours,
                        'venue' => $timetable->venue,
                        'location' => $timetable->location,
                        'student_count' => $timetable->no,
                        'lecturer' => $timetable->lecturer,
                        'delivery_mode' => $timetable->status,
                        'program_name' => $timetable->program ? $timetable->program->name : null,
                        'group' => $timetable->group,
                    ];
                });

            return response()->json($timetables);
        } catch (\Exception $e) {
            Log::error('Error fetching timetable data: ' . $e->getMessage());
            return response()->json([]);
        }
    }
}
