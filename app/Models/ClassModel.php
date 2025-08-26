<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassModel extends Model
{
    protected $table = 'classes';
    
    protected $fillable = [
        'name',
        'semester_id', 
        'program_id',
        'year_level',
        'section',
        'capacity',
        'students_count',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
        'students_count' => 'integer',
        'year_level' => 'integer'
    ];

    // Relationships
    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    // Scopes for common queries
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByProgram($query, $programId)
    {
        return $query->where('program_id', $programId);
    }

    public function scopeByYearLevel($query, $yearLevel)
    {
        return $query->where('year_level', $yearLevel);
    }

    public function scopeBySection($query, $section)
    {
        return $query->where('section', $section);
    }

    // Helper method to generate class code
    public function generateClassCode()
    {
        if ($this->program && $this->year_level && $this->section) {
            $sectionNumber = ord($this->section) - 64; // A=1, B=2, etc.
            return "{$this->program->code} {$this->year_level}.{$sectionNumber}";
        }
        return $this->name;
    }
}