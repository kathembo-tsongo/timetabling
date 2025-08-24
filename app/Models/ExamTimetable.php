<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamTimetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'semester_id',
        'class_id',
        'examroom_id',
        'time_slot_id',
        'exam_date',
        'start_time',
        'end_time',
        'duration',
        'lecturer_id',
        'invigilator_id',
        'special_requirements',
        'status'
    ];

    protected $casts = [
        'exam_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    /**
     * Get the unit that this exam is for
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the semester this exam belongs to
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the class this exam is for
     */
    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    /**
     * Get the exam room for this exam
     */
    public function examroom()
    {
        return $this->belongsTo(Examroom::class, 'examroom_id');
    }

    /**
     * Alternative relationship name (if you use 'room' instead of 'examroom')
     */
    public function room()
    {
        return $this->belongsTo(Examroom::class, 'examroom_id');
    }

    /**
     * Get the time slot for this exam
     */
    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class, 'time_slot_id');
    }

    /**
     * Get the lecturer assigned to this exam
     */
    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    /**
     * Get the invigilator for this exam
     */
    public function invigilator()
    {
        return $this->belongsTo(User::class, 'invigilator_id');
    }

    /**
     * Get students enrolled in this exam's unit
     */
    public function students()
    {
        return $this->hasManyThrough(
            User::class,
            Enrollment::class,
            'unit_id',     // Foreign key on enrollments table
            'id',          // Foreign key on users table
            'unit_id',     // Local key on exam_timetables table
            'student_id'   // Local key on enrollments table
        )->where('enrollments.semester_id', $this->semester_id);
    }

    /**
     * Scope to get exams for a specific student
     */
    public function scopeForStudent($query, $studentId)
    {
        return $query->whereHas('students', function ($q) use ($studentId) {
            $q->where('users.id', $studentId);
        });
    }

    /**
     * Scope to get exams for a specific lecturer
     */
    public function scopeForLecturer($query, $lecturerId)
    {
        return $query->where('lecturer_id', $lecturerId)
                    ->orWhere('invigilator_id', $lecturerId);
    }
}