<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassModel extends Model
{
    use HasFactory;

    protected $table = 'classes';
    protected $primaryKey = 'id';
    protected $fillable = [
        'name',
        'school_id',
        'semester_id',
        'program_id', // Ensure this is fillable if you're using it in forms
    ];

    // Add or update the school relationship in the ClassModel
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    // Add relationships for units and semesters through the semester_unit table
    public function units()
    {
        return $this->belongsToMany(Unit::class, 'semester_unit', 'class_id', 'unit_id');
    }

    public function semesters()
    {
        return $this->belongsToMany(Semester::class, 'semester_unit', 'class_id', 'semester_id');
    }

    /**
     * Define the relationship to the Semester model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    /**
     * Define the relationship to the Program model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }
}
