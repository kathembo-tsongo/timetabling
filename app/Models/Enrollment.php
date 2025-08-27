<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_code',
        'lecturer_code',
        'group_id',
        'unit_id',
        'class_id',
        'semester_id',
        'program_id',
        'school_id',
        'status',
        'enrollment_date'
    ];

    protected $casts = [
        'enrollment_date' => 'datetime',
    ];

    /**
     * Get the student that owns the enrollment
     * Since your database uses student_code instead of student_id,
     * we need to define the relationship using the code field
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_code', 'code');
    }

    /**
     * Get the unit that belongs to the enrollment
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    /**
     * Get the semester that belongs to the enrollment
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    /**
     * Get the class that belongs to the enrollment
     */
    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    /**
     * Get the program that belongs to the enrollment
     */
    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    /**
     * Get the school that belongs to the enrollment
     */
    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }
}