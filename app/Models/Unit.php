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
        'description',
        'credit_hours',
        'school_code',
        'program_code',
        'semester_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credit_hours' => 'integer',
    ];

    // Relationships
    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    // Scopes for filtering by school/program
    public function scopeForSchool($query, $schoolCode)
    {
        return $query->where('school_code', strtoupper($schoolCode));
    }

    public function scopeForProgram($query, $programCode)
    {
        return $query->where('program_code', strtoupper($programCode));
    }

    public function scopeForSchoolAndProgram($query, $schoolCode, $programCode)
    {
        return $query->where('school_code', strtoupper($schoolCode))
                    ->where('program_code', strtoupper($programCode));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSemester($query, $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    // Accessors
    public function getSchoolNameAttribute()
    {
        return match($this->school_code) {
            'SCES' => 'School of Computing and Engineering Sciences',
            'SBS' => 'School of Business Studies',
            default => $this->school_code
        };
    }

    public function getProgramNameAttribute()
    {
        return match($this->program_code) {
            'BBIT' => 'Bachelor of Business Information Technology',
            'ICS' => 'Information Communication Systems',
            'CS' => 'Computer Science',
            'MBA' => 'Master of Business Administration',
            'BBA' => 'Bachelor of Business Administration',
            'BCOM' => 'Bachelor of Commerce',
            default => $this->program_code
        };
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

    // Static helper methods
    public static function getSchoolOptions()
    {
        return [
            'SCES' => 'School of Computing and Engineering Sciences',
            'SBS' => 'School of Business Studies',
        ];
    }

    public static function getProgramOptions($schoolCode = null)
    {
        $programs = [
            'SCES' => [
                'BBIT' => 'Bachelor of Business Information Technology',
                'ICS' => 'Information Communication Systems', 
                'CS' => 'Computer Science',
            ],
            'SBS' => [
                'MBA' => 'Master of Business Administration',
                'BBA' => 'Bachelor of Business Administration',
                'BCOM' => 'Bachelor of Commerce',
            ],
        ];

        return $schoolCode ? ($programs[strtoupper($schoolCode)] ?? []) : $programs;
    }

    public static function getAllProgramCodes()
    {
        return ['BBIT', 'ICS', 'CS', 'MBA', 'BBA', 'BCOM'];
    }
}