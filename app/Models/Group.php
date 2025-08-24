<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'class_id',
        'capacity',
        'description'
    ];

    /**
     * Get the class that owns the group.
     */
    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    /**
     * Get the enrollments for the group.
     */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'group_id');
    }

    /**
     * ✅ REAL DATA: Get the actual count of students enrolled in this group
     */
    public function getStudentCountAttribute()
    {
        return $this->enrollments()->count();
    }

    /**
     * ✅ REAL DATA: Get students enrolled in this group for a specific semester
     */
    public function getStudentCountForSemester($semesterId)
    {
        return $this->enrollments()
            ->where('semester_id', $semesterId)
            ->count();
    }

    /**
     * ✅ REAL DATA: Get students enrolled in this group for a specific unit and semester
     */
    public function getStudentCountForUnitAndSemester($unitId, $semesterId)
    {
        return $this->enrollments()
            ->where('unit_id', $unitId)
            ->where('semester_id', $semesterId)
            ->count();
    }

    /**
     * ✅ REAL DATA: Get detailed enrollment breakdown for this group
     */
    public function getEnrollmentBreakdown($semesterId = null, $unitId = null)
    {
        $query = $this->enrollments();
        
        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }
        
        if ($unitId) {
            $query->where('unit_id', $unitId);
        }

        return [
            'total_students' => $query->count(),
            'students' => $query->with(['student:id,code,first_name,last_name'])
                ->get()
                ->map(function ($enrollment) {
                    return [
                        'student_code' => $enrollment->student_code,
                        'student_name' => $enrollment->student 
                            ? $enrollment->student->first_name . ' ' . $enrollment->student->last_name 
                            : 'Unknown',
                        'unit_id' => $enrollment->unit_id,
                        'semester_id' => $enrollment->semester_id,
                    ];
                })
        ];
    }
}
