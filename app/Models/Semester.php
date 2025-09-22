<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Add these imports
use App\Models\Unit;
use App\Models\Enrollment;
use App\Models\ClassTimetable;
use App\Models\ExamTimetable;

class Semester extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'start_date', 
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    public function unitAssignments()
{
    return $this->hasMany(UnitAssignment::class);
}

    public function classTimetables()
    {
        return $this->hasMany(ClassTimetable::class);
    }

    public function examTimetables()
    {
        return $this->hasMany(ExamTimetable::class);
    }
}