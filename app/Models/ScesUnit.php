<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScesUnit extends Model
{
    protected $table = 'sces_units';
    protected $guarded = [];

    // Example relationship for scesEnrollments if needed
    public function scesEnrollments()
    {
        return $this->hasMany(\App\Models\ScesEnrollment::class, 'unit_id');
    }
}