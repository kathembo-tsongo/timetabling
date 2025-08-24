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
use App\Models\Group;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class OptimizedClassTimetableController extends Controller
{
    // Enhanced scheduling constraints
    private const MAX_PHYSICAL_PER_DAY = 2;
    private const MAX_ONLINE_PER_DAY = 2;
    private const MIN_HOURS_PER_DAY = 2;
    private const MAX_HOURS_PER_DAY = 5;
    private const REQUIRE_MIXED_MODE = true;
    private const AVOID_CONSECUTIVE_SLOTS = true;
    private const MAX_ASSIGNMENT_ATTEMPTS = 100;
    private const OPTIMIZATION_ITERATIONS = 50;

    /**
     * Display a listing of the resource with enhanced conflict detection
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        \Log::info('Accessing optimized class timetable', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);

        if (!$user->can('manage-classtimetables')) {
            abort(403, 'Unauthorized action.');
        }

        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');

        // Enhanced query with optimization metrics
        $classTimetables = ClassTimetable::query()
            ->leftJoin('units', 'class_timetable.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
            ->leftJoin('classes', 'class_timetable.class_id', '=', 'classes.id')
            ->leftJoin('groups', 'class_timetable.group_id', '=', 'groups.id')
            ->leftJoin('programs', 'class_timetable.program_id', '=', 'programs.id')
            ->leftJoin('schools', 'class_timetable.school_id', '=', 'schools.id')
            ->leftJoin('class_time_slots', function ($join) {
                $join->on('class_timetable.day', '=', 'class_time_slots.day')
                    ->on('class_timetable.start_time', '=', 'class_time_slots.start_time')
                    ->on('class_timetable.end_time', '=', 'class_time_slots.end_time');
            })
            ->leftJoin('users', 'users.code', '=', 'class_timetable.lecturer')
            ->select(
                'class_timetable.id',
                'class_timetable.semester_id',
                'class_timetable.unit_id',
                'class_timetable.class_id',
                'class_timetable.group_id',
                'class_timetable.day',
                'class_timetable.start_time',
                'class_timetable.end_time',
                'class_timetable.teaching_mode',
                'class_timetable.venue',
                'class_timetable.location',
                'class_timetable.no',
                DB::raw("IF(users.id IS NOT NULL, CONCAT(users.first_name, ' ', users.last_name), class_timetable.lecturer) as lecturer"),
                'class_timetable.program_id',
                'class_timetable.school_id',
                'class_timetable.created_at',
                'class_timetable.updated_at',
                'units.code as unit_code',
                'units.name as unit_name',
                'units.credit_hours',
                'semesters.name as semester_name',
                'classes.name as class_name',
                'groups.name as group_name',
                'programs.code as program_code',
                'programs.name as program_name',
                'schools.code as school_code',
                'schools.name as school_name',
                'class_time_slots.status'
            )
            ->when($request->has('search') && $request->search !== '', function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('class_timetable.day', 'like', "%{$search}%")
                      ->orWhere('units.code', 'like', "%{$search}%")
                      ->orWhere('units.name', 'like', "%{$search}%")
                      ->orWhere(DB::raw("CONCAT(users.first_name, ' ', users.last_name)"), 'like', "%{$search}%")
                      ->orWhere('class_timetable.venue', 'like', "%{$search}%");
                });
            })
            ->orderBy('class_timetable.day')
            ->orderBy('class_timetable.start_time')
            ->paginate($request->get('per_page', 10));

        // Get all necessary data for the form
        $lecturers = User::role('Lecturer')
            ->select('id', 'code', DB::raw("CONCAT(first_name, ' ', last_name) as name"))
            ->get();

        $semesters = Semester::all();
        $classrooms = Classroom::all();
        $classtimeSlots = ClassTimeSlot::all();
        $allUnits = Unit::select('id', 'code', 'name', 'semester_id', 'credit_hours')->get();
        $allEnrollments = Enrollment::select('id', 'student_code', 'unit_id', 'semester_id', 'lecturer_code')->get();

        // Enhanced unit processing with optimization metrics
        $enrollmentsByUnitAndSemester = [];
        foreach ($allEnrollments as $enrollment) {
            $key = $enrollment->unit_id . '_' . $enrollment->semester_id;
            if (!isset($enrollmentsByUnitAndSemester[$key])) {
                $enrollmentsByUnitAndSemester[$key] = [];
            }
            $enrollmentsByUnitAndSemester[$key][] = $enrollment;
        }

        $unitsBySemester = [];
        foreach ($semesters as $semester) {
            $unitIdsInSemester = Enrollment::where('semester_id', $semester->id)
                ->distinct('unit_id')
                ->pluck('unit_id')
                ->toArray();

            $unitsInSemester = Unit::whereIn('id', $unitIdsInSemester)
                ->select('id', 'code', 'name', 'credit_hours')
                ->get()
                ->map(function ($unit) use ($semester, $enrollmentsByUnitAndSemester) {
                    $unit->semester_id = $semester->id;
                    $key = $unit->id . '_' . $semester->id;
                    $unitEnrollments = $enrollmentsByUnitAndSemester[$key] ?? [];
                    $unit->student_count = count($unitEnrollments);

                    $lecturerCode = null;
                    foreach ($unitEnrollments as $enrollment) {
                        if ($enrollment->lecturer_code) {
                            $lecturerCode = $enrollment->lecturer_code;
                            break;
                        }
                    }

                    if ($lecturerCode) {
                        $lecturer = User::where('code', $lecturerCode)->first();
                        if ($lecturer) {
                            $unit->lecturer_code = $lecturerCode;
                            $unit->lecturer_name = $lecturer->first_name . ' ' . $lecturer->last_name;
                        }
                    }
                    return $unit;
                });

            $unitsBySemester[$semester->id] = $unitsInSemester;
        }

        $unitsWithSemesters = collect();
        foreach ($unitsBySemester as $semesterId => $units) {
            $unitsWithSemesters = $unitsWithSemesters->concat($units);
        }

        $classes = ClassModel::select('id', 'name')->get();
        $groups = Group::select('id', 'name', 'class_id')->get();
        $programs = DB::table('programs')->select('id', 'code', 'name')->get();
        $schools = DB::table('schools')->select('id', 'name', 'code')->get();

        return Inertia::render('ClassTimetables/Index', [
            'classTimetables' => $classTimetables,
            'lecturers' => $lecturers,
            'perPage' => $perPage,
            'search' => $search,
            'semesters' => $semesters,
            'classrooms' => $classrooms,
            'classtimeSlots' => $classtimeSlots,
            'units' => $unitsWithSemesters,
            'enrollments' => $allEnrollments,
            'classes' => $classes,
            'groups' => $groups,
            'programs' => $programs,
            'schools' => $schools,
            'constraints' => [
                'maxPhysicalPerDay' => self::MAX_PHYSICAL_PER_DAY,
                'maxOnlinePerDay' => self::MAX_ONLINE_PER_DAY,
                'minHoursPerDay' => self::MIN_HOURS_PER_DAY,
                'maxHoursPerDay' => self::MAX_HOURS_PER_DAY,
                'requireMixedMode' => self::REQUIRE_MIXED_MODE,
                'avoidConsecutiveSlots' => self::AVOID_CONSECUTIVE_SLOTS,
                'maxAssignmentAttempts' => self::MAX_ASSIGNMENT_ATTEMPTS,
            ],
            'can' => [
                'create' => $user->can('create-classtimetables'),
                'edit' => $user->can('update-classtimetables'),
                'delete' => $user->can('delete-classtimetables'),
                'process' => $user->can('process-classtimetables'),
                'solve_conflicts' => $user->can('solve-class-conflicts'),
                'download' => $user->can('download-classtimetables'),
            ],
        ]);
    }

    /**
     * Enhanced store method with conflict-free optimization
     */
    public function store(Request $request)
    {
        $request->validate([
            'day' => 'nullable|string',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'group_id' => 'nullable|exists:groups,id',
            'venue' => 'nullable|string',
            'location' => 'nullable|string',
            'no' => 'required|integer|min:1',
            'lecturer' => 'required|string',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'teaching_mode' => 'nullable|in:physical,online',
            'program_id' => 'nullable|exists:programs,id',
            'school_id' => 'nullable|exists:schools,id',
        ]);

        try {
            \Log::info('Creating optimized class timetable with data:', $request->all());

            $unit = Unit::findOrFail($request->unit_id);
            $class = ClassModel::find($request->class_id);
            $programId = $request->program_id ?: ($class ? $class->program_id : null);
            $schoolId = $request->school_id ?: ($class ? $class->school_id : null);

            // Check if random time slot assignment is requested
            $isRandomTimeSlot = empty($request->day) || empty($request->start_time) || 
                              empty($request->end_time) || $request->start_time === 'Random Time Slot (auto-assign)';

            if ($isRandomTimeSlot) {
                // Use optimized credit-based assignment
                return $this->createOptimizedCreditBasedTimetable($request, $unit, $programId, $schoolId);
            } else {
                // Use single time slot assignment with validation
                return $this->createValidatedSingleTimetable($request, $unit, $programId, $schoolId);
            }

        } catch (\Exception $e) {
            \Log::error('Failed to create optimized class timetable: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e->getTraceAsString()
            ]);

            return $this->handleError($request, 'Failed to create class timetable: ' . $e->getMessage());
        }
    }

    /**
     * ✅ ENHANCED: Create credit-based timetable with conflict-free optimization
     */
    private function createOptimizedCreditBasedTimetable(Request $request, Unit $unit, $programId, $schoolId)
    {
        $creditHours = $unit->credit_hours;
        $sessions = $this->getOptimalSessionsForCredits($creditHours);
        
        \Log::info('Creating optimized conflict-free timetable', [
            'unit_code' => $unit->code,
            'credit_hours' => $creditHours,
            'sessions' => $sessions
        ]);

        // Initialize optimization state
        $optimizationState = [
            'lecturer_schedule' => [],
            'venue_schedule' => [],
            'group_schedule' => [],
            'daily_constraints' => []
        ];

        $createdTimetables = [];
        $allAttempts = [];

        // Generate multiple complete assignment attempts
        for ($attempt = 1; $attempt <= self::MAX_ASSIGNMENT_ATTEMPTS; $attempt++) {
            $attemptResult = $this->attemptCompleteAssignment($request, $unit, $sessions, $programId, $schoolId, $optimizationState);
            
            if ($attemptResult['success'] && count($attemptResult['timetables']) === count($sessions)) {
                $allAttempts[] = $attemptResult;
                
                // If we have a perfect assignment, use it immediately
                if ($attemptResult['optimization_score'] >= 95) {
                    $createdTimetables = $attemptResult['timetables'];
                    \Log::info('Perfect assignment found', [
                        'attempt' => $attempt,
                        'score' => $attemptResult['optimization_score']
                    ]);
                    break;
                }
            }
        }

        // If no perfect assignment found, select the best one
        if (empty($createdTimetables) && !empty($allAttempts)) {
            $bestAttempt = collect($allAttempts)->sortByDesc('optimization_score')->first();
            $createdTimetables = $bestAttempt['timetables'];
            \Log::info('Best assignment selected', [
                'score' => $bestAttempt['optimization_score'],
                'total_attempts' => count($allAttempts)
            ]);
        }

        // If still no success, try fallback assignment
        if (empty($createdTimetables)) {
            \Log::warning('All optimization attempts failed, using fallback');
            return $this->createFallbackAssignment($request, $unit, $sessions, $programId, $schoolId);
        }

        // Apply final optimization
        $this->applyFinalOptimization($createdTimetables);

        $optimizationScore = $this->calculateOptimizationScore($createdTimetables);
        $successMessage = count($createdTimetables) . " optimized conflict-free sessions created for {$unit->code} ({$creditHours} credit hours). Optimization Score: {$optimizationScore}%";

        return $this->handleSuccess($request, $successMessage, $createdTimetables);
    }

    /**
     * ✅ NEW: Attempt complete conflict-free assignment for all sessions
     */
    private function attemptCompleteAssignment(Request $request, Unit $unit, array $sessions, $programId, $schoolId, &$optimizationState)
    {
        $tempTimetables = [];
        $tempState = $optimizationState;
        
        foreach ($sessions as $sessionIndex => $session) {
            $sessionResult = $this->assignOptimalSessionSlot($request, $unit, $session, $sessionIndex + 1, $programId, $schoolId, $tempState);
            
            if (!$sessionResult['success']) {
                // This attempt failed, return failure
                return [
                    'success' => false,
                    'message' => $sessionResult['message'],
                    'failed_at_session' => $sessionIndex + 1
                ];
            }
            
            $tempTimetables[] = $sessionResult['timetable'];
            
            // Update temporary state to track conflicts for next session
            $this->updateOptimizationState($tempState, $sessionResult['timetable']);
        }

        // Calculate optimization score for this complete assignment
        $optimizationScore = $this->calculateAssignmentScore($tempTimetables, $tempState);

        return [
            'success' => true,
            'timetables' => $tempTimetables,
            'optimization_score' => $optimizationScore,
            'state' => $tempState
        ];
    }

    /**
     * ✅ NEW: Assign optimal session slot with advanced conflict avoidance
     */
    private function assignOptimalSessionSlot(Request $request, Unit $unit, array $session, int $sessionNumber, $programId, $schoolId, array &$state)
    {
        $sessionType = $session['type'];
        $requiredDuration = $session['duration'];
        
        // Get all possible time slots for this duration
        $candidateSlots = $this->getCandidateTimeSlots($requiredDuration);
        
        if ($candidateSlots->isEmpty()) {
            return [
                'success' => false,
                'message' => "No time slots available for {$requiredDuration}-hour sessions"
            ];
        }

        // Score and rank all candidate slots
        $scoredSlots = $candidateSlots->map(function ($slot) use ($request, $sessionType, $state) {
            return [
                'slot' => $slot,
                'score' => $this->scoreTimeSlot($slot, $request->lecturer, $request->group_id, $sessionType, $state)
            ];
        })->sortByDesc('score');

        // Try slots in order of score until we find one that works
        foreach ($scoredSlots as $scoredSlot) {
            $slot = $scoredSlot['slot'];
            
            if ($scoredSlot['score'] < 0) {
                continue; // Skip slots with negative scores (hard conflicts)
            }

            // Try to assign venue for this slot
            $venueResult = $this->assignOptimalVenue($request->no, $slot->day, $slot->start_time, $slot->end_time, $sessionType, $state);
            
            if (!$venueResult['success']) {
                continue; // Try next slot
            }

            // Verify no conflicts would be created
            if ($this->wouldCreateAnyConflict($request, $slot, $venueResult, $state)) {
                continue; // Try next slot
            }

            // Create the timetable entry
            $classTimetable = ClassTimetable::create([
                'day' => $slot->day,
                'unit_id' => $unit->id,
                'semester_id' => $request->semester_id,
                'class_id' => $request->class_id,
                'group_id' => $request->group_id ?: null,
                'venue' => $venueResult['venue'],
                'location' => $venueResult['location'],
                'no' => $request->no,
                'lecturer' => $request->lecturer,
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
                'teaching_mode' => $venueResult['teaching_mode'],
                'program_id' => $programId,
                'school_id' => $schoolId,
            ]);

            \Log::info("Optimal session {$sessionNumber} assigned", [
                'timetable_id' => $classTimetable->id,
                'unit_code' => $unit->code,
                'session_type' => $sessionType,
                'day' => $slot->day,
                'time' => "{$slot->start_time}-{$slot->end_time}",
                'venue' => $venueResult['venue'],
                'score' => $scoredSlot['score']
            ]);

            return [
                'success' => true,
                'timetable' => $classTimetable,
                'score' => $scoredSlot['score']
            ];
        }

        return [
            'success' => false,
            'message' => "No conflict-free slots available for session {$sessionNumber} ({$sessionType}, {$requiredDuration}h)"
        ];
    }

    /**
     * ✅ NEW: Advanced time slot scoring with constraint optimization
     */
    private function scoreTimeSlot($slot, $lecturer, $groupId, $sessionType, array $state)
    {
        $score = 100; // Base score

        // Hard constraint checks (negative scores = disqualify)
        
        // Check lecturer conflicts
        if ($this->hasLecturerConflict($lecturer, $slot, $state)) {
            return -1000;
        }

        // Check existing database conflicts for lecturer
        $dbLecturerConflict = ClassTimetable::where('lecturer', $lecturer)
            ->where('day', $slot->day)
            ->where(function ($query) use ($slot) {
                $query->where(function ($q) use ($slot) {
                    $q->where('start_time', '<', $slot->end_time)
                      ->where('end_time', '>', $slot->start_time);
                });
            })
            ->exists();

        if ($dbLecturerConflict) {
            return -1000;
        }

        // Check group daily constraints
        if ($groupId) {
            $constraintCheck = $this->checkGroupDailyConstraints($groupId, $slot, $sessionType, $state);
            if (!$constraintCheck['valid']) {
                return -500;
            }
            $score += $constraintCheck['bonus'];
        }

        // Soft preferences (positive/negative adjustments)
        
        // Prefer earlier slots for physical classes
        if ($sessionType === 'physical') {
            $hour = (int) explode(':', $slot->start_time)[0];
            $score += (16 - $hour) * 2; // Earlier is better
        }

        // Prefer later slots for online classes
        if ($sessionType === 'online') {
            $hour = (int) explode(':', $slot->start_time)[0];
            $score += ($hour - 8) * 1; // Later is better
        }

        // Prefer spreading sessions across different days
        $daySpreadBonus = $this->calculateDaySpreadBonus($slot->day, $state);
        $score += $daySpreadBonus;

        // Avoid consecutive slots if constraint is enabled
        if (self::AVOID_CONSECUTIVE_SLOTS) {
            $consecutivePenalty = $this->calculateConsecutivePenalty($slot, $groupId, $state);
            $score -= $consecutivePenalty;
        }

        // Mixed mode bonus
        if (self::REQUIRE_MIXED_MODE && $groupId) {
            $mixedModeBonus = $this->calculateMixedModeBonus($slot->day, $sessionType, $groupId, $state);
            $score += $mixedModeBonus;
        }

        return $score;
    }

    /**
     * ✅ NEW: Check group daily constraints with detailed validation
     */
    private function checkGroupDailyConstraints($groupId, $slot, $sessionType, array $state)
    {
        $dayKey = "{$groupId}_{$slot->day}";
        $dayStats = $state['daily_constraints'][$dayKey] ?? [
            'physical_count' => 0,
            'online_count' => 0,
            'total_hours' => 0
        ];

        // Add existing database constraints
        $existingDayStats = $this->getExistingDayStats($groupId, $slot->day);
        $dayStats['physical_count'] += $existingDayStats['physical_count'];
        $dayStats['online_count'] += $existingDayStats['online_count'];
        $dayStats['total_hours'] += $existingDayStats['total_hours'];

        $slotDuration = $this->calculateSlotDuration($slot->start_time, $slot->end_time);

        // Check hard constraints
        if ($sessionType === 'physical' && $dayStats['physical_count'] >= self::MAX_PHYSICAL_PER_DAY) {
            return ['valid' => false, 'reason' => 'max_physical_exceeded'];
        }

        if ($sessionType === 'online' && $dayStats['online_count'] >= self::MAX_ONLINE_PER_DAY) {
            return ['valid' => false, 'reason' => 'max_online_exceeded'];
        }

        if ($dayStats['total_hours'] + $slotDuration > self::MAX_HOURS_PER_DAY) {
            return ['valid' => false, 'reason' => 'max_hours_exceeded'];
        }

        // Calculate bonus for good distribution
        $bonus = 0;
        
        // Bonus for reaching minimum hours
        if ($dayStats['total_hours'] + $slotDuration >= self::MIN_HOURS_PER_DAY) {
            $bonus += 10;
        }

        // Bonus for balanced physical/online distribution
        if ($sessionType === 'physical' && $dayStats['online_count'] > 0) {
            $bonus += 15; // Good for balance
        }
        if ($sessionType === 'online' && $dayStats['physical_count'] > 0) {
            $bonus += 15; // Good for balance
        }

        return ['valid' => true, 'bonus' => $bonus];
    }

    /**
     * ✅ NEW: Get existing day statistics from database
     */
    private function getExistingDayStats($groupId, $day)
    {
        $existingSlots = ClassTimetable::where('group_id', $groupId)
            ->where('day', $day)
            ->get();

        return [
            'physical_count' => $existingSlots->where('teaching_mode', 'physical')->count(),
            'online_count' => $existingSlots->where('teaching_mode', 'online')->count(),
            'total_hours' => $existingSlots->sum(function ($slot) {
                return $this->calculateSlotDuration($slot->start_time, $slot->end_time);
            })
        ];
    }

    /**
     * ✅ NEW: Assign optimal venue with advanced conflict checking
     */
    private function assignOptimalVenue($studentCount, $day, $startTime, $endTime, $sessionType, array $state)
    {
        // Get candidate venues
        $candidateVenues = Classroom::where('capacity', '>=', $studentCount)->get();

        if ($candidateVenues->isEmpty()) {
            return [
                'success' => false,
                'message' => "No venues with sufficient capacity for {$studentCount} students"
            ];
        }

        // Filter by session type preference
        $preferredVenues = $candidateVenues->filter(function ($venue) use ($sessionType) {
            $venueInfo = $this->determineTeachingModeAndLocation($venue->name);
            return $venueInfo['teaching_mode'] === $sessionType;
        });

        // Use preferred venues if available, otherwise any suitable venue
        $availableVenues = $preferredVenues->isNotEmpty() ? $preferredVenues : $candidateVenues;

        // Score and rank venues
        $scoredVenues = $availableVenues->map(function ($venue) use ($day, $startTime, $endTime, $state) {
            return [
                'venue' => $venue,
                'score' => $this->scoreVenue($venue, $day, $startTime, $endTime, $state)
            ];
        })->sortByDesc('score');

        // Select the best available venue
        foreach ($scoredVenues as $scoredVenue) {
            if ($scoredVenue['score'] >= 0) {
                $venue = $scoredVenue['venue'];
                $venueInfo = $this->determineTeachingModeAndLocation($venue->name);

                return [
                    'success' => true,
                    'venue' => $venue->name,
                    'location' => $venueInfo['location'],
                    'teaching_mode' => $venueInfo['teaching_mode'],
                    'score' => $scoredVenue['score']
                ];
            }
        }

        return [
            'success' => false,
            'message' => "No conflict-free venues available for the specified time"
        ];
    }

    /**
     * ✅ NEW: Score venue based on conflicts and preferences
     */
    private function scoreVenue($venue, $day, $startTime, $endTime, array $state)
    {
        $score = 100;
        $venueInfo = $this->determineTeachingModeAndLocation($venue->name);

        // Online venues can handle multiple sessions
        if ($venueInfo['teaching_mode'] === 'online') {
            return $score + 50; // Bonus for online venues
        }

        // Check for venue conflicts in state
        $venueKey = "{$venue->name}_{$day}";
        if (isset($state['venue_schedule'][$venueKey])) {
            foreach ($state['venue_schedule'][$venueKey] as $booking) {
                if ($this->timeSlotsOverlapDirect($startTime, $endTime, $booking['start_time'], $booking['end_time'])) {
                    return -1000; // Hard conflict
                }
            }
        }

        // Check existing database conflicts
        $hasDbConflict = ClassTimetable::where('venue', $venue->name)
            ->where('day', $day)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
                });
            })
            ->exists();

        if ($hasDbConflict) {
            return -1000; // Hard conflict
        }

        // Bonus for larger capacity (more flexibility)
        $score += min($venue->capacity / 10, 20);

        return $score;
    }

    /**
     * ✅ NEW: Fallback assignment when optimization fails
     */
    private function createFallbackAssignment(Request $request, Unit $unit, array $sessions, $programId, $schoolId)
    {
        \Log::warning('Using fallback assignment for unit', ['unit_code' => $unit->code]);

        $createdTimetables = [];
        $errors = [];
        
        // Use simpler assignment strategy
        foreach ($sessions as $sessionIndex => $session) {
            $result = $this->createSimpleSessionAssignment($request, $unit, $session, $sessionIndex + 1, $programId, $schoolId);
            if ($result['success']) {
                $createdTimetables[] = $result['timetable'];
            } else {
                $errors[] = $result['message'];
            }
        }

        if (empty($createdTimetables)) {
            return [
                'success' => false,
                'message' => 'Failed to create any timetable sessions even with fallback strategy. Errors: ' . implode('; ', $errors)
            ];
        }

        $successMessage = count($createdTimetables) . " sessions created using fallback strategy for {$unit->code}";
        if (!empty($errors)) {
            $successMessage .= ". Some sessions failed: " . count($errors);
        }

        return $this->handleSuccess($request, $successMessage, $createdTimetables);
    }

    /**
     * ✅ NEW: Create validated single timetable entry
     */
    private function createValidatedSingleTimetable(Request $request, Unit $unit, $programId, $schoolId)
    {
        // Validate for conflicts before creating
        $conflicts = $this->validateSingleSlotConflicts($request);
        
        if (!empty($conflicts)) {
            $conflictMessage = 'Conflicts detected: ' . implode(', ', $conflicts);
            return $this->handleError($request, $conflictMessage);
        }

        $classTimetable = ClassTimetable::create([
            'day' => $request->day,
            'unit_id' => $unit->id,
            'semester_id' => $request->semester_id,
            'class_id' => $request->class_id,
            'group_id' => $request->group_id ?: null,
            'venue' => $request->venue,
            'location' => $request->location,
            'no' => $request->no,
            'lecturer' => $request->lecturer,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'teaching_mode' => $request->teaching_mode ?? 'physical',
            'program_id' => $programId,
            'school_id' => $schoolId,
        ]);

        \Log::info('Validated single timetable entry created', [
            'timetable_id' => $classTimetable->id,
            'unit_code' => $unit->code,
            'day' => $request->day,
            'time' => "{$request->start_time}-{$request->end_time}",
            'venue' => $request->venue,
        ]);

        return $this->handleSuccess($request, 'Class timetable created successfully with validation.');
    }

    /**
     * ✅ NEW: Validate single slot for conflicts
     */
    private function validateSingleSlotConflicts(Request $request)
    {
        $conflicts = [];

        // Check lecturer conflicts
        $lecturerConflict = ClassTimetable::where('lecturer', $request->lecturer)
            ->where('day', $request->day)
            ->where(function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('start_time', '<', $request->end_time)
                      ->where('end_time', '>', $request->start_time);
                });
            })
            ->exists();

        if ($lecturerConflict) {
            $conflicts[] = "Lecturer {$request->lecturer} has a conflict on {$request->day} at {$request->start_time}-{$request->end_time}";
        }

        // Check venue conflicts (skip for online venues)
        if ($request->venue && strtolower(trim($request->venue)) !== 'remote') {
            $venueConflict = ClassTimetable::where('venue', $request->venue)
                ->where('day', $request->day)
                ->where(function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        $q->where('start_time', '<', $request->end_time)
                          ->where('end_time', '>', $request->start_time);
                    });
                })
                ->exists();

            if ($venueConflict) {
                $conflicts[] = "Venue {$request->venue} has a conflict on {$request->day} at {$request->start_time}-{$request->end_time}";
            }
        }

        // Check group conflicts
        if ($request->group_id) {
            $groupConflict = ClassTimetable::where('group_id', $request->group_id)
                ->where('day', $request->day)
                ->where(function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        $q->where('start_time', '<', $request->end_time)
                          ->where('end_time', '>', $request->start_time);
                    });
                })
                ->exists();

            if ($groupConflict) {
                $conflicts[] = "Group has a conflict on {$request->day} at {$request->start_time}-{$request->end_time}";
            }
        }

        return $conflicts;
    }

    /**
     * ✅ ENHANCED: Conflict detection API endpoint
     */
    public function detectConflicts(Request $request)
    {
        try {
            $semesterId = $request->input('semester_id');
            $classId = $request->input('class_id');
            $groupId = $request->input('group_id');

            $query = ClassTimetable::query();
            
            if ($semesterId) {
                $query->where('semester_id', $semesterId);
            }
            
            if ($classId) {
                $query->where('class_id', $classId);
            }
            
            if ($groupId) {
                $query->where('group_id', $groupId);
            }

            $timetables = $query->with(['unit', 'group'])->get();

            $conflicts = [
                'lecturer_conflicts' => $this->detectLecturerConflicts($timetables),
                'venue_conflicts' => $this->detectVenueConflicts($timetables),
                'group_conflicts' => $this->detectGroupConflicts($timetables),
                'constraint_violations' => $this->detectConstraintViolations($timetables),
            ];

            $optimizationScore = $this->calculateCurrentOptimizationScore($timetables);

            return response()->json([
                'success' => true,
                'conflicts' => $conflicts,
                'total_conflicts' => array_sum(array_map('count', $conflicts)),
                'optimization_score' => $optimizationScore,
                'recommendations' => $this->generateOptimizationRecommendations($conflicts)
            ]);

        } catch (\Exception $e) {
            \Log::error('Error detecting conflicts: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * ✅ NEW: Generate optimization recommendations
     */
    private function generateOptimizationRecommendations(array $conflicts)
    {
        $recommendations = [];

        if (!empty($conflicts['lecturer_conflicts'])) {
            $recommendations[] = [
                'type' => 'lecturer_optimization',
                'message' => 'Consider redistributing lecturer schedules across different time slots',
                'priority' => 'high'
            ];
        }

        if (!empty($conflicts['venue_conflicts'])) {
            $recommendations[] = [
                'type' => 'venue_optimization',
                'message' => 'Consider using alternative venues or converting some sessions to online',
                'priority' => 'medium'
            ];
        }

        if (!empty($conflicts['constraint_violations'])) {
            $recommendations[] = [
                'type' => 'constraint_optimization',
                'message' => 'Redistribute sessions to better comply with daily constraints',
                'priority' => 'high'
            ];
        }

        return $recommendations;
    }

    // ✅ Helper methods for optimization
    private function getCandidateTimeSlots($requiredDuration)
    {
        return ClassTimeSlot::whereRaw('TIMESTAMPDIFF(HOUR, start_time, end_time) >= ?', [$requiredDuration])->get();
    }

    private function hasLecturerConflict($lecturer, $slot, array $state)
    {
        $lecturerKey = "{$lecturer}_{$slot->day}";
        if (!isset($state['lecturer_schedule'][$lecturerKey])) {
            return false;
        }

        foreach ($state['lecturer_schedule'][$lecturerKey] as $booking) {
            if ($this->timeSlotsOverlapDirect($slot->start_time, $slot->end_time, $booking['start_time'], $booking['end_time'])) {
                return true;
            }
        }

        return false;
    }

    private function timeSlotsOverlapDirect($start1, $end1, $start2, $end2)
    {
        return $start1 < $end2 && $start2 < $end1;
    }

    private function calculateSlotDuration($startTime, $endTime)
    {
        return \Carbon\Carbon::parse($startTime)->diffInHours(\Carbon\Carbon::parse($endTime));
    }

    private function updateOptimizationState(array &$state, $timetable)
    {
        // Update lecturer schedule
        $lecturerKey = "{$timetable->lecturer}_{$timetable->day}";
        if (!isset($state['lecturer_schedule'][$lecturerKey])) {
            $state['lecturer_schedule'][$lecturerKey] = [];
        }
        $state['lecturer_schedule'][$lecturerKey][] = [
            'start_time' => $timetable->start_time,
            'end_time' => $timetable->end_time,
            'timetable_id' => $timetable->id
        ];

        // Update venue schedule
        if ($timetable->venue && strtolower($timetable->venue) !== 'remote') {
            $venueKey = "{$timetable->venue}_{$timetable->day}";
            if (!isset($state['venue_schedule'][$venueKey])) {
                $state['venue_schedule'][$venueKey] = [];
            }
            $state['venue_schedule'][$venueKey][] = [
                'start_time' => $timetable->start_time,
                'end_time' => $timetable->end_time,
                'timetable_id' => $timetable->id
            ];
        }

        // Update group daily constraints
        if ($timetable->group_id) {
            $dayKey = "{$timetable->group_id}_{$timetable->day}";
            if (!isset($state['daily_constraints'][$dayKey])) {
                $state['daily_constraints'][$dayKey] = [
                    'physical_count' => 0,
                    'online_count' => 0,
                    'total_hours' => 0
                ];
            }

            $duration = $this->calculateSlotDuration($timetable->start_time, $timetable->end_time);
            $state['daily_constraints'][$dayKey]['total_hours'] += $duration;

            if ($timetable->teaching_mode === 'physical') {
                $state['daily_constraints'][$dayKey]['physical_count']++;
            } else {
                $state['daily_constraints'][$dayKey]['online_count']++;
            }
        }
    }

    private function calculateAssignmentScore(array $timetables, array $state)
    {
        $score = 100;
        $penalties = 0;
        $bonuses = 0;

        // Check constraint compliance
        foreach ($state['daily_constraints'] as $dayKey => $stats) {
            // Penalty for exceeding limits
            if ($stats['physical_count'] > self::MAX_PHYSICAL_PER_DAY) {
                $penalties += ($stats['physical_count'] - self::MAX_PHYSICAL_PER_DAY) * 20;
            }
            if ($stats['online_count'] > self::MAX_ONLINE_PER_DAY) {
                $penalties += ($stats['online_count'] - self::MAX_ONLINE_PER_DAY) * 20;
            }
            if ($stats['total_hours'] > self::MAX_HOURS_PER_DAY) {
                $penalties += ($stats['total_hours'] - self::MAX_HOURS_PER_DAY) * 15;
            }

            // Bonus for meeting minimum hours
            if ($stats['total_hours'] >= self::MIN_HOURS_PER_DAY) {
                $bonuses += 10;
            }

            // Bonus for mixed mode
            if (self::REQUIRE_MIXED_MODE && $stats['physical_count'] > 0 && $stats['online_count'] > 0) {
                $bonuses += 15;
            }
        }

        // Bonus for day distribution
        $uniqueDays = collect($timetables)->pluck('day')->unique()->count();
        $bonuses += $uniqueDays * 5;

        // Bonus for optimal time distribution
        $timeDistributionBonus = $this->calculateTimeDistributionBonus($timetables);
        $bonuses += $timeDistributionBonus;

        return max(0, $score - $penalties + $bonuses);
    }

    private function applyFinalOptimization(array $timetables)
    {
        foreach ($timetables as $timetable) {
            \Log::info('Final optimized assignment', [
                'timetable_id' => $timetable->id,
                'day' => $timetable->day,
                'time' => "{$timetable->start_time}-{$timetable->end_time}",
                'venue' => $timetable->venue,
                'teaching_mode' => $timetable->teaching_mode
            ]);
        }
    }

    private function calculateOptimizationScore(array $timetables)
    {
        if (empty($timetables)) return 0;

        $score = 100;
        $penalties = 0;

        // Group by day and group for constraint checking
        $groupedByDay = collect($timetables)->groupBy(function ($item) {
            return $item->group_id . '_' . $item->day;
        });

        foreach ($groupedByDay as $dayGroup) {
            $physicalCount = $dayGroup->where('teaching_mode', 'physical')->count();
            $onlineCount = $dayGroup->where('teaching_mode', 'online')->count();
            $totalHours = $dayGroup->sum(function ($item) {
                return $this->calculateSlotDuration($item->start_time, $item->end_time);
            });

            // Apply penalties for constraint violations
            if ($physicalCount > self::MAX_PHYSICAL_PER_DAY) {
                $penalties += ($physicalCount - self::MAX_PHYSICAL_PER_DAY) * 15;
            }
            if ($onlineCount > self::MAX_ONLINE_PER_DAY) {
                $penalties += ($onlineCount - self::MAX_ONLINE_PER_DAY) * 15;
            }
            if ($totalHours > self::MAX_HOURS_PER_DAY) {
                $penalties += ($totalHours - self::MAX_HOURS_PER_DAY) * 10;
            }
        }

        return max(0, $score - $penalties);
    }

    private function calculateDaySpreadBonus($day, array $state)
    {
        $usedDays = [];
        foreach ($state['daily_constraints'] as $dayKey => $stats) {
            $usedDays[] = explode('_', $dayKey)[1];
        }
        
        $uniqueDays = array_unique($usedDays);
        return in_array($day, $uniqueDays) ? 0 : 10;
    }

    private function calculateConsecutivePenalty($slot, $groupId, array $state)
    {
        if (!$groupId) return 0;

        $groupKey = "{$groupId}_{$slot->day}";
        if (!isset($state['group_schedule'][$groupKey])) {
            return 0;
        }

        foreach ($state['group_schedule'][$groupKey] as $booking) {
            if ($booking['end_time'] === $slot->start_time || $booking['start_time'] === $slot->end_time) {
                return 30;
            }
        }

        return 0;
    }

    private function calculateMixedModeBonus($day, $sessionType, $groupId, array $state)
    {
        $dayKey = "{$groupId}_{$day}";
        if (!isset($state['daily_constraints'][$dayKey])) {
            return 0;
        }

        $stats = $state['daily_constraints'][$dayKey];
        
        if ($sessionType === 'physical' && $stats['online_count'] > 0) {
            return 20;
        }
        if ($sessionType === 'online' && $stats['physical_count'] > 0) {
            return 20;
        }

        return 0;
    }

    private function calculateTimeDistributionBonus(array $timetables)
    {
        $timeSlots = collect($timetables)->groupBy('day');
        $distributionScore = 0;

        foreach ($timeSlots as $day => $dayTimetables) {
            if ($dayTimetables->count() <= 3) {
                $distributionScore += 5;
            }
        }

        return $distributionScore;
    }

    private function wouldCreateAnyConflict(Request $request, $slot, array $venueResult, array $state)
    {
        return $this->hasLecturerConflict($request->lecturer, $slot, $state) ||
               $this->hasVenueConflict($venueResult['venue'], $slot, $state) ||
               $this->hasGroupConflict($request->group_id, $slot, $state);
    }

    private function hasVenueConflict($venue, $slot, array $state)
    {
        if (strtolower($venue) === 'remote') {
            return false;
        }

        $venueKey = "{$venue}_{$slot->day}";
        if (!isset($state['venue_schedule'][$venueKey])) {
            return false;
        }

        foreach ($state['venue_schedule'][$venueKey] as $booking) {
            if ($this->timeSlotsOverlapDirect($slot->start_time, $slot->end_time, $booking['start_time'], $booking['end_time'])) {
                return true;
            }
        }

        return false;
    }

    private function hasGroupConflict($groupId, $slot, array $state)
    {
        if (!$groupId) return false;

        $groupKey = "{$groupId}_{$slot->day}";
        if (!isset($state['group_schedule'][$groupKey])) {
            return false;
        }

        foreach ($state['group_schedule'][$groupKey] as $booking) {
            if ($this->timeSlotsOverlapDirect($slot->start_time, $slot->end_time, $booking['start_time'], $booking['end_time'])) {
                return true;
            }
        }

        return false;
    }

    private function getOptimalSessionsForCredits($creditHours)
    {
        $sessions = [];

        if ($creditHours >= 2) {
            $sessions[] = ['type' => 'physical', 'duration' => 2];
            $remaining = $creditHours - 2;
        } else {
            $remaining = $creditHours;
        }

        $isOnline = true;
        while ($remaining > 0) {
            $sessions[] = ['type' => $isOnline ? 'online' : 'physical', 'duration' => 1];
            $isOnline = !$isOnline;
            $remaining--;
        }

        return $sessions;
    }

    private function createSimpleSessionAssignment(Request $request, Unit $unit, array $session, int $sessionNumber, $programId, $schoolId)
    {
        $timeSlots = ClassTimeSlot::whereRaw('TIMESTAMPDIFF(HOUR, start_time, end_time) >= ?', [$session['duration']])->get();
        
        foreach ($timeSlots as $slot) {
            $venueResult = $this->assignOptimalVenue($request->no, $slot->day, $slot->start_time, $slot->end_time, $session['type'], []);
            
            if ($venueResult['success']) {
                $timetable = ClassTimetable::create([
                    'day' => $slot->day,
                    'unit_id' => $unit->id,
                    'semester_id' => $request->semester_id,
                    'class_id' => $request->class_id,
                    'group_id' => $request->group_id ?: null,
                    'venue' => $venueResult['venue'],
                    'location' => $venueResult['location'],
                    'no' => $request->no,
                    'lecturer' => $request->lecturer,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->end_time,
                    'teaching_mode' => $venueResult['teaching_mode'],
                    'program_id' => $programId,
                    'school_id' => $schoolId,
                ]);

                return ['success' => true, 'timetable' => $timetable];
            }
        }

        return ['success' => false, 'message' => "Could not assign session {$sessionNumber}"];
    }

    private function determineTeachingModeAndLocation($venue)
    {
        if (strtolower(trim($venue)) === 'remote') {
            return [
                'teaching_mode' => 'online',
                'location' => 'online'
            ];
        }
        
        $classroom = Classroom::where('name', $venue)->first();
        $location = $classroom ? $classroom->location : 'Physical';
        return [
            'teaching_mode' => 'physical',
            'location' => $location
        ];
    }

    // ✅ Helper methods for error and success handling
    private function handleError($request, $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => ['error' => $message]
            ], 500);
        }

        return redirect()->back()
            ->withErrors(['error' => $message])
            ->withInput();
    }

    private function handleSuccess($request, $message, $data = null)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $data
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    // ✅ Include all existing methods from original controller
    public function getUnitsByClass(Request $request)
    {
        try {
            $classId = $request->input('class_id');
            $semesterId = $request->input('semester_id');

            if (!$classId || !$semesterId) {
                return response()->json(['error' => 'Class ID and Semester ID are required.'], 400);
            }

            \Log::info('Fetching units for class', [
                'class_id' => $classId,
                'semester_id' => $semesterId
            ]);

            $units = [];
            
            $enrollmentColumns = DB::getSchemaBuilder()->getColumnListing('enrollments');
            
            if (in_array('class_id', $enrollmentColumns)) {
                $units = DB::table('enrollments')
                    ->join('units', 'enrollments.unit_id', '=', 'units.id')
                    ->where('enrollments.semester_id', $semesterId)
                    ->where('enrollments.class_id', $classId)
                    ->select('units.id', 'units.code', 'units.name', 'units.credit_hours')
                    ->distinct()
                    ->get()
                    ->toArray();
            }

            if (empty($units)) {
                $hasClassUnitTable = DB::getSchemaBuilder()->hasTable('class_unit');
                
                if ($hasClassUnitTable) {
                    $units = DB::table('class_unit')
                        ->join('units', 'class_unit.unit_id', '=', 'units.id')
                        ->where('class_unit.class_id', $classId)
                        ->where('units.semester_id', $semesterId)
                        ->select('units.id', 'units.code', 'units.name', 'units.credit_hours')
                        ->distinct()
                        ->get()
                        ->toArray();
                }
            }

            if (empty($units)) {
                $units = DB::table('units')
                    ->where('semester_id', $semesterId)
                    ->select('id', 'code', 'name', 'credit_hours')
                    ->get()
                    ->toArray();
            }

            $enhancedUnits = collect($units)->map(function ($unit) use ($semesterId, $classId) {
                $unitArray = is_object($unit) ? (array) $unit : $unit;
                
                $enrollmentQuery = Enrollment::where('unit_id', $unitArray['id'])
                    ->where('semester_id', $semesterId);

                $enrollmentColumns = DB::getSchemaBuilder()->getColumnListing('enrollments');
                if (in_array('class_id', $enrollmentColumns)) {
                    $enrollmentQuery->where('class_id', $classId);
                }

                $enrollments = $enrollmentQuery->get();
                $studentCount = $enrollments->count();

                $lecturerName = '';
                $lecturerEnrollment = $enrollments->whereNotNull('lecturer_code')->first();
                if ($lecturerEnrollment) {
                    $lecturer = User::where('code', $lecturerEnrollment->lecturer_code)->first();
                    if ($lecturer) {
                        $lecturerName = $lecturer->first_name . ' ' . $lecturer->last_name;
                    }
                }

                return [
                    'id' => $unitArray['id'],
                    'code' => $unitArray['code'],
                    'name' => $unitArray['name'],
                    'credit_hours' => $unitArray['credit_hours'] ?? 3,
                    'student_count' => $studentCount,
                    'lecturer_name' => $lecturerName,
                ];
            });

            return response()->json($enhancedUnits->values()->all());
            
        } catch (\Exception $e) {
            \Log::error('Error fetching units for class: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch units. Please try again.'], 500);
        }
    }

    // ✅ Include other essential methods
    public function update(Request $request, $id)
    {
        $timetable = ClassTimetable::findOrFail($id);
        $timetable->update($request->all());
        return redirect()->back()->with('success', 'Class timetable updated successfully.');
    }

    public function destroy($id)
    {
        try {
            $timetable = ClassTimetable::findOrFail($id);
            $timetable->delete();

            \Log::info('Class timetable deleted successfully', ['id' => $id]);
            return redirect()->back()->with('success', 'Class timetable deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to delete class timetable: ' . $e->getMessage(), ['id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to delete class timetable.'], 500);
        }
    }

    public function show($id)
    {
        try {
            $timetable = ClassTimetable::findOrFail($id);
            return response()->json($timetable);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch class timetable: ' . $e->getMessage(), ['id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch class timetable.'], 500);
        }
    }

    public function downloadPDF(Request $request)
    {
        try {
            if (!view()->exists('classtimetables.pdf')) {
                \Log::error('PDF template not found: classtimetables.pdf');
                return redirect()->back()->with('error', 'PDF template not found. Please contact the administrator.');
            }

            $query = ClassTimetable::query()
                ->join('units', 'class_timetable.unit_id', '=', 'units.id')
                ->join('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
                ->leftJoin('classes', 'class_timetable.class_id', '=', 'classes.id')
                ->leftJoin('groups', 'class_timetable.group_id', '=', 'groups.id')
                ->leftJoin('class_time_slots', function ($join) {
                    $join->on('class_timetable.day', '=', 'class_time_slots.day')
                        ->on('class_timetable.start_time', '=', 'class_time_slots.start_time')
                        ->on('class_timetable.end_time', '=', 'class_time_slots.end_time');
                })
                ->select(
                    'class_timetable.*',
                    'units.name as unit_name',
                    'units.code as unit_code',
                    'semesters.name as semester_name',
                    'classes.name as class_name',
                    'groups.name as group_name',
                    'class_time_slots.status as mode_of_teaching'
                );

            if ($request->has('semester_id')) {
                $query->where('class_timetable.semester_id', $request->semester_id);
            }

            if ($request->has('class_id')) {
                $query->where('class_timetable.class_id', $request->class_id);
            }

            if ($request->has('group_id')) {
                $query->where('class_timetable.group_id', $request->group_id);
            }

            $classTimetables = $query->orderBy('class_timetable.day')
                ->orderBy('class_timetable.start_time')
                ->get();

            $pdf = Pdf::loadView('classtimetables.pdf', [
                'classTimetables' => $classTimetables,
                'title' => 'Optimized Class Timetable',
                'generatedAt' => now()->format('Y-m-d H:i:s'),
                'filters' => $request->only(['semester_id', 'class_id', 'group_id'])
            ]);

            $pdf->setPaper('a4', 'landscape');
            return $pdf->download('optimized-class-timetable-' . now()->format('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            \Log::error('Failed to generate PDF: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    // ✅ Include conflict detection methods
    private function detectLecturerConflicts($timetables)
    {
        $conflicts = [];
        $lecturerSlots = [];

        foreach ($timetables as $timetable) {
            $key = $timetable->lecturer . '_' . $timetable->day;
            
            if (!isset($lecturerSlots[$key])) {
                $lecturerSlots[$key] = [];
            }

            foreach ($lecturerSlots[$key] as $existingSlot) {
                if ($this->timeSlotsOverlap($timetable, $existingSlot)) {
                    $conflicts[] = [
                        'type' => 'lecturer_conflict',
                        'lecturer' => $timetable->lecturer,
                        'day' => $timetable->day,
                        'conflicting_sessions' => [
                            [
                                'id' => $timetable->id,
                                'unit' => $timetable->unit->code ?? 'Unknown',
                                'time' => $timetable->start_time . '-' . $timetable->end_time,
                                'venue' => $timetable->venue
                            ],
                            [
                                'id' => $existingSlot->id,
                                'unit' => $existingSlot->unit->code ?? 'Unknown',
                                'time' => $existingSlot->start_time . '-' . $existingSlot->end_time,
                                'venue' => $existingSlot->venue
                            ]
                        ]
                    ];
                }
            }

            $lecturerSlots[$key][] = $timetable;
        }

        return $conflicts;
    }

    private function detectVenueConflicts($timetables)
    {
        $conflicts = [];
        $venueSlots = [];

        foreach ($timetables as $timetable) {
            if (strtolower($timetable->venue) === 'remote') {
                continue;
            }

            $key = $timetable->venue . '_' . $timetable->day;
            
            if (!isset($venueSlots[$key])) {
                $venueSlots[$key] = [];
            }

            foreach ($venueSlots[$key] as $existingSlot) {
                if ($this->timeSlotsOverlap($timetable, $existingSlot)) {
                    $conflicts[] = [
                        'type' => 'venue_conflict',
                        'venue' => $timetable->venue,
                        'day' => $timetable->day,
                        'conflicting_sessions' => [
                            [
                                'id' => $timetable->id,
                                'unit' => $timetable->unit->code ?? 'Unknown',
                                'time' => $timetable->start_time . '-' . $timetable->end_time,
                                'lecturer' => $timetable->lecturer
                            ],
                            [
                                'id' => $existingSlot->id,
                                'unit' => $existingSlot->unit->code ?? 'Unknown',
                                'time' => $existingSlot->start_time . '-' . $existingSlot->end_time,
                                'lecturer' => $existingSlot->lecturer
                            ]
                        ]
                    ];
                }
            }

            $venueSlots[$key][] = $timetable;
        }

        return $conflicts;
    }

    private function detectGroupConflicts($timetables)
    {
        $conflicts = [];
        $groupSlots = [];

        foreach ($timetables as $timetable) {
            if (!$timetable->group_id) continue;

            $key = $timetable->group_id . '_' . $timetable->day;
            
            if (!isset($groupSlots[$key])) {
                $groupSlots[$key] = [];
            }

            foreach ($groupSlots[$key] as $existingSlot) {
                if ($this->timeSlotsOverlap($timetable, $existingSlot)) {
                    $conflicts[] = [
                        'type' => 'group_conflict',
                        'group' => $timetable->group->name ?? 'Unknown',
                        'day' => $timetable->day,
                        'conflicting_sessions' => [
                            [
                                'id' => $timetable->id,
                                'unit' => $timetable->unit->code ?? 'Unknown',
                                'time' => $timetable->start_time . '-' . $timetable->end_time,
                                'venue' => $timetable->venue
                            ],
                            [
                                'id' => $existingSlot->id,
                                'unit' => $existingSlot->unit->code ?? 'Unknown',
                                'time' => $existingSlot->start_time . '-' . $existingSlot->end_time,
                                'venue' => $existingSlot->venue
                            ]
                        ]
                    ];
                }
            }

            $groupSlots[$key][] = $timetable;
        }

        return $conflicts;
    }

    private function detectConstraintViolations($timetables)
    {
        $violations = [];
        $groupDayStats = [];

        foreach ($timetables as $timetable) {
            if (!$timetable->group_id) continue;

            $key = $timetable->group_id . '_' . $timetable->day;
            
            if (!isset($groupDayStats[$key])) {
                $groupDayStats[$key] = [
                    'physical_count' => 0,
                    'online_count' => 0,
                    'total_hours' => 0,
                    'sessions' => []
                ];
            }

            $duration = $this->calculateSlotDuration($timetable->start_time, $timetable->end_time);
            $groupDayStats[$key]['total_hours'] += $duration;
            $groupDayStats[$key]['sessions'][] = $timetable;

            if ($timetable->teaching_mode === 'physical') {
                $groupDayStats[$key]['physical_count']++;
            } else {
                $groupDayStats[$key]['online_count']++;
            }
        }

        foreach ($groupDayStats as $key => $stats) {
            list($groupId, $day) = explode('_', $key);

            if ($stats['physical_count'] > self::MAX_PHYSICAL_PER_DAY) {
                $violations[] = [
                    'type' => 'max_physical_exceeded',
                    'group' => $stats['sessions'][0]->group->name ?? 'Unknown',
                    'day' => $day,
                    'current' => $stats['physical_count'],
                    'max_allowed' => self::MAX_PHYSICAL_PER_DAY,
                    'sessions' => $stats['sessions']
                ];
            }

            if ($stats['online_count'] > self::MAX_ONLINE_PER_DAY) {
                $violations[] = [
                    'type' => 'max_online_exceeded',
                    'group' => $stats['sessions'][0]->group->name ?? 'Unknown',
                    'day' => $day,
                    'current' => $stats['online_count'],
                    'max_allowed' => self::MAX_ONLINE_PER_DAY,
                    'sessions' => $stats['sessions']
                ];
            }

            if ($stats['total_hours'] > self::MAX_HOURS_PER_DAY) {
                $violations[] = [
                    'type' => 'max_hours_exceeded',
                    'group' => $stats['sessions'][0]->group->name ?? 'Unknown',
                    'day' => $day,
                    'current_hours' => $stats['total_hours'],
                    'max_allowed' => self::MAX_HOURS_PER_DAY,
                    'sessions' => $stats['sessions']
                ];
            }

            if (self::REQUIRE_MIXED_MODE) {
                $hasPhysical = $stats['physical_count'] > 0;
                $hasOnline = $stats['online_count'] > 0;
                
                if (!$hasPhysical || !$hasOnline) {
                    $violations[] = [
                        'type' => 'mixed_mode_required',
                        'group' => $stats['sessions'][0]->group->name ?? 'Unknown',
                        'day' => $day,
                        'has_physical' => $hasPhysical,
                        'has_online' => $hasOnline,
                        'sessions' => $stats['sessions']
                    ];
                }
            }
        }

        return $violations;
    }

    private function timeSlotsOverlap($slot1, $slot2)
    {
        return $slot1->start_time < $slot2->end_time && $slot2->start_time < $slot1->end_time;
    }

    private function calculateCurrentOptimizationScore($timetables)
    {
        if ($timetables->isEmpty()) return 100;

        $conflicts = [
            'lecturer_conflicts' => $this->detectLecturerConflicts($timetables),
            'venue_conflicts' => $this->detectVenueConflicts($timetables),
            'group_conflicts' => $this->detectGroupConflicts($timetables),
            'constraint_violations' => $this->detectConstraintViolations($timetables),
        ];

        $totalConflicts = array_sum(array_map('count', $conflicts));
        $maxPossibleConflicts = $timetables->count();

        return max(0, 100 - ($totalConflicts / max($maxPossibleConflicts, 1)) * 100);
    }

    // ✅ Student timetable method
    public function studentTimetable(Request $request)
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return redirect()->route('login')->with('error', 'Please log in to view your timetable.');
            }

            $currentSemester = Semester::where('is_active', true)->first();
            
            if (!$currentSemester) {
                $currentSemester = Semester::orderByDesc('id')->first();
            }

            if (!$currentSemester || !$user->code) {
                return Inertia::render('Student/Timetable', [
                    'classTimetables' => new \Illuminate\Pagination\LengthAwarePaginator(
                        collect([]), 0, $request->get('per_page', 10), 1,
                        ['path' => request()->url(), 'pageName' => 'page']
                    ),
                    'currentSemester' => $currentSemester,
                    'downloadUrl' => route('student.timetable.download'),
                    'student' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'code' => $user->code,
                        'groups' => []
                    ],
                    'filters' => [
                        'per_page' => $request->get('per_page', 10),
                        'search' => $request->get('search', ''),
                    ],
                    'error' => !$currentSemester ? 'No semester data available.' : 'Student code not found.'
                ]);
            }

            $enrollments = Enrollment::where('student_code', $user->code)
                ->where('semester_id', $currentSemester->id)
                ->with(['unit', 'semester', 'group'])
                ->get();

            $enrolledUnitIds = $enrollments->pluck('unit_id')->filter()->toArray();
            $studentGroupIds = $enrollments->pluck('group_id')->filter()->unique()->toArray();

            $perPage = $request->get('per_page', 10);
            $search = $request->get('search', '');

            $classTimetables = collect();
            
            if (!empty($enrolledUnitIds)) {
                $query = DB::table('class_timetable')
                    ->whereIn('class_timetable.unit_id', $enrolledUnitIds)
                    ->where('class_timetable.semester_id', $currentSemester->id);

                if (!empty($studentGroupIds)) {
                    $query->whereIn('class_timetable.group_id', $studentGroupIds);
                } else {
                    $hasNullGroup = $enrollments->whereNull('group_id')->isNotEmpty();
                    if ($hasNullGroup) {
                        $query->whereNull('class_timetable.group_id');
                    }
                }

                if (!empty($search)) {
                    $query->where(function ($q) use ($search) {
                        $q->where('units.code', 'like', "%{$search}%")
                          ->orWhere('units.name', 'like', "%{$search}%")
                          ->orWhere('class_timetable.venue', 'like', "%{$search}%")
                          ->orWhere('class_timetable.day', 'like', "%{$search}%")
                          ->orWhere(DB::raw("CONCAT(users.first_name, ' ', users.last_name)"), 'like', "%{$search}%")
                          ->orWhere('class_timetable.lecturer', 'like', "%{$search}%");
                    });
                }

                $classTimetables = $query
                    ->leftJoin('units', 'class_timetable.unit_id', '=', 'units.id')
                    ->leftJoin('users', 'users.code', '=', 'class_timetable.lecturer')
                    ->leftJoin('groups', 'class_timetable.group_id', '=', 'groups.id')
                    ->leftJoin('semesters', 'class_timetable.semester_id', '=', 'semesters.id')
                    ->select(
                        'class_timetable.id',
                        'class_timetable.day',
                        'class_timetable.start_time',
                        'class_timetable.end_time',
                        'class_timetable.venue',
                        'class_timetable.location',
                        'class_timetable.teaching_mode',
                        'class_timetable.lecturer',
                        'units.code as unit_code',
                        'units.name as unit_name',
                        DB::raw("CASE 
                            WHEN users.id IS NOT NULL THEN CONCAT(users.first_name, ' ', users.last_name) 
                            ELSE class_timetable.lecturer 
                            END as lecturer"),
                        'groups.name as group_name',
                        'semesters.name as semester_name'
                    )
                    ->orderBy('class_timetable.day')
                    ->orderBy('class_timetable.start_time')
                    ->paginate($perPage)
                    ->withQueryString();
            } else {
                $classTimetables = new \Illuminate\Pagination\LengthAwarePaginator(
                    collect([]), 0, $perPage, 1,
                    ['path' => request()->url(), 'pageName' => 'page']
                );
            }

            return Inertia::render('Student/Timetable', [
                'classTimetables' => $classTimetables,
                'currentSemester' => [
                    'id' => $currentSemester->id,
                    'name' => $currentSemester->name
                ],
                'downloadUrl' => route('student.timetable.download'),
                'student' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'code' => $user->code,
                    'groups' => $studentGroupIds
                ],
                'filters' => [
                    'per_page' => (int) $perPage,
                    'search' => $search,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Critical error in studentTimetable method: ' . $e->getMessage());

            return Inertia::render('Student/Timetable', [
                'classTimetables' => new \Illuminate\Pagination\LengthAwarePaginator(
                    collect([]), 0, $request->get('per_page', 10), 1,
                    ['path' => request()->url(), 'pageName' => 'page']
                ),
                'currentSemester' => null,
                'downloadUrl' => route('student.timetable.download'),
                'student' => [
                    'id' => auth()->id(),
                    'first_name' => auth()->user()->first_name ?? '',
                    'last_name' => auth()->user()->last_name ?? '',
                    'code' => auth()->user()->code ?? '',
                    'groups' => []
                ],
                'filters' => [
                    'per_page' => $request->get('per_page', 10),
                    'search' => $request->get('search', ''),
                ],
                'error' => 'An error occurred while loading your timetable. Please try again or contact support.'
            ]);
        }
    }

    // ✅ Additional helper methods
    public function getGroupsByClass($classId)
    {
        try {
            $groups = Group::where('class_id', $classId)
                ->select('id', 'name', 'class_id')
                ->get();

            return response()->json($groups);
        } catch (\Exception $e) {
            \Log::error('Error fetching groups for class: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch groups.'], 500);
        }
    }

    public function getLecturerForUnit($unitId, $semesterId)
    {
        try {
            $enrollment = Enrollment::where('unit_id', $unitId)
                ->where('semester_id', $semesterId)
                ->whereNotNull('lecturer_code')
                ->first();

            if (!$enrollment) {
                return response()->json(['success' => false, 'message' => 'No lecturer assigned to this unit.']);
            }

            $lecturer = User::where('code', $enrollment->lecturer_code)->first();
            if (!$lecturer) {
                return response()->json(['success' => false, 'message' => 'Lecturer not found.']);
            }

            $studentCount = Enrollment::where('unit_id', $unitId)
                ->where('semester_id', $semesterId)
                ->count();

            return response()->json([
                'success' => true,
                'lecturer' => [
                    'id' => $lecturer->id,
                    'code' => $lecturer->code,
                    'name' => $lecturer->first_name . ' ' . $lecturer->last_name,
                ],
                'studentCount' => $studentCount
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to get lecturer for unit: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to get lecturer information: ' . $e->getMessage()], 500);
        }
    }
}
