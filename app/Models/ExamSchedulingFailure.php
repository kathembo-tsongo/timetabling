<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'student_count' => 'integer',
    ];

    // Relationships
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}