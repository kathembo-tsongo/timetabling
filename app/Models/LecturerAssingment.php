<?php

// Update your UnitAssignment model
// app/Models/UnitAssignment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'class_id',
        'semester_id',
        'lecturer_code',
        'assigned_at',
        'assignment_notes',
        'is_active'
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    // Existing relationships
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    // New relationship for lecturer
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_code', 'code');
    }

    // Relationship for assignment history
    public function history(): HasMany
    {
        return $this->hasMany(AssignmentHistory::class);
    }

    // Scopes
    public function scopeAssigned($query)
    {
        return $query->whereNotNull('lecturer_code');
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('lecturer_code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSemester($query, $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    public function scopeForLecturer($query, $lecturerCode)
    {
        return $query->where('lecturer_code', $lecturerCode);
    }

    // Helper methods
    public function isAssigned(): bool
    {
        return !is_null($this->lecturer_code);
    }

    public function canBeAssignedTo(User $lecturer): bool
    {
        // Check if lecturer has required qualifications
        $hasQualification = $lecturer->school_id === $this->unit->school_id || 
                           $lecturer->program_id === $this->unit->program_id;
        
        // Check workload limits
        $currentWorkload = static::where('lecturer_code', $lecturer->code)
            ->where('semester_id', $this->semester_id)
            ->count();
            
        $workloadLimit = LecturerWorkloadLimit::where('lecturer_code', $lecturer->code)
            ->where('semester_id', $this->semester_id)
            ->first();
            
        $maxUnits = $workloadLimit ? $workloadLimit->max_units : 10;
        
        return $hasQualification && $currentWorkload < $maxUnits;
    }
}

// New model: LecturerWorkloadLimit
// app/Models/LecturerWorkloadLimit.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LecturerWorkloadLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'lecturer_code',
        'semester_id',
        'max_units',
        'max_credit_hours',
        'is_active',
        'notes'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_code', 'code');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForLecturer($query, $lecturerCode)
    {
        return $query->where('lecturer_code', $lecturerCode);
    }

    public function scopeForSemester($query, $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }
}

// New model: LecturerSpecialization
// app/Models/LecturerSpecialization.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LecturerSpecialization extends Model
{
    use HasFactory;

    protected $fillable = [
        'lecturer_code',
        'unit_id',
        'proficiency_level',
        'is_preferred',
        'notes'
    ];

    protected $casts = [
        'is_preferred' => 'boolean'
    ];

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_code', 'code');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function scopePreferred($query)
    {
        return $query->where('is_preferred', true);
    }

    public function scopeByProficiency($query, $level)
    {
        return $query->where('proficiency_level', $level);
    }

    public function scopeForLecturer($query, $lecturerCode)
    {
        return $query->where('lecturer_code', $lecturerCode);
    }

    public function scopeForUnit($query, $unitId)
    {
        return $query->where('unit_id', $unitId);
    }
}

// New model: AssignmentHistory
// app/Models/AssignmentHistory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_assignment_id',
        'action',
        'previous_lecturer_code',
        'new_lecturer_code',
        'changed_by',
        'reason'
    ];

    public function unitAssignment(): BelongsTo
    {
        return $this->belongsTo(UnitAssignment::class);
    }

    public function previousLecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_lecturer_code', 'code');
    }

    public function newLecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_lecturer_code', 'code');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by', 'code');
    }

    public function scopeForAssignment($query, $assignmentId)
    {
        return $query->where('unit_assignment_id', $assignmentId);
    }

    public function scopeForLecturer($query, $lecturerCode)
    {
        return $query->where('new_lecturer_code', $lecturerCode)
                    ->orWhere('previous_lecturer_code', $lecturerCode);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }
}

// Update User model to include lecturer relationships
// Add these methods to your existing User model

class User extends Authenticatable
{
    // ... existing code ...

    // New relationships for lecturer functionality
    public function unitAssignments()
    {
        return $this->hasMany(UnitAssignment::class, 'lecturer_code', 'code');
    }

    public function workloadLimits()
    {
        return $this->hasMany(LecturerWorkloadLimit::class, 'lecturer_code', 'code');
    }

    public function specializations()
    {
        return $this->hasMany(LecturerSpecialization::class, 'lecturer_code', 'code');
    }

    public function assignmentHistory()
    {
        return $this->hasMany(AssignmentHistory::class, 'new_lecturer_code', 'code');
    }

    // Helper methods for lecturer functionality
    public function getCurrentWorkload($semesterId)
    {
        return $this->unitAssignments()
            ->where('semester_id', $semesterId)
            ->with(['unit', 'class'])
            ->get();
    }

    public function getTotalUnitsAssigned($semesterId)
    {
        return $this->unitAssignments()
            ->where('semester_id', $semesterId)
            ->count();
    }

    public function getTotalCreditHours($semesterId)
    {
        return $this->unitAssignments()
            ->where('semester_id', $semesterId)
            ->with('unit')
            ->get()
            ->sum('unit.credit_hours');
    }

    public function getWorkloadLimit($semesterId)
    {
        return $this->workloadLimits()
            ->where('semester_id', $semesterId)
            ->where('is_active', true)
            ->first();
    }

    public function canTakeMoreUnits($semesterId)
    {
        $limit = $this->getWorkloadLimit($semesterId);
        $maxUnits = $limit ? $limit->max_units : 10; // Default limit
        $currentUnits = $this->getTotalUnitsAssigned($semesterId);
        
        return $currentUnits < $maxUnits;
    }

    public function getPreferredUnits()
    {
        return $this->specializations()
            ->where('is_preferred', true)
            ->with('unit')
            ->get()
            ->pluck('unit');
    }

    public function hasSpecializationFor($unitId)
    {
        return $this->specializations()
            ->where('unit_id', $unitId)
            ->exists();
    }

    public function getSpecializationLevel($unitId)
    {
        $specialization = $this->specializations()
            ->where('unit_id', $unitId)
            ->first();
            
        return $specialization ? $specialization->proficiency_level : null;
    }

    // Scope for lecturers only
    public function scopeLecturers($query)
    {
        return $query->role('Lecturer');
    }

    public function scopeAvailableForSemester($query, $semesterId)
    {
        return $query->lecturers()->whereHas('workloadLimits', function($q) use ($semesterId) {
            $q->where('semester_id', $semesterId)
              ->where('is_active', true);
        })->orWhereDoesntHave('workloadLimits');
    }
}