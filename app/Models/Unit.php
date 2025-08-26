<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    // Relationships
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    // Scopes for filtering
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

    // Accessors for relationship data (these will work with eager loading)
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

    // Helper methods
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

    // Auto-deactivate when removing from semester
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
}