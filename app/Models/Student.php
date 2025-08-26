<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_code',
        'name',
        'email',
        'school_id',
        'program_id'
    ];

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    // Accessors for relationship data
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
}