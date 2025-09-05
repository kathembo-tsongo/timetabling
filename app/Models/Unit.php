<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'credit_hours',
        'school_id',
        'program_id',
        'semester_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credit_hours' => 'integer',
    ];

    // Add this to always eager load relationships when needed
    protected $with = [];

    // Existing Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UnitAssignment::class);
    }

    
    // Existing Scopes
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeForProgram($query, $programId)
    {
        return $query->where('program_id', $programId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSemester($query, $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('semester_id');
    }

    public function scopeAssigned($query)
    {
        return $query->whereNotNull('semester_id');
    }

    // Enhanced scope for statistics with relationships
    public function scopeWithStatisticsData($query)
    {
        return $query->with(['school:id,code,name', 'program:id,code,name', 'semester:id,name']);
    }

    // NEW: Lecturer assignment related scopes
    public function scopeWithLecturerAssignments($query, $semesterId = null)
    {
        $relationQuery = ['lecturerAssignments'];
        if ($semesterId) {
            $relationQuery = ['lecturerAssignments' => function ($query) use ($semesterId) {
                $query->where('semester_id', $semesterId)->where('is_active', true);
            }];
        }
        return $query->with($relationQuery);
    }

        // Existing Accessors
    public function getSchoolNameAttribute()
    {
        return $this->school ? $this->school->name : null;
    }

    public function getSchoolCodeAttribute()
    {
        return $this->school ? $this->school->code : null;
    }

    public function getProgramNameAttribute()
    {
        return $this->program ? $this->program->name : null;
    }

    public function getProgramCodeAttribute()
    {
        return $this->program ? $this->program->code : null;
    }

    public function getSemesterNameAttribute()
    {
        return $this->semester ? $this->semester->name : null;
    }

    
    /**
     * Check if unit has a lecturer assigned for a specific semester.
     */
    public function hasLecturerAssignedForSemester($semesterId)
    {
        return $this->lecturerAssignments()
            ->where('semester_id', $semesterId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get the current lecturer for a specific semester.
     */
    public function getCurrentLecturerForSemester($semesterId)
    {
        $assignment = $this->getLecturerAssignmentForSemester($semesterId);
        return $assignment ? [
            'code' => $assignment->lecturer_code,
            'name' => $assignment->lecturer_name,
            'email' => $assignment->lecturer_email,
        ] : null;
    }

    /**
     * Get lecturer information as attributes (for compatibility with existing code)
     */
    public function getLecturerNameForSemester($semesterId)
    {
        $assignment = $this->getLecturerAssignmentForSemester($semesterId);
        return $assignment ? $assignment->lecturer_name : null;
    }

    public function getLecturerCodeForSemester($semesterId)
    {
        $assignment = $this->getLecturerAssignmentForSemester($semesterId);
        return $assignment ? $assignment->lecturer_code : null;
    }

    public function getLecturerEmailForSemester($semesterId)
    {
        $assignment = $this->getLecturerAssignmentForSemester($semesterId);
        return $assignment ? $assignment->lecturer_email : null;
    }

    // Existing Static Methods for Statistics
    public static function getStatisticsBySemester($semesterId)
    {
        $units = static::withStatisticsData()
            ->where('semester_id', $semesterId)
            ->get();

        $unitsBySchool = [];
        $unitsByProgram = [];

        foreach ($units as $unit) {
            // Group by school code
            $schoolCode = $unit->school_code;
            if ($schoolCode) {
                $unitsBySchool[$schoolCode] = ($unitsBySchool[$schoolCode] ?? 0) + 1;
            }

            // Group by program code  
            $programCode = $unit->program_code;
            if ($programCode) {
                $unitsByProgram[$programCode] = ($unitsByProgram[$programCode] ?? 0) + 1;
            }
        }

        return [
            'units_count' => $units->count(),
            'units_by_school' => $unitsBySchool,
            'units_by_program' => $unitsByProgram,
        ];
    }

    public static function getSchoolStatistics($semesterId = null)
    {
        $query = static::withStatisticsData();
        
        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return $query->get()
            ->groupBy('school_code')
            ->map(function ($units, $schoolCode) {
                return [
                    'school_code' => $schoolCode,
                    'school_name' => $units->first()->school_name ?? 'Unknown',
                    'count' => $units->count(),
                    'units' => $units->pluck('name')->toArray()
                ];
            });
    }

    public static function getProgramStatistics($semesterId = null)
    {
        $query = static::withStatisticsData();
        
        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return $query->get()
            ->groupBy('program_code')
            ->map(function ($units, $programCode) {
                return [
                    'program_code' => $programCode,
                    'program_name' => $units->first()->program_name ?? 'Unknown',
                    'count' => $units->count(),
                    'units' => $units->pluck('name')->toArray()
                ];
            });
    }

       // Existing Helper Methods
    public function getEnrollmentCount()
    {
        return $this->enrollments()->count();
    }

    public function getStudentCount()
    {
        return $this->enrollments()
            ->whereNotNull('student_code')
            ->distinct('student_code')
            ->count();
    }

    public function isAssignedToSemester()
    {
        return !is_null($this->semester_id);
    }

    public function canBeActive()
    {
        return $this->isAssignedToSemester();
    }

    // Existing Boot Method
    protected static function boot()
    {
        parent::boot();
        
        static::updating(function ($unit) {
            // If semester_id is being set to null, also set is_active to false
            if ($unit->isDirty('semester_id') && is_null($unit->semester_id)) {
                $unit->is_active = false;
            }
        });
    }

    // Collection method for getting statistics from a collection of units
    public function newCollection(array $models = [])
    {
        return new UnitCollection($models);
    }


/**
 * Get lecturer assignment for a specific semester
 */
public function getLecturerAssignmentForSemester($semesterId)
{
    return LecturerAssignment::where('unit_id', $this->id)
        ->where('semester_id', $semesterId)
        ->where('is_active', true)
        ->first();
}

/**
 * Scope to include lecturer information for a specific semester
 */
public function scopeWithLecturerForSemester($query, $semesterId)
{
    return $query->with(['lecturerAssignments' => function ($q) use ($semesterId) {
        $q->where('semester_id', $semesterId)->where('is_active', true);
    }]);
}

/**
 * Get lecturer assignments for this unit
 */
public function lecturerAssignments()
{
    return $this->hasMany(LecturerAssignment::class);
}

/**
 * Get lecturer assignment statistics for a semester
 */
public static function getLecturerAssignmentStatistics($semesterId)
{
    $totalUnits = self::where('is_active', true)->count();
    $assignedUnits = LecturerAssignment::where('semester_id', $semesterId)
        ->where('is_active', true)
        ->distinct('unit_id')
        ->count();
    
    $totalLecturers = User::whereHas('roles', function ($query) {
        $query->where('name', 'lecturer');
    })->count();

    return [
        'total_units' => $totalUnits,
        'assigned_units' => $assignedUnits,
        'unassigned_units' => $totalUnits - $assignedUnits,
        'total_lecturers' => $totalLecturers,
    ];
}
}