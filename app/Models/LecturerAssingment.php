<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LecturerAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'semester_id',
        'lecturer_code',
        'lecturer_name',
        'lecturer_email',
        'school_id',
        'program_id',
        'credit_hours',
        'notes',
        'is_active',
        'assigned_by',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the unit that is assigned.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the semester for this assignment.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the school this assignment belongs to.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the program this assignment belongs to.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Get the user who made this assignment.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the lecturer for this assignment.
     * Using Spatie's role system to ensure we only get lecturers
     */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_code', 'code')
                    ->whereHas('roles', function ($query) {
                        $query->where('name', 'lecturer');
                    });
    }

    /**
     * Scope to get assignments for a specific semester.
     */
    public function scopeForSemester($query, $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    /**
     * Scope to get assignments for a specific lecturer.
     */
    public function scopeForLecturer($query, $lecturerCode)
    {
        return $query->where('lecturer_code', $lecturerCode);
    }

    /**
     * Scope to get active assignments only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get assignments for a specific school.
     */
    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    /**
     * Scope to get assignments for a specific program.
     */
    public function scopeForProgram($query, $programId)
    {
        return $query->where('program_id', $programId);
    }

    /**
     * Get the total credit hours for a lecturer in a semester.
     */
    public static function getLecturerWorkload($lecturerCode, $semesterId)
    {
        return self::where('lecturer_code', $lecturerCode)
            ->where('semester_id', $semesterId)
            ->where('is_active', true)
            ->sum('credit_hours');
    }

    /**
     * Get assignments statistics for a semester.
     */
    public static function getSemesterStats($semesterId)
    {
        $totalUnits = Unit::where('is_active', true)->count();
        $assignedUnits = self::where('semester_id', $semesterId)
            ->where('is_active', true)
            ->distinct('unit_id')
            ->count();

        return [
            'total_units' => $totalUnits,
            'assigned_units' => $assignedUnits,
            'unassigned_units' => $totalUnits - $assignedUnits,
            'total_lecturers' => self::where('semester_id', $semesterId)
                ->where('is_active', true)
                ->distinct('lecturer_code')
                ->count(),
        ];
    }
}