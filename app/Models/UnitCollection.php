<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

class UnitCollection extends Collection
{
    /**
     * Group units by school code and return statistics
     */
    public function groupBySchool()
    {
        return $this->groupBy('school_code')->map(function ($units, $schoolCode) {
            return [
                'school_code' => $schoolCode,
                'school_name' => $units->first()->school_name ?? 'Unknown',
                'count' => $units->count(),
                'units' => $units->pluck('name')->toArray()
            ];
        });
    }

    /**
     * Group units by program code and return statistics
     */
    public function groupByProgram()
    {
        return $this->groupBy('program_code')->map(function ($units, $programCode) {
            return [
                'program_code' => $programCode,
                'program_name' => $units->first()->program_name ?? 'Unknown',
                'count' => $units->count(),
                'units' => $units->pluck('name')->toArray()
            ];
        });
    }

    /**
     * Get simple count by school code (for semester statistics)
     */
    public function countBySchool()
    {
        return $this->groupBy('school_code')->map(function ($units) {
            return $units->count();
        })->toArray();
    }

    /**
     * Get simple count by program code (for semester statistics)
     */
    public function countByProgram()
    {
        return $this->groupBy('program_code')->map(function ($units) {
            return $units->count();
        })->toArray();
    }

    /**
     * Get units statistics summary
     */
    public function getStatisticsSummary()
    {
        return [
            'total_units' => $this->count(),
            'active_units' => $this->where('is_active', true)->count(),
            'inactive_units' => $this->where('is_active', false)->count(),
            'total_credit_hours' => $this->sum('credit_hours'),
            'schools_count' => $this->pluck('school_code')->unique()->filter()->count(),
            'programs_count' => $this->pluck('program_code')->unique()->filter()->count(),
        ];
    }
}