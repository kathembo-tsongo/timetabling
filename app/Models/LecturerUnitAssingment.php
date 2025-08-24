<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LecturerUnitAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['lecturer_id', 'unit_id'];

    public function lecturer()
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
