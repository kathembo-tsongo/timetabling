<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitAssignment extends Model
{
    protected $fillable = [
        'unit_id',
        'semester_id', 
        'class_id',
        'lecturer_code',
        'assigned_at'
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    // Existing relationships
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function class()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    // ADD THIS RELATIONSHIP if it doesn't exist
    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_code', 'code');
    }
}