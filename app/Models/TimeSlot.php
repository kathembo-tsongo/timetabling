<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeSlot extends Model
{
    use HasFactory;

    // Explicitly define the table name
    protected $table = 'time_slots';

    // Define fillable fields for mass assignment
    protected $fillable = [
        'day',
        'date',
        'start_time',
        'end_time',
    ];

    // Define relationships
    public function examTimetables()
    {
        return $this->hasMany(ExamTimetable::class);
    }
}

