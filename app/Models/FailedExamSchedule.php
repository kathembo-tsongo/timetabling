<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedExamSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        // ✅ REQUIRED (NOT NULL in DB)
        'batch_id',
        'semester_id',
        'unit_id',
        'unit_code',
        'unit_name',
        'class_ids',           // JSON
        'class_names',         // TEXT (not class_name)
        'student_count',
        
        // ✅ OPTIONAL (nullable in DB)
        'program_id',
        'school_id',
        'attempted_date',
        'attempted_start_time',
        'attempted_end_time',
        'assigned_slot_number',
        'failure_reason',      // TEXT (not failure_reasons)
        'conflict_details',    // JSON
        'status',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'class_ids' => 'array',          // ✅ JSON field
        'conflict_details' => 'array',   // ✅ JSON field
        'resolved_at' => 'datetime',
        'attempted_date' => 'date',
        'student_count' => 'integer',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeForProgram($query, $programId)
    {
        return $query->where('program_id', $programId);
    }

    public function scopeForSchool($query, $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function getConflictSummaryAttribute(): string
    {
        if (empty($this->conflict_details)) {
            return 'No conflicts recorded';
        }

        $details = $this->conflict_details;
        $summary = [];

        if (is_array($details)) {
            foreach ($details as $key => $value) {
                if (is_string($value)) {
                    $summary[] = $value;
                }
            }
        }

        return implode(', ', array_unique($summary)) ?: 'Unknown conflict';
    }

    public function markAsResolved(User $user, ?string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_by' => $user->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }

    public function markAsIgnored(User $user, ?string $notes = null): void
    {
        $this->update([
            'status' => 'ignored',
            'resolved_by' => $user->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);
    }
}