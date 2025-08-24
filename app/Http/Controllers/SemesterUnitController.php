<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Semester;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class SemesterUnitController extends Controller
{


    /**
     * Display a listing of semester units.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        // Log the start of the index method
        Log::debug('SemesterUnit Index method started');
        
        // Get all semesters with their units - FIXED VERSION
        $semesters = Semester::orderBy('name')->get();
        
        // For each semester, load its units with class information - IMPROVED
        foreach ($semesters as $semester) {
            $semester->units = $this->getSemesterUnitsWithClassInfo($semester->id);
        }
        
        $classes = ClassModel::orderBy('name')->get();
        $units = Unit::with(['program', 'school'])->get();
        
        // Enhanced debug information
        Log::info('Semesters with units loaded:', [
            'total_semesters' => count($semesters),
            'semesters_detail' => $semesters->map(function($semester) {
                return [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'units_count' => $semester->units ? count($semester->units) : 0,
                    'first_unit' => $semester->units && count($semester->units) > 0 ? [
                        'id' => $semester->units[0]->id ?? null,
                        'name' => $semester->units[0]->name ?? null,
                        'pivot_class_id' => $semester->units[0]->pivot_class_id ?? null
                    ] : null
                ];
            })->toArray()
        ]);
        
        return Inertia::render('SemesterUnits/Index', [
            'semesters' => $semesters,
            'classes' => $classes,
            'units' => $units
        ]);
    }

    /**
     * Get all units for a specific semester with their class information - FIXED VERSION.
     *
     * @param int $semesterId
     * @return \Illuminate\Support\Collection
     */
    private function getSemesterUnitsWithClassInfo($semesterId)
    {
        Log::debug('Getting semester units with class info', ['semester_id' => $semesterId]);
        
        $allUnits = collect();
        
        // PRIMARY: Check semester_unit table (this is your main table based on the screenshot)
        if (Schema::hasTable('semester_unit')) {
            $units = DB::table('units')
                ->join('semester_unit', 'units.id', '=', 'semester_unit.unit_id')
                ->where('semester_unit.semester_id', $semesterId)
                ->select(
                    'units.id',
                    'units.name',
                    'units.code',
                    'semester_unit.class_id as pivot_class_id' // This is the key fix
                )
                ->get();
                
            if ($units->isNotEmpty()) {
                Log::debug('Found units in semester_unit table', [
                    'count' => count($units),
                    'sample_unit' => $units->first()
                ]);
                $allUnits = $allUnits->merge($units);
            }
        }
        
        // SECONDARY: Check other tables only if no units found in primary table
        if ($allUnits->isEmpty()) {
            // Check if we're using semester_unit_class table
            if (Schema::hasTable('semester_unit_class')) {
                $units = DB::table('units')
                    ->join('semester_unit_class', 'units.id', '=', 'semester_unit_class.unit_id')
                    ->where('semester_unit_class.semester_id', $semesterId)
                    ->select(
                        'units.id',
                        'units.name',
                        'units.code',
                        'semester_unit_class.class_id as pivot_class_id'
                    )
                    ->get();
                    
                if ($units->isNotEmpty()) {
                    Log::debug('Found units in semester_unit_class table', ['count' => count($units)]);
                    $allUnits = $allUnits->merge($units);
                }
            }
            
            // Check class_unit table
            if ($allUnits->isEmpty() && Schema::hasTable('class_unit')) {
                $units = DB::table('units')
                    ->join('class_unit', 'units.id', '=', 'class_unit.unit_id')
                    ->where('class_unit.semester_id', $semesterId)
                    ->select(
                        'units.id',
                        'units.name',
                        'units.code',
                        'class_unit.class_id as pivot_class_id'
                    )
                    ->get();
                    
                if ($units->isNotEmpty()) {
                    Log::debug('Found units in class_unit table', ['count' => count($units)]);
                    $allUnits = $allUnits->merge($units);
                }
            }
            
            // Check if units have a direct semester_id column
            if ($allUnits->isEmpty() && Schema::hasColumn('units', 'semester_id')) {
                $directUnits = DB::table('units')
                    ->where('semester_id', $semesterId)
                    ->select(
                        'units.id',
                        'units.name',
                        'units.code'
                        // Note: No pivot_class_id for direct assignments
                    )
                    ->get();
                    
                if ($directUnits->isNotEmpty()) {
                    Log::debug('Found units with direct semester_id', ['count' => count($directUnits)]);
                    $allUnits = $allUnits->merge($directUnits);
                }
            }
            
            // Check enrollments table as last resort
            if ($allUnits->isEmpty()) {
                $enrolledUnitIds = DB::table('enrollments')
                    ->where('semester_id', $semesterId)
                    ->distinct()
                    ->pluck('unit_id')
                    ->toArray();
                    
                if (!empty($enrolledUnitIds)) {
                    $enrolledUnits = DB::table('units')
                        ->whereIn('id', $enrolledUnitIds)
                        ->select(
                            'units.id',
                            'units.name',
                            'units.code'
                        )
                        ->get();
                        
                    if ($enrolledUnits->isNotEmpty()) {
                        Log::debug('Found units from enrollments', ['count' => count($enrolledUnits)]);
                        $allUnits = $allUnits->merge($enrolledUnits);
                    }
                }
            }
        }
        
        // Remove duplicates by unit ID and convert to objects
        $uniqueUnits = $allUnits->unique('id')->map(function($unit) {
            // Ensure we return a proper object with all expected properties
            return (object)[
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code,
                'pivot_class_id' => $unit->pivot_class_id ?? null,
                'is_suggestion' => false // Default value
            ];
        });
        
        Log::debug('Final unique units for semester', [
            'semester_id' => $semesterId,
            'count' => count($uniqueUnits),
            'units_with_class' => $uniqueUnits->where('pivot_class_id', '!=', null)->count(),
            'units_without_class' => $uniqueUnits->where('pivot_class_id', null)->count()
        ]);
        
        return $uniqueUnits;
    }

    /**
     * Get all units for a specific semester with their class information.
     *
     * @param int $semesterId
     * @return \Illuminate\Support\Collection
     * @deprecated Use getSemesterUnitsWithClassInfo instead
     */
    private function getSemesterUnits($semesterId)
    {
        // Keep the old method for backward compatibility but redirect to new method
        return $this->getSemesterUnitsWithClassInfo($semesterId);
    }
    
    /**
     * Store a newly created semester unit assignment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'exists:units,id',
        ]);
        
        // Log the validated data
        Log::info('Assigning units to semester and class', [
            'semester_id' => $validated['semester_id'],
            'class_id' => $validated['class_id'],
            'unit_ids' => $validated['unit_ids'],
        ]);
        
        try {
            // Begin transaction
            DB::beginTransaction();
            
            // Check if semester_unit table exists, if not create it
            if (!Schema::hasTable('semester_unit')) {
                Log::info('Creating semester_unit table');
                
                Schema::create('semester_unit', function ($table) {
                    $table->id();
                    $table->unsignedBigInteger('semester_id');
                    $table->unsignedBigInteger('unit_id');
                    $table->unsignedBigInteger('class_id');
                    $table->timestamps();
                    
                    // Add foreign keys if the referenced tables exist
                    if (Schema::hasTable('semesters')) {
                        $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
                    }
                    
                    if (Schema::hasTable('units')) {
                        $table->foreign('unit_id')->references('id')->on('units')->onDelete('cascade');
                    }
                    
                    if (Schema::hasTable('classes')) {
                        $table->foreign('class_id')->references('id')->on('classes')->onDelete('cascade');
                    }
                    
                    $table->unique(['semester_id', 'unit_id', 'class_id']);
                });
            }
            
            // Get the structure of the semester_unit table
            $semesterUnitColumns = Schema::getColumnListing('semester_unit');
            Log::info('semester_unit table columns', ['columns' => $semesterUnitColumns]);
            
            // DIRECT INSERT: Force insert into semester_unit table
            foreach ($validated['unit_ids'] as $unitId) {
                // Check if the assignment already exists
                $exists = DB::table('semester_unit')
                    ->where('semester_id', $validated['semester_id'])
                    ->where('unit_id', $unitId)
                    ->where('class_id', $validated['class_id'])
                    ->exists();
                    
                if (!$exists) {
                    $insertData = [
                        'semester_id' => $validated['semester_id'],
                        'unit_id' => $unitId,
                        'class_id' => $validated['class_id'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    
                    Log::info('Inserting record into semester_unit', $insertData);
                    
                    try {
                        $insertId = DB::table('semester_unit')->insertGetId($insertData);
                        Log::info('Insert successful', ['id' => $insertId]);
                        
                        // If units table has semester_id column, update it as well
                        if (Schema::hasColumn('units', 'semester_id')) {
                            DB::table('units')
                                ->where('id', $unitId)
                                ->update(['semester_id' => $validated['semester_id']]);
                                
                            Log::info('Updated unit semester_id', [
                                'unit_id' => $unitId,
                                'semester_id' => $validated['semester_id']
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('Error inserting record', [
                            'error' => $e->getMessage(),
                            'data' => $insertData
                        ]);
                        throw $e;
                    }
                } else {
                    Log::info('Record already exists, skipping', [
                        'semester_id' => $validated['semester_id'],
                        'unit_id' => $unitId,
                        'class_id' => $validated['class_id'],
                    ]);
                }
            }
            
            // Commit transaction
            DB::commit();
            
            Log::info('Units assigned successfully');
            
            return redirect()->back()->with('success', 'Units assigned successfully!');
        } catch (\Exception $e) {
            // Rollback transaction
            DB::rollBack();
            
            Log::error('Error assigning units: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->back()->withErrors(['error' => 'Failed to assign units: ' . $e->getMessage()]);
        }
    }
    
    // ... [Keep all other existing methods unchanged] ...
    
    /**
     * Remove a unit assignment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
            'unit_id' => 'required|exists:units,id',
        ]);
        
        Log::debug('Removing unit assignment', $validated);
        
        try {
            // Begin transaction
            DB::beginTransaction();
            
            $deleted = false;
            
            // Check if we're using semester_unit_class table
            if (Schema::hasTable('semester_unit_class')) {
                $result = DB::table('semester_unit_class')
                    ->where('semester_id', $validated['semester_id'])
                    ->where('class_id', $validated['class_id'])
                    ->where('unit_id', $validated['unit_id'])
                    ->delete();
                    
                if ($result) {
                    Log::debug('Deleted from semester_unit_class table');
                    $deleted = true;
                }
            }
            
            // Check semester_unit table
            if (Schema::hasTable('semester_unit')) {
                $result = DB::table('semester_unit')
                    ->where('semester_id', $validated['semester_id'])
                    ->where('class_id', $validated['class_id'])
                    ->where('unit_id', $validated['unit_id'])
                    ->delete();
                    
                if ($result) {
                    Log::debug('Deleted from semester_unit table');
                    $deleted = true;
                }
            }
            
            // Check class_unit table
            if (Schema::hasTable('class_unit')) {
                $result = DB::table('class_unit')
                    ->where('class_id', $validated['class_id'])
                    ->where('unit_id', $validated['unit_id'])
                    ->where('semester_id', $validated['semester_id'])
                    ->delete();
                    
                if ($result) {
                    Log::debug('Deleted from class_unit table');
                    $deleted = true;
                }
            }
            
            // If units table has semester_id column, check if this is the only assignment
            if (Schema::hasColumn('units', 'semester_id')) {
                // Check if this unit is assigned to any other class in this semester
                $otherAssignments = DB::table('semester_unit')
                    ->where('semester_id', $validated['semester_id'])
                    ->where('unit_id', $validated['unit_id'])
                    ->where('class_id', '!=', $validated['class_id'])
                    ->exists();
                    
                if (!$otherAssignments) {
                    // This was the only assignment, so clear the semester_id
                    DB::table('units')
                        ->where('id', $validated['unit_id'])
                        ->update(['semester_id' => null]);
                        
                    Log::debug('Cleared unit semester_id', [
                        'unit_id' => $validated['unit_id']
                    ]);
                }
            }
            
            // Commit transaction
            DB::commit();
            
            if ($deleted) {
                return redirect()->back()->with('success', 'Unit assignment removed successfully!');
            } else {
                Log::warning('No unit assignment found to delete', $validated);
                return redirect()->back()->with('warning', 'No unit assignment found to remove.');
            }
        } catch (\Exception $e) {
            // Rollback transaction
            DB::rollBack();
            
            Log::error('Error removing unit assignment: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->back()->withErrors(['error' => 'Failed to remove unit assignment: ' . $e->getMessage()]);
        }
    }
   

    
    /**
     * Get units assigned to classes for a semester.
     *
     * @param  int  $semesterId
     * @return array
     */
    private function getAssignedUnitsForSemester($semesterId)
    {
        Log::debug('Getting assigned units for semester', ['semester_id' => $semesterId]);
        
        $assignedUnits = [];
        
        // Get all classes
        $classes = ClassModel::orderBy('name')->get();
        
        foreach ($classes as $class) {
            // Get units assigned to this class for the semester
            $units = $this->getUnitsByClass($semesterId, $class->id);
            
            if (count($units) > 0) {
                $assignedUnits[$class->name] = $units;
            }
        }
        
        Log::debug('Assigned units by class', [
            'semester_id' => $semesterId,
            'class_count' => count($assignedUnits)
        ]);
        
        return $assignedUnits;
    }
    
    /**
     * Get units assigned to a specific class for a semester.
     *
     * @param  int  $semesterId
     * @param  int  $classId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUnitsByClass($semesterId, $classId)
    {
        // Log the request
        Log::info('Getting units for class', [
            'semester_id' => $semesterId,
            'class_id' => $classId
        ]);
        
        $allUnits = collect();
        
        // First check if we have a semester_unit_class table
        if (Schema::hasTable('semester_unit_class')) {
            $units = Unit::with(['program', 'school'])
                ->join('semester_unit_class', 'units.id', '=', 'semester_unit_class.unit_id')
                ->where('semester_unit_class.semester_id', $semesterId)
                ->where('semester_unit_class.class_id', $classId)
                ->select('units.*')
                ->get();
                
            if (count($units) > 0) {
                Log::info('Found units in semester_unit_class table', ['count' => count($units)]);
                $allUnits = $allUnits->merge($units);
            }
        }
        
        // Check semester_unit table
        if (Schema::hasTable('semester_unit')) {
            $units = Unit::with(['program', 'school'])
                ->join('semester_unit', 'units.id', '=', 'semester_unit.unit_id')
                ->where('semester_unit.semester_id', $semesterId)
                ->where('semester_unit.class_id', $classId)
                ->select('units.*')
                ->get();
                
            if (count($units) > 0) {
                Log::info('Found units in semester_unit table', ['count' => count($units)]);
                $allUnits = $allUnits->merge($units);
            }
        }
        
        // Check class_unit table
        if (Schema::hasTable('class_unit')) {
            $units = Unit::with(['program', 'school'])
                ->join('class_unit', 'units.id', '=', 'class_unit.unit_id')
                ->where('class_unit.class_id', $classId)
                ->where(function($query) use ($semesterId) {
                    $query->where('class_unit.semester_id', $semesterId)
                          ->orWhereNull('class_unit.semester_id');
                })
                ->select('units.*')
                ->get();
                
            if (count($units) > 0) {
                Log::info('Found units in class_unit table', ['count' => count($units)]);
                $allUnits = $allUnits->merge($units);
            }
        }
        
        // Check if units have a direct semester_id column
        if (Schema::hasColumn('units', 'semester_id')) {
            // Get units directly assigned to this semester
            $directUnits = Unit::with(['program', 'school'])
                ->where('semester_id', $semesterId)
                ->get();
                
            if (count($directUnits) > 0) {
                Log::info('Found units with direct semester_id', ['count' => count($directUnits)]);
                
                // Mark these as not explicitly assigned to the class
                foreach ($directUnits as $unit) {
                    $unit->direct_assignment = true;
                }
                
                $allUnits = $allUnits->merge($directUnits);
            }
        }
        
        // If still not found, try to find units from enrollments
        $enrolledUnitIds = DB::table('enrollments')
            ->join('groups', 'enrollments.group_id', '=', 'groups.id')
            ->where('groups.class_id', $classId)
            ->where('enrollments.semester_id', $semesterId)
            ->distinct()
            ->pluck('enrollments.unit_id')
            ->toArray();
            
        if (!empty($enrolledUnitIds)) {
            $enrolledUnits = Unit::with(['program', 'school'])
                ->whereIn('id', $enrolledUnitIds)
                ->get();
                
            if (count($enrolledUnits) > 0) {
                Log::info('Found units from enrollments', ['count' => count($enrolledUnits)]);
                
                // Mark these as enrollment-based
                foreach ($enrolledUnits as $unit) {
                    $unit->enrollment_based = true;
                }
                
                $allUnits = $allUnits->merge($enrolledUnits);
            }
        }
        
        // If still no units found, try pattern matching as a last resort
        if ($allUnits->isEmpty()) {
            $class = ClassModel::find($classId);
            if ($class && preg_match('/(\w+)\s+(\d+\.\d+)/', $class->name, $matches)) {
                $program = $matches[1]; // e.g., "BBIT"
                $level = $matches[2];   // e.g., "1.1"
                
                // Extract the major level (e.g., "1" from "1.1")
                $majorLevel = explode('.', $level)[0];
                
                // Look for units with codes that might match this class level
                if (Schema::hasColumn('units', 'code')) {
                    $suggestedUnits = Unit::with(['program', 'school'])
                        ->where(function($q) use ($program, $level, $majorLevel) {
                            // Look for units with codes that match this specific class
                            $q->where('code', 'like', $program . $level . '%')
                              // Or units with codes that match this program and level
                              ->orWhere('code', 'like', $program . '%' . str_replace('.', '', $level) . '%')
                              // Or units with codes that match common patterns for this level
                              ->orWhere('code', 'like', $program . $majorLevel . '%');
                        })
                        ->when(Schema::hasColumn('units', 'semester_id'), function($query) use ($semesterId) {
                            return $query->where('semester_id', $semesterId);
                        })
                        ->get();
                        
                    if (count($suggestedUnits) > 0) {
                        // Mark these units as suggestions, not actual assignments
                        foreach ($suggestedUnits as $unit) {
                            $unit->is_suggestion = true;
                        }
                        
                        Log::info('Found suggested units based on naming pattern', ['count' => count($suggestedUnits)]);
                        $allUnits = $allUnits->merge($suggestedUnits);
                    }
                }
            }
        }
        
        // Remove duplicates by unit ID
        $uniqueUnits = $allUnits->unique('id')->values();
        
        Log::info('Final unique units for class', [
            'semester_id' => $semesterId,
            'class_id' => $classId,
            'count' => count($uniqueUnits)
        ]);
        
        if ($uniqueUnits->isEmpty()) {
            Log::warning('No units found for this class and semester', [
                'semester_id' => $semesterId,
                'class_id' => $classId
            ]);
        }
        
        return $uniqueUnits;
    }

    /**
     * Delete a unit from a semester.
     *
     * @param  int  $semesterId
     * @param  int  $unitId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteUnit($semesterId, $unitId)
    {
        Log::debug('Deleting unit from semester', [
            'semester_id' => $semesterId,
            'unit_id' => $unitId
        ]);
        
        try {
            // Begin transaction
            DB::beginTransaction();
            
            // Delete the unit from the semester_unit table
            $deleted = DB::table('semester_unit')
                ->where('semester_id', $semesterId)
                ->where('unit_id', $unitId)
                ->delete();
                
            // If units table has semester_id column, check if this is the only assignment
            if (Schema::hasColumn('units', 'semester_id')) {
                // Check if this unit is assigned to any other class in this semester
                $otherAssignments = DB::table('semester_unit')
                    ->where('semester_id', $semesterId)
                    ->where('unit_id', $unitId)
                    ->exists();
                    
                if (!$otherAssignments) {
                    // This was the only assignment, so clear the semester_id
                    DB::table('units')
                        ->where('id', $unitId)
                        ->update(['semester_id' => null]);
                        
                    Log::debug('Cleared unit semester_id', [
                        'unit_id' => $unitId
                    ]);
                }
            }
            
            // Commit transaction
            DB::commit();

            if ($deleted) {
                return redirect()->back()->with('success', 'Unit removed successfully!');
            } else {
                Log::warning('No unit found to delete', [
                    'semester_id' => $semesterId,
                    'unit_id' => $unitId
                ]);
                return redirect()->back()->with('warning', 'No unit found to remove.');
            }
        } catch (\Exception $e) {
            // Rollback transaction
            DB::rollBack();
            
            Log::error('Failed to delete unit: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->back()->withErrors(['error' => 'Failed to delete unit: ' . $e->getMessage()]);
        }
    }
    
    /**
     * API endpoint to get units for a semester.
     *
     * @param  int  $semesterId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnitsBySemester($semesterId)
    {
        Log::debug('API: Getting units for semester', ['semester_id' => $semesterId]);
        
        try {
            // Get all units for this semester
            $units = $this->getSemesterUnits($semesterId);
            
            // Convert to array and add additional information
            $unitsArray = $units->map(function ($unit) {
                $unitObj = is_object($unit) ? $unit : (object)$unit;
                
                // Get student count for this unit
                $studentCount = Enrollment::where('unit_id', $unitObj->id)
                    ->where('semester_id', request()->input('semester_id', $unitObj->semester_id ?? null))
                    ->count();
                
                // Get lecturer for this unit
                $lecturer = Enrollment::where('unit_id', $unitObj->id)
                    ->where('semester_id', request()->input('semester_id', $unitObj->semester_id ?? null))
                    ->whereNotNull('lecturer_code')
                    ->with('lecturer:id,name,code')
                    ->first();
                
                return [
                    'id' => $unitObj->id,
                    'code' => $unitObj->code,
                    'name' => $unitObj->name,
                    'student_count' => $studentCount,
                    'lecturer_code' => $lecturer ? $lecturer->lecturer_code : null,
                    'lecturer_name' => $lecturer && $lecturer->lecturer ? $lecturer->lecturer->name : null,
                    'semester_id' => $unitObj->semester_id ?? null,
                    'pivot_class_id' => $unitObj->pivot_class_id ?? null,
                    'is_suggestion' => $unitObj->is_suggestion ?? false,
                    'direct_assignment' => $unitObj->direct_assignment ?? false,
                    'enrollment_based' => $unitObj->enrollment_based ?? false,
                ];
            })->toArray();
            
            Log::debug('API: Returning units for semester', [
                'semester_id' => $semesterId,
                'count' => count($unitsArray)
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Units retrieved successfully',
                'data' => $unitsArray
            ]);
        } catch (\Exception $e) {
            Log::error('API: Error getting units for semester: ' . $e->getMessage(), [
                'semester_id' => $semesterId,
                'exception' => $e
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving units: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }
    
    /**
     * API endpoint to get units for a class in a semester.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnitsByClassAndSemester(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'class_id' => 'required|exists:classes,id',
        ]);
        
        Log::debug('API: Getting units for class and semester', $validated);
        
        try {
            // Get units for this class and semester
            $units = $this->getUnitsByClass($validated['semester_id'], $validated['class_id']);
            
            // Convert to array and add additional information
            $unitsArray = $units->map(function ($unit) use ($validated) {
                // Get student count for this unit in this class
                $studentCount = Enrollment::where('unit_id', $unit->id)
                    ->where('semester_id', $validated['semester_id'])
                    ->join('groups', 'enrollments.group_id', '=', 'groups.id')
                    ->where('groups.class_id', $validated['class_id'])
                    ->count();
                
                // Get lecturer for this unit
                $lecturer = Enrollment::where('unit_id', $unit->id)
                    ->where('semester_id', $validated['semester_id'])
                    ->whereNotNull('lecturer_code')
                    ->with('lecturer:id,name,code')
                    ->first();
                
                return [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'student_count' => $studentCount,
                    'lecturer_code' => $lecturer ? $lecturer->lecturer_code : null,
                    'lecturer_name' => $lecturer && $lecturer->lecturer ? $lecturer->lecturer->name : null,
                    'semester_id' => $validated['semester_id'],
                    'class_id' => $validated['class_id'],
                    'is_suggestion' => $unit->is_suggestion ?? false,
                    'direct_assignment' => $unit->direct_assignment ?? false,
                    'enrollment_based' => $unit->enrollment_based ?? false,
                ];
            })->toArray();
            
            Log::debug('API: Returning units for class and semester', [
                'semester_id' => $validated['semester_id'],
                'class_id' => $validated['class_id'],
                'count' => count($unitsArray)
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Units retrieved successfully',
                'data' => $unitsArray
            ]);
        } catch (\Exception $e) {
            Log::error('API: Error getting units for class and semester: ' . $e->getMessage(), [
                'validated' => $validated,
                'exception' => $e
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving units: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }
    
    /**
     * Bulk assign units to a semester.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'semester_id' => 'required|exists:semesters,id',
            'unit_ids' => 'required|array',
            'unit_ids.*' => 'exists:units,id',
        ]);
        
        Log::info('Bulk assigning units to semester', [
            'semester_id' => $validated['semester_id'],
            'unit_count' => count($validated['unit_ids']),
        ]);
        
        try {
            // Begin transaction
            DB::beginTransaction();
            
            // Update units table if it has semester_id column
            if (Schema::hasColumn('units', 'semester_id')) {
                DB::table('units')
                    ->whereIn('id', $validated['unit_ids'])
                    ->update(['semester_id' => $validated['semester_id']]);
                    
                Log::info('Updated units semester_id', [
                    'count' => count($validated['unit_ids']),
                    'semester_id' => $validated['semester_id']
                ]);
            }
            
            // Commit transaction
            DB::commit();
            
            return redirect()->back()->with('success', 'Units bulk assigned to semester successfully!');
        } catch (\Exception $e) {
            // Rollback transaction
            DB::rollBack();
            
            Log::error('Error bulk assigning units: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->back()->withErrors(['error' => 'Failed to bulk assign units: ' . $e->getMessage()]);
        }
    }

    /**
     * Assign a unit to a lecturer.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function assignUnitToLecturer(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'lecturer_code' => 'required|exists:users,code',
        ]);

        try {
            $lecturer = User::where('code', $validated['lecturer_code'])->firstOrFail();

            // Update the lecturer_code in the enrollments table
            Enrollment::where('unit_id', $validated['unit_id'])
                ->update(['lecturer_code' => $lecturer->code]);

            return redirect()->back()->with('success', 'Unit assigned to lecturer successfully!');
        } catch (\Exception $e) {
            Log::error('Error assigning unit to lecturer: ' . $e->getMessage(), [
                'unit_id' => $validated['unit_id'],
                'lecturer_code' => $validated['lecturer_code'],
            ]);

            return redirect()->back()->withErrors(['error' => 'Failed to assign unit to lecturer.']);
        }
    }
}