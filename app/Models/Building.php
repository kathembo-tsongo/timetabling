<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
    use HasFactory;

    // Specify the table name since your table is 'building' not 'buildings'
    protected $table = 'building';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'address',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the classrooms for the building.
     */
    public function classrooms()
    {
        return $this->hasMany(Classroom::class);
    }

    /**
     * Scope a query to only include active buildings.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the building's display name (name with code).
     */
    public function getDisplayNameAttribute()
    {
        return $this->name . ' (' . $this->code . ')';
    }
}