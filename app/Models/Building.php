<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Building extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'building';
    protected $fillable = [
        'name',
        'code',
        'description',
        'address',
        'classroom',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get all classrooms in this building
     */
    public function classroom()
    {
        return $this->hasMany(Classroom::class);
    }

    /**
     * Get active classrooms only
     */
    public function activeClassrooms()
    {
        return $this->hasMany(Classroom::class)->where('is_active', true);
    }

    /**
     * Scope for active buildings
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for inactive buildings
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Get building status badge
     */
    public function getStatusBadgeAttribute()
    {
        return $this->is_active ? 
            '<span class="badge badge-success">Active</span>' : 
            '<span class="badge badge-danger">Inactive</span>';
    }

    /**
     * Get classroom count
     */
    public function getClassroomCountAttribute()
    {
        return $this->classroom()->count();
    }
}