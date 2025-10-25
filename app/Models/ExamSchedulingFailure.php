<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSchedulingFailure extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'semester_id',
        'program_id',
        'school_id',
        'unit_id',
        'unit_code',
        'unit_name',
        'class_ids',
        'class_names',
        'student_count',
        'attempted_date',
        'attempted_start_time',
        'attempted_end_time',
        'assigned_slot_number',
        'failure_reason',
        'conflict_details',
        'status',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'class_ids' => 'array',
        'conflict_details' => 'array',
        'attempted_date' => 'date',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the semester that owns the failure.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the program that owns the failure.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Get the unit that owns the failure.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the user who resolved the failure.
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope to get pending failures only.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get failures by batch.
     */
    public function scopeByBatch($query, $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    /**
     * Scope to get failures by semester.
     */
    public function scopeBySemester($query, $semesterId)
    {
        return $query->where('semester_id', $semesterId);
    }

    /**
     * Mark failure as resolved.
     */
    public function markAsResolved($userId, $notes = null)
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'resolution_notes' => $notes,
        ]);
    }

    /**
     * Mark failure as retried.
     */
    public function markAsRetried($userId, $notes = null)
    {
        $this->update([
            'status' => 'retried',
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'resolution_notes' => $notes,
        ]);
    }

    /**
     * Mark failure as ignored.
     */
    public function markAsIgnored($userId, $notes = null)
    {
        $this->update([
            'status' => 'ignored',
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'resolution_notes' => $notes,
        ]);
    }

    /**
     * Get formatted class names as string.
     */
    public function getFormattedClassNamesAttribute()
    {
        if (is_array($this->class_names)) {
            return implode(', ', $this->class_names);
        }
        return $this->class_names;
    }

    /**
     * Get formatted time slot.
     */
    public function getFormattedTimeSlotAttribute()
    {
        if (!$this->attempted_start_time || !$this->attempted_end_time) {
            return 'N/A';
        }
        
        return date('H:i', strtotime($this->attempted_start_time)) . ' - ' . 
               date('H:i', strtotime($this->attempted_end_time));
    }
}