<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'start_date',
        'end_date',
        'is_active',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get semesters for this academic year
     */
    public function semesters()
    {
        return $this->hasMany(Semester::class, 'academic_year_id');
    }

    /**
     * Get intake types available for this academic year (many-to-many)
     */
    public function intakeTypes()
    {
        return $this->belongsToMany(IntakeType::class, 'academic_year_intake_type')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    /**
     * Get only active intake types for this academic year
     */
    public function activeIntakeTypes()
    {
        return $this->belongsToMany(IntakeType::class, 'academic_year_intake_type')
            ->wherePivot('is_active', true)
            ->withPivot('is_active')
            ->withTimestamps();
    }

    /**
     * Scope to get only active academic years
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort_order and year
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('year', 'desc');
    }

    /**
     * Check if this academic year is currently active based on dates
     */
    public function isCurrentlyActive()
    {
        if (!$this->start_date || !$this->end_date) {
            return false;
        }

        $now = now();
        return $now->between($this->start_date, $this->end_date);
    }

    /**
     * Get the status of the academic year
     */
    public function getStatusAttribute()
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        if (!$this->start_date || !$this->end_date) {
            return 'no_dates';
        }

        $now = now();
        
        if ($now->lt($this->start_date)) {
            return 'upcoming';
        } elseif ($now->gt($this->end_date)) {
            return 'past';
        } else {
            return 'current';
        }
    }
}