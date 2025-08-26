<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\School;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'code',
        'phone',
        'schools',
        'programs',
        'school_id',
        'program_id', // ADD: For student program assignment
        'name', // ✅ ADDED: Missing name field that's used in timetable
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ✅ ADDED: Accessor to get full name if 'name' field doesn't exist
    public function getNameAttribute($value)
    {
        // If name field exists, return it
        if ($value) {
            return $value;
        }
        
        // Otherwise, construct from first_name and last_name
        return trim($this->first_name . ' ' . $this->last_name);
    }

    // ADD: Accessor for student code (using existing code field)
    public function getStudentCodeAttribute()
    {
        return $this->code;
    }

    // ADD: Accessor for school name through relationship
    public function getSchoolNameAttribute()
    {
        return $this->school ? $this->school->name : null;
    }

    // ADD: Accessor for school code through relationship  
    public function getSchoolCodeAttribute()
    {
        return $this->school ? $this->school->code : null;
    }

    // ADD: Accessor for program name through relationship
    public function getProgramNameAttribute()
    {
        return $this->program ? $this->program->name : null;
    }

    // ADD: Accessor for program code through relationship
    public function getProgramCodeAttribute()
    {
        return $this->program ? $this->program->code : null;
    }

    // ✅ ADDED: Relationship with ClassTimetables as lecturer (by name)
    public function classTimetables()
    {
        return $this->hasMany(ClassTimetable::class, 'lecturer', 'name');
    }

    // ✅ ADDED: Relationship with ClassTimetables as lecturer (by code)
    public function classTimetablesByCode()
    {
        return $this->hasMany(ClassTimetable::class, 'lecturer_code', 'code');
    }

    // ✅ ADDED: Relationship to get units through enrollments (as lecturer)
    public function lecturerUnits()
    {
        return $this->hasManyThrough(
            Unit::class,
            Enrollment::class,
            'lecturer_code', // Foreign key on enrollments table
            'id',            // Foreign key on units table
            'code',          // Local key on users table
            'unit_id'        // Local key on enrollments table
        );
    }

    // ✅ ADDED: Relationship to get enrollments as lecturer
    public function lecturerEnrollments()
    {
        return $this->hasMany(Enrollment::class, 'lecturer_code', 'code');
    }

    // ✅ ADDED: Relationship to get enrollments as student
    public function studentEnrollments()
    {
        return $this->hasMany(Enrollment::class, 'student_code', 'code');
    }

    // ADD: Relationship for enrollments as student (by ID - for new enrollment system)
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    // ADD: Relationship with program
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    // ADD: Relationship with school (alias for existing assignedSchool)
    public function school()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * Get the school that this user is assigned to
     */
    public function assignedSchool()
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * Check if user can manage a specific school using Spatie permissions
     */
    public function canManageSchool($schoolCode)
    {
        $schoolCode = strtolower($schoolCode);

        // Super admin can manage all schools
        if ($this->hasRole('Admin') || $this->can('manage schools')) {
            return true;
        }

        // Check school-specific permission
        return $this->can("manage {$schoolCode} school");
    }

    /**
     * Check if user can view a specific school using Spatie permissions
     */
    public function canViewSchool($schoolCode)
    {
        $schoolCode = strtolower($schoolCode);

        // Super admin can view all schools
        if ($this->hasRole('Admin') || $this->can('view schools')) {
            return true;
        }

        // Check school-specific permission
        return $this->can("view {$schoolCode} school");
    }

    /**
     * Get schools that this user can manage based on Spatie permissions
     */
    public function getManageableSchools()
    {
        // Super admin can see all schools
        if ($this->hasRole('Admin') || $this->can('manage schools')) {
            return School::all();
        }

        // Get schools based on specific permissions
        $manageableSchools = collect();
        $schools = School::all();

        foreach ($schools as $school) {
            if ($this->canViewSchool($school->code)) {
                $manageableSchools->push($school);
            }
        }

        return $manageableSchools;
    }

    /**
     * Assign school-specific faculty admin role
     */
    public function assignToSchool($schoolCode)
    {
        $school = School::where('code', $schoolCode)->first();
        if (!$school) {
            throw new \Exception("School with code {$schoolCode} not found");
        }

        // Update school_id for reference
        $this->school_id = $school->id;
        $this->save();

        // Assign school-specific role
        $schoolRoleName = "Faculty Admin - {$schoolCode}";
        $this->assignRole($schoolRoleName);

        return $this;
    }
}