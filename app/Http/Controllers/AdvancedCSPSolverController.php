<?php

namespace App\Http\Controllers;

use App\Models\ClassTimetable;
use App\Models\Unit;
use App\Models\Semester;
use App\Models\ClassTimeSlot;
use App\Models\Classroom;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvancedCSPSolverController extends Controller
{
    // CSP Solver parameters
    private const MAX_ITERATIONS = 1000;
    private const TEMPERATURE_START = 100.0;
    private const TEMPERATURE_END = 0.1;
    private const COOLING_RATE = 0.95;
    private const POPULATION_SIZE = 50;
    private const GENERATIONS = 100;
    private const MUTATION_RATE = 0.1;
    private const CROSSOVER_RATE = 0.8;

    /**
     * Main CSP Solver endpoint
     */
    public function optimize(Request $request)
    {
        try {
            $algorithm = $request->input('algorithm', 'simulated_annealing');
            $mode = $request->input('mode', 'optimize');
            $constraints = $request->input('constraints', []);
            $currentSchedule = $request->input('current_schedule', []);

            Log::info('CSP Solver started', [
                'algorithm' => $algorithm,
                'mode' => $mode,
                'schedule_count' => count($currentSchedule)
            ]);

            // Get available resources
            $availableSlots = ClassTimeSlot::all();
            $availableVenues = Classroom::all();

            if ($availableSlots->isEmpty() || $availableVenues->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No available time slots or venues found. Please configure them first.'
                ]);
            }

            // Initialize CSP solver
            $solver = new ConstraintSatisfactionSolver(
                $currentSchedule,
                $availableSlots->toArray(),
                $availableVenues->toArray(),
                $constraints
            );

            // Apply selected algorithm
            $result = null;
            switch ($algorithm) {
                case 'simulated_annealing':
                    $result = $solver->simulatedAnnealing();
                    break;
                case 'genetic':
                    $result = $solver->geneticAlgorithm();
                    break;
                case 'backtracking':
                    $result = $solver->backtrackingSearch();
                    break;
                default:
                    $result = $solver->simulatedAnnealing();
            }

            if ($result['success']) {
                if ($mode === 'optimize') {
                    // Apply optimized schedule to existing timetables
                    $appliedChanges = $this->applyOptimizedSchedule($result['schedule'], $currentSchedule);
                    
                    return response()->json([
                        'success' => true,
                        'message' => "Schedule optimized successfully using {$algorithm}! Applied {$appliedChanges['count']} changes.",
                        'optimization_result' => [
                            'algorithm_used' => $algorithm,
                            'iterations' => $result['iterations'],
                            'initial_conflicts' => $result['initial_conflicts'],
                            'final_conflicts' => $result['final_conflicts'],
                            'improvement_percentage' => $result['improvement_percentage'],
                            'changes_applied' => $appliedChanges,
                            'execution_time' => $result['execution_time']
                        ]
                    ]);
                } else {
                    // Generate completely new schedule
                    $generatedCount = $this->generateNewSchedule($result['schedule']);
                    
                    return response()->json([
                        'success' => true,
                        'message' => "New optimal schedule generated using {$algorithm}! Created {$generatedCount} sessions.",
                        'generation_result' => [
                            'algorithm_used' => $algorithm,
                            'sessions_created' => $generatedCount,
                            'conflicts' => $result['final_conflicts'],
                            'execution_time' => $result['execution_time']
                        ]
                    ]);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'CSP solver failed to find a solution'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('CSP Solver error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'CSP Solver encountered an error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply optimized schedule to existing timetables
     */
    private function applyOptimizedSchedule($optimizedSchedule, $originalSchedule)
    {
        $changesApplied = 0;
        $changeDetails = [];

        DB::beginTransaction();
        
        try {
            foreach ($optimizedSchedule as $sessionId => $newAssignment) {
                $timetable = ClassTimetable::find($sessionId);
                if (!$timetable) continue;

                $changes = [];
                
                // Check what changed
                if ($timetable->day !== $newAssignment['day']) {
                    $changes[] = "day: {$timetable->day} → {$newAssignment['day']}";
                }
                
                if ($timetable->start_time !== $newAssignment['start_time']) {
                    $changes[] = "time: {$timetable->start_time}-{$timetable->end_time} → {$newAssignment['start_time']}-{$newAssignment['end_time']}";
                }
                
                if ($timetable->venue !== $newAssignment['venue']) {
                    $changes[] = "venue: {$timetable->venue} → {$newAssignment['venue']}";
                }
                
                if ($timetable->teaching_mode !== $newAssignment['teaching_mode']) {
                    $changes[] = "mode: {$timetable->teaching_mode} → {$newAssignment['teaching_mode']}";
                }

                if (!empty($changes)) {
                    // Apply changes
                    $timetable->update([
                        'day' => $newAssignment['day'],
                        'start_time' => $newAssignment['start_time'],
                        'end_time' => $newAssignment['end_time'],
                        'venue' => $newAssignment['venue'],
                        'location' => $newAssignment['location'],
                        'teaching_mode' => $newAssignment['teaching_mode']
                    ]);

                    $changesApplied++;
                    $changeDetails[] = [
                        'session_id' => $sessionId,
                        'unit_code' => $timetable->unit_code ?? 'Unknown',
                        'changes' => $changes
                    ];
                }
            }

            DB::commit();

            Log::info('Applied optimized schedule', [
                'changes_applied' => $changesApplied,
                'details' => $changeDetails
            ]);

            return [
                'count' => $changesApplied,
                'details' => $changeDetails
            ];

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Generate completely new schedule
     */
    private function generateNewSchedule($newSchedule)
    {
        DB::beginTransaction();
        
        try {
            // Clear existing timetables (you might want to be more selective here)
            ClassTimetable::truncate();

            $createdCount = 0;
            foreach ($newSchedule as $session) {
                ClassTimetable::create([
                    'day' => $session['day'],
                    'start_time' => $session['start_time'],
                    'end_time' => $session['end_time'],
                    'unit_id' => $session['unit_id'] ?? null,
                    'semester_id' => $session['semester_id'] ?? null,
                    'class_id' => $session['class_id'] ?? null,
                    'group_id' => $session['group_id'] ?? null,
                    'venue' => $session['venue'],
                    'location' => $session['location'],
                    'teaching_mode' => $session['teaching_mode'],
                    'lecturer' => $session['lecturer'] ?? 'TBA',
                    'no' => $session['student_count'] ?? 0,
                    'program_id' => $session['program_id'] ?? null,
                    'school_id' => $session['school_id'] ?? null
                ]);
                $createdCount++;
            }

            DB::commit();
            return $createdCount;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}

/**
 * Constraint Satisfaction Problem Solver
 */
class ConstraintSatisfactionSolver
{
    private $timetables;
    private $availableSlots;
    private $availableVenues;
    private $constraints;
    private $currentSolution;
    private $bestSolution;
    private $bestScore;

    public function __construct($timetables, $availableSlots, $availableVenues, $constraints)
    {
        $this->timetables = $timetables;
        $this->availableSlots = $availableSlots;
        $this->availableVenues = $availableVenues;
        $this->constraints = $constraints;
        $this->currentSolution = $this->initializeSolution();
        $this->bestSolution = $this->currentSolution;
        $this->bestScore = $this->evaluateSolution($this->currentSolution);
    }

    /**
     * Simulated Annealing Algorithm
     */
    public function simulatedAnnealing()
    {
        $startTime = microtime(true);
        $temperature = CSPSolverController::TEMPERATURE_START;
        $iterations = 0;
        $initialConflicts = $this->countConflicts($this->currentSolution);

        while ($temperature > CSPSolverController::TEMPERATURE_END && 
               $iterations < CSPSolverController::MAX_ITERATIONS) {
            
            // Generate neighbor solution
            $neighborSolution = $this->generateNeighbor($this->currentSolution);
            $neighborScore = $this->evaluateSolution($neighborSolution);
            $currentScore = $this->evaluateSolution($this->currentSolution);

            // Accept or reject the neighbor
            $deltaE = $neighborScore - $currentScore;
            
            if ($deltaE > 0 || exp($deltaE / $temperature) > mt_rand() / mt_getrandmax()) {
                $this->currentSolution = $neighborSolution;
                
                // Update best solution if better
                if ($neighborScore > $this->bestScore) {
                    $this->bestSolution = $neighborSolution;
                    $this->bestScore = $neighborScore;
                }
            }

            $temperature *= CSPSolverController::COOLING_RATE;
            $iterations++;
        }

        $finalConflicts = $this->countConflicts($this->bestSolution);
        $executionTime = microtime(true) - $startTime;

        return [
            'success' => true,
            'schedule' => $this->bestSolution,
            'iterations' => $iterations,
            'initial_conflicts' => $initialConflicts,
            'final_conflicts' => $finalConflicts,
            'improvement_percentage' => $initialConflicts > 0 ? 
                (($initialConflicts - $finalConflicts) / $initialConflicts) * 100 : 0,
            'execution_time' => round($executionTime, 2)
        ];
    }

    /**
     * Genetic Algorithm
     */
    public function geneticAlgorithm()
    {
        $startTime = microtime(true);
        $populationSize = CSPSolverController::POPULATION_SIZE;
        $generations = CSPSolverController::GENERATIONS;
        $mutationRate = CSPSolverController::MUTATION_RATE;
        $crossoverRate = CSPSolverController::CROSSOVER_RATE;

        // Initialize population
        $population = [];
        for ($i = 0; $i < $populationSize; $i++) {
            $population[] = $this->generateRandomSolution();
        }

        $initialConflicts = $this->countConflicts($this->currentSolution);
        $bestSolution = $population[0];
        $bestFitness = $this->evaluateSolution($bestSolution);

        for ($generation = 0; $generation < $generations; $generation++) {
            // Evaluate fitness for all individuals
            $fitness = array_map([$this, 'evaluateSolution'], $population);
            
            // Find best individual
            $maxFitnessIndex = array_keys($fitness, max($fitness))[0];
            if ($fitness[$maxFitnessIndex] > $bestFitness) {
                $bestSolution = $population[$maxFitnessIndex];
                $bestFitness = $fitness[$maxFitnessIndex];
            }

            // Selection, crossover, and mutation
            $newPopulation = [];
            
            for ($i = 0; $i < $populationSize; $i++) {
                // Tournament selection
                $parent1 = $this->tournamentSelection($population, $fitness);
                $parent2 = $this->tournamentSelection($population, $fitness);
                
                // Crossover
                if (mt_rand() / mt_getrandmax() < $crossoverRate) {
                    $offspring = $this->crossover($parent1, $parent2);
                } else {
                    $offspring = $parent1;
                }
                
                // Mutation
                if (mt_rand() / mt_getrandmax() < $mutationRate) {
                    $offspring = $this->mutate($offspring);
                }
                
                $newPopulation[] = $offspring;
            }
            
            $population = $newPopulation;
        }

        $finalConflicts = $this->countConflicts($bestSolution);
        $executionTime = microtime(true) - $startTime;

        return [
            'success' => true,
            'schedule' => $bestSolution,
            'iterations' => $generations,
            'initial_conflicts' => $initialConflicts,
            'final_conflicts' => $finalConflicts,
            'improvement_percentage' => $initialConflicts > 0 ? 
                (($initialConflicts - $finalConflicts) / $initialConflicts) * 100 : 0,
            'execution_time' => round($executionTime, 2)
        ];
    }

    /**
     * Backtracking Search
     */
    public function backtrackingSearch()
    {
        $startTime = microtime(true);
        $initialConflicts = $this->countConflicts($this->currentSolution);
        
        $solution = $this->backtrack([], 0);
        
        $executionTime = microtime(true) - $startTime;

        if ($solution) {
            $finalConflicts = $this->countConflicts($solution);
            
            return [
                'success' => true,
                'schedule' => $solution,
                'iterations' => 1,
                'initial_conflicts' => $initialConflicts,
                'final_conflicts' => $finalConflicts,
                'improvement_percentage' => $initialConflicts > 0 ? 
                    (($initialConflicts - $finalConflicts) / $initialConflicts) * 100 : 0,
                'execution_time' => round($executionTime, 2)
            ];
        } else {
            return [
                'success' => false,
                'message' => 'No valid solution found with backtracking',
                'execution_time' => round($executionTime, 2)
            ];
        }
    }

    /**
     * Initialize solution from current timetables
     */
    private function initializeSolution()
    {
        $solution = [];
        
        foreach ($this->timetables as $timetable) {
            $id = $timetable['id'] ?? uniqid();
            $solution[$id] = [
                'day' => $timetable['day'] ?? 'Monday',
                'start_time' => $timetable['start_time'] ?? '08:00',
                'end_time' => $timetable['end_time'] ?? '10:00',
                'venue' => $timetable['venue'] ?? 'TBA',
                'location' => $timetable['location'] ?? 'TBA',
                'teaching_mode' => $timetable['teaching_mode'] ?? 'physical',
                'unit_id' => $timetable['unit_id'] ?? null,
                'group_id' => $timetable['group_id'] ?? null,
                'lecturer' => $timetable['lecturer'] ?? 'TBA',
                'student_count' => $timetable['no'] ?? 0
            ];
        }
        
        return $solution;
    }

    /**
     * Generate a random valid solution
     */
    private function generateRandomSolution()
    {
        $solution = [];
        
        foreach ($this->timetables as $timetable) {
            $randomSlot = $this->availableSlots[array_rand($this->availableSlots)];
            $randomVenue = $this->availableVenues[array_rand($this->availableVenues)];
            $randomMode = mt_rand(0, 1) ? 'physical' : 'online';
            
            $id = $timetable['id'] ?? uniqid();
            $solution[$id] = [
                'day' => $randomSlot['day'],
                'start_time' => $randomSlot['start_time'],
                'end_time' => $randomSlot['end_time'],
                'venue' => $randomMode === 'online' ? 'Remote' : $randomVenue['name'],
                'location' => $randomMode === 'online' ? 'online' : $randomVenue['location'],
                'teaching_mode' => $randomMode,
                'unit_id' => $timetable['unit_id'] ?? null,
                'group_id' => $timetable['group_id'] ?? null,
                'lecturer' => $timetable['lecturer'] ?? 'TBA',
                'student_count' => $timetable['no'] ?? 0
            ];
        }
        
        return $solution;
    }

    /**
     * Generate neighbor solution by making small changes
     */
    private function generateNeighbor($solution)
    {
        $neighbor = $solution;
        $sessionIds = array_keys($solution);
        
        if (empty($sessionIds)) {
            return $neighbor;
        }
        
        $randomSessionId = $sessionIds[array_rand($sessionIds)];
        
        // Randomly choose what to change
        $changeType = mt_rand(1, 3);
        
        switch ($changeType) {
            case 1: // Change time slot
                $randomSlot = $this->availableSlots[array_rand($this->availableSlots)];
                $neighbor[$randomSessionId]['day'] = $randomSlot['day'];
                $neighbor[$randomSessionId]['start_time'] = $randomSlot['start_time'];
                $neighbor[$randomSessionId]['end_time'] = $randomSlot['end_time'];
                break;
                
            case 2: // Change venue
                $randomVenue = $this->availableVenues[array_rand($this->availableVenues)];
                if ($neighbor[$randomSessionId]['teaching_mode'] === 'physical') {
                    $neighbor[$randomSessionId]['venue'] = $randomVenue['name'];
                    $neighbor[$randomSessionId]['location'] = $randomVenue['location'];
                }
                break;
                
            case 3: // Change teaching mode
                $currentMode = $neighbor[$randomSessionId]['teaching_mode'];
                $newMode = $currentMode === 'physical' ? 'online' : 'physical';
                $neighbor[$randomSessionId]['teaching_mode'] = $newMode;
                
                if ($newMode === 'online') {
                    $neighbor[$randomSessionId]['venue'] = 'Remote';
                    $neighbor[$randomSessionId]['location'] = 'online';
                } else {
                    $randomVenue = $this->availableVenues[array_rand($this->availableVenues)];
                    $neighbor[$randomSessionId]['venue'] = $randomVenue['name'];
                    $neighbor[$randomSessionId]['location'] = $randomVenue['location'];
                }
                break;
        }
        
        return $neighbor;
    }

    /**
     * Evaluate solution quality (higher is better)
     */
    private function evaluateSolution($solution)
    {
        $score = 1000; // Start with perfect score
        
        // Subtract points for each conflict
        $conflicts = $this->countConflicts($solution);
        $score -= $conflicts * 10; // Heavy penalty for conflicts
        
        return $score;
    }

    /**
     * Count total conflicts in solution
     */
    private function countConflicts($solution)
    {
        $conflicts = 0;
        
        // Simple conflict detection - same time/venue/lecturer overlaps
        $sessions = array_values($solution);
        
        for ($i = 0; $i < count($sessions); $i++) {
            for ($j = $i + 1; $j < count($sessions); $j++) {
                $session1 = $sessions[$i];
                $session2 = $sessions[$j];
                
                // Same day check
                if ($session1['day'] === $session2['day']) {
                    // Time overlap check
                    if ($this->timesOverlap($session1, $session2)) {
                        // Venue conflict (physical venues only)
                        if ($session1['venue'] !== 'Remote' && 
                            $session1['venue'] === $session2['venue']) {
                            $conflicts++;
                        }
                        
                        // Lecturer conflict
                        if (isset($session1['lecturer']) && isset($session2['lecturer']) &&
                            $session1['lecturer'] === $session2['lecturer']) {
                            $conflicts++;
                        }
                        
                        // Group conflict
                        if (isset($session1['group_id']) && isset($session2['group_id']) &&
                            $session1['group_id'] === $session2['group_id'] &&
                            $session1['group_id'] !== null) {
                            $conflicts++;
                        }
                    }
                }
            }
        }
        
        return $conflicts;
    }

    /**
     * Check if two time sessions overlap
     */
    private function timesOverlap($session1, $session2)
    {
        $start1 = strtotime($session1['start_time']);
        $end1 = strtotime($session1['end_time']);
        $start2 = strtotime($session2['start_time']);
        $end2 = strtotime($session2['end_time']);
        
        return $start1 < $end2 && $start2 < $end1;
    }

    /**
     * Tournament selection for genetic algorithm
     */
    private function tournamentSelection($population, $fitness, $tournamentSize = 3)
    {
        $best = null;
        $bestFitness = -PHP_INT_MAX;
        
        for ($i = 0; $i < $tournamentSize; $i++) {
            $index = mt_rand(0, count($population) - 1);
            if ($fitness[$index] > $bestFitness) {
                $best = $population[$index];
                $bestFitness = $fitness[$index];
            }
        }
        
        return $best;
    }

    /**
     * Crossover operation for genetic algorithm
     */
    private function crossover($parent1, $parent2)
    {
        $offspring = [];
        $sessionIds = array_keys($parent1);
        
        foreach ($sessionIds as $sessionId) {
            // Randomly choose from either parent
            if (mt_rand(0, 1)) {
                $offspring[$sessionId] = $parent1[$sessionId];
            } else {
                $offspring[$sessionId] = $parent2[$sessionId];
            }
        }
        
        return $offspring;
    }

    /**
     * Mutation operation for genetic algorithm
     */
    private function mutate($individual)
    {
        return $this->generateNeighbor($individual);
    }

    /**
     * Backtracking search implementation
     */
    private function backtrack($assignment, $sessionIndex)
    {
        $sessionIds = array_keys($this->currentSolution);
        
        if ($sessionIndex >= count($sessionIds)) {
            // All sessions assigned, check if solution is valid
            if ($this->countConflicts($assignment) === 0) {
                return $assignment;
            }
            return null;
        }
        
        $sessionId = $sessionIds[$sessionIndex];
        
        // Try all possible assignments for this session
        foreach ($this->availableSlots as $slot) {
            foreach ($this->availableVenues as $venue) {
                foreach (['physical', 'online'] as $mode) {
                    $newAssignment = $assignment;
                    $newAssignment[$sessionId] = [
                        'day' => $slot['day'],
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['end_time'],
                        'venue' => $mode === 'online' ? 'Remote' : $venue['name'],
                        'location' => $mode === 'online' ? 'online' : $venue['location'],
                        'teaching_mode' => $mode,
                        'unit_id' => $this->currentSolution[$sessionId]['unit_id'],
                        'group_id' => $this->currentSolution[$sessionId]['group_id'],
                        'lecturer' => $this->currentSolution[$sessionId]['lecturer'],
                        'student_count' => $this->currentSolution[$sessionId]['student_count']
                    ];
                    
                    // Check if this assignment is consistent
                    if ($this->isConsistent($newAssignment, $sessionId)) {
                        $result = $this->backtrack($newAssignment, $sessionIndex + 1);
                        if ($result !== null) {
                            return $result;
                        }
                    }
                }
            }
        }
        
        return null; // No valid assignment found
    }

    /**
     * Check if current assignment is consistent
     */
    private function isConsistent($assignment, $currentSessionId)
    {
        $currentSession = $assignment[$currentSessionId];
        
        foreach ($assignment as $sessionId => $session) {
            if ($sessionId === $currentSessionId) continue;
            
            // Check conflicts only for same day
            if ($session['day'] === $currentSession['day'] &&
                $this->timesOverlap($session, $currentSession)) {
                
                // Venue conflict
                if ($session['venue'] !== 'Remote' && 
                    $session['venue'] === $currentSession['venue']) {
                    return false;
                }
                
                // Lecturer conflict
                if (isset($session['lecturer']) && isset($currentSession['lecturer']) &&
                    $session['lecturer'] === $currentSession['lecturer']) {
                    return false;
                }
                
                // Group conflict
                if (isset($session['group_id']) && isset($currentSession['group_id']) &&
                    $session['group_id'] === $currentSession['group_id'] &&
                    $session['group_id'] !== null) {
                    return false;
                }
            }
        }
        
        return true;
    }
}
