<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedExamSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'school_id',
        'class_name',
        'section',
        'unit_code',
        'unit_name',
        'student_count',
        'lecturer_name',
        'failure_reasons',
        'attempted_dates',
        'status',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
        'created_by',
    ];

    protected $casts = [
        'failure_reasons' => 'array',
        'attempted_dates' => 'array',
        'resolved_at' => 'datetime',
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
        if (empty($this->failure_reasons)) {
            return 'No conflicts recorded';
        }

        $reasons = $this->failure_reasons;
        $summary = [];

        foreach ($reasons as $reason) {
            if (isset($reason['type'])) {
                $summary[] = $reason['type'];
            }
        }

        return implode(', ', array_unique($summary));
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