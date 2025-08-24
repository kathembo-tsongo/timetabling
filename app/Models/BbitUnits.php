<?php
// app/Models/BbitUnit.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BbitUnit extends Model
{
    use HasFactory;

    protected $table = 'bbit_units';

    protected $fillable = [
        'name',
        'code',
        'credit_hours',
        'is_active',
        'semester_id', // Add this field for semester relationship
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credit_hours' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the BBIT enrollments for this unit.
     */
    public function enrollments()
    {
        return $this->hasMany(BbitEnrollment::class, 'unit_id');
    }

    /**
     * Get the semester this unit belongs to.
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    /**
     * Get students enrolled in this unit.
     */
    public function students()
    {
        return $this->hasManyThrough(
            User::class,
            BbitEnrollment::class,
            'unit_id',      // Foreign key on BbitEnrollment table
            'code',         // Foreign key on User table
            'id',           // Local key on BbitUnit table
            'student_code'  // Local key on BbitEnrollment table
        );
    }

    /**
     * Get lecturers assigned to this unit.
     */
    public function lecturers()
    {
        return $this->hasManyThrough(
            User::class,
            BbitEnrollment::class,
            'unit_id',       // Foreign key on BbitEnrollment table
            'code',          // Foreign key on User table
            'id',            // Local key on BbitUnit table
            'lecturer_code'  // Local key on BbitEnrollment table
        )->distinct();
    }

    /**
     * Scope for active units only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope for units in a specific semester
     */
    public function scopeForSemester($query, $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    /**
     * Get the full unit display name
     */
    public function getFullNameAttribute()
    {
        return $this->code . ' - ' . $this->name;
    }

    /**
     * Get enrollment count for this unit
     */
    public function getEnrollmentCountAttribute()
    {
        return $this->enrollments()->count();
    }
}