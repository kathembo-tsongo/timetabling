<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'is_active',
        'description',
        'contact_email',
        'contact_phone',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function programs()
    {
        return $this->hasMany(Program::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class, 'school_code', 'code');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->code} - {$this->name}";
    }

    // Methods for safe relationship counting
    public function getProgramsCount()
    {
        try {
            return $this->programs()->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getActiveUnitsCount()
    {
        try {
            return $this->units()->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function hasProgramsRelation()
    {
        try {
            return $this->programs()->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function hasUnitsRelation()
    {
        try {
            return $this->units()->exists();
        } catch (\Exception $e) {
            return false;
        }
    }
}