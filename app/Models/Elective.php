<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Elective extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'category',
        'year_level',
        'semester_offered',
        'max_students',
        'min_students',
        'is_active',
        'description',
        'prerequisites',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'year_level' => 'integer',
        'max_students' => 'integer',
        'min_students' => 'integer',
    ];

    /**
     * Get the unit that this elective belongs to
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Scope to get only active electives
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by year level
     */
    public function scopeYearLevel($query, int $yearLevel)
    {
        return $query->where('year_level', $yearLevel);
    }

    /**
     * Get the formatted category name
     */
    public function getCategoryNameAttribute(): string
    {
        return ucfirst($this->category);
    }

    /**
     * Check if elective has available spots
     */
    public function hasAvailableSpots(): bool
    {
        if ($this->max_students === null) {
            return true;
        }

        $currentEnrollments = $this->unit->enrollments()
            ->where('status', 'enrolled')
            ->count();

        return $currentEnrollments < $this->max_students;
    }

    /**
     * Get available spots count
     */
    public function getAvailableSpotsAttribute(): ?int
    {
        if ($this->max_students === null) {
            return null;
        }

        $currentEnrollments = $this->unit->enrollments()
            ->where('status', 'enrolled')
            ->count();

        return max(0, $this->max_students - $currentEnrollments);
    }

    /**
     * Get current enrollment count
     */
    public function getCurrentEnrollmentAttribute(): int
    {
        return $this->unit->enrollments()
            ->where('status', 'enrolled')
            ->count();
    }
}