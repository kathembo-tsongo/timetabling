<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'group',
        'capacity',
        'current_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the program that owns the group.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Get the enrollments for this group.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'group', 'group')
            ->where('program_id', $this->program_id);
    }

    /**
     * Check if the group is full.
     */
    public function isFull(): bool
    {
        return $this->current_count >= $this->capacity;
    }

    /**
     * Get the remaining capacity of the group.
     */
    public function remainingCapacity(): int
    {
        return max(0, $this->capacity - $this->current_count);
    }
}