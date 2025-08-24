<?php
// app/Models/ScesEnrollment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScesEnrollment extends Model
{
    use HasFactory;

    protected $table = 'sces_enrollments';

    protected $fillable = [
        'student_code',
        'lecturer_code',
        'group_id',
        'unit_id',
        'semester_id',
        'program_id',
        'school_id',
    ];

    /**
     * Get the unit that owns the enrollment.
     */
    public function unit()
    {
        return $this->belongsTo(ScesUnit::class, 'unit_id');
    }

    /**
     * Get the student for this enrollment.
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_code', 'code');
    }

    /**
     * Get the lecturer for this enrollment.
     */
    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_code', 'code');
    }

    /**
     * Get the group for this enrollment.
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the semester for this enrollment.
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the program for this enrollment.
     */
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Get the school for this enrollment.
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }
}