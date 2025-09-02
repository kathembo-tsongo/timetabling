<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Classroom extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'building_id',  // Use building_id since that's what your DB has
        'building',     // Keep building for backwards compatibility
        'floor',
        'capacity',
        'type',
        'facilities',
        'is_active',
        'location',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'facilities' => 'array',
        'is_active' => 'boolean',
        'capacity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the building that owns the classroom.
     */
    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    /**
     * Get mock usage statistics for the classroom.
     */
    public function getUsageStatsAttribute()
    {
        // Return mock data for now
        return (object) [
            'total_bookings' => rand(0, 50),
            'weekly_hours' => rand(0, 40),
            'utilization_rate' => round(rand(0, 100), 1),
            'recent_bookings' => []
        ];
    }

    /**
     * Get bookings for this classroom
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Scope a query to only include active classrooms.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by building.
     */
    public function scopeInBuilding($query, $buildingId)
    {
        return $query->where('building_id', $buildingId);
    }

    /**
     * Scope a query to filter by type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the classroom's full location string.
     */
    public function getFullLocationAttribute()
    {
        $location = $this->building ? $this->building->name : 'Unknown Building';
        
        if ($this->floor) {
            $location .= ', Floor ' . $this->floor;
        }
        
        if ($this->location) {
            $location .= ', ' . $this->location;
        }
        
        return $location;
    }

    /**
     * Check if classroom has a specific facility.
     */
    public function hasFacility($facility)
    {
        return in_array($facility, $this->facilities ?? []);
    }

    /**
     * Get available classroom types.
     */
    public static function getAvailableTypes()
    {
        return [
            'lecture_hall' => 'Lecture Hall',
            'laboratory' => 'Laboratory',
            'seminar_room' => 'Seminar Room',
            'computer_lab' => 'Computer Lab',
            'auditorium' => 'Auditorium',
            'meeting_room' => 'Meeting Room',
            'other' => 'Other'
        ];
    }

    /**
     * Get common facilities.
     */
    public static function getCommonFacilities()
    {
        return [
            'Projector',
            'Whiteboard',
            'Smart Board',
            'Audio System',
            'Air Conditioning',
            'WiFi',
            'Microphone',
            'Chairs',
            'Tables',
            'Computer',
            'Printer'
        ];
    }
}