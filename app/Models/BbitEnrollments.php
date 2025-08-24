<?php
// app/Models/BbitEnrollment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BbitEnrollments extends Model
{
    use HasFactory;

    protected $table = 'bbit_enrollments';

    protected $fillable = [
        'student_code',
        'lecturer_code',
        'group_id',
        'unit_id',
        'semester_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the student associated with the BBIT enrollment.
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_code', 'code')
            ->select(['id', 'code', 'first_name', 'last_name']);
    }

    /**
     * Get the lecturer associated with the BBIT enrollment.
     */
    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_code', 'code')
            ->select(['id', 'code', 'first_name', 'last_name']);
    }

    /**
     * Get the BBIT unit associated with the enrollment.
     */
    public function unit()
    {
        return $this->belongsTo(BbitUnit::class, 'unit_id')->withDefault();
    }

    /**
     * Get the group associated with the enrollment.
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id')->withDefault();
    }

    /**
     * Get the semester associated with the enrollment.
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id')->withDefault();
    }

    /**
     * Scope for active enrollments
     */
    public function scopeActive($query)
    {
        return $query->whereHas('unit', function ($q) {
            $q->where('is_active', 1);
        });
    }

    /**
     * Scope for current semester enrollments
     */
    public function scopeCurrentSemester($query)
    {
        return $query->whereHas('semester', function ($q) {
            $q->where('is_active', true);
        });
    }
}