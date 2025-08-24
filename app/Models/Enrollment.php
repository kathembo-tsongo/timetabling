<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_code',
        'lecturer_code',
        'group_id',
        'unit_id',
        'semester_id',
        'program_id',
        'school_id',
        'class_id', // ✅ ADDED: Missing class_id field
    ];

    /**
     * Get the student associated with the enrollment.
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_code', 'code')
                ->select(['id', 'code', 'first_name', 'last_name']);
    }

    /**
     * Get the lecturer associated with the enrollment.
     */
    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_code', 'code');
    }

    /**
     * Get the unit associated with the enrollment.
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id')->withDefault();
    }

    /**
     * Get the group associated with the enrollment.
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id')->withDefault();
    }

    /**
     * Get the semester associated with the enrollment.
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id')->withDefault();
    }

    /**
     * Get the program associated with the enrollment.
     */
    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    /**
     * Get the school associated with the enrollment.
     */
    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }
}
