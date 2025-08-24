<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Unit;

class EnrollmentService
{
    /**
     * Enroll a student in a unit and assign them to a group.
     *
     * @param string $studentCode
     * @param int $unitId
     * @param int $semesterId
     * @return \App\Models\Enrollment
     * @throws \Exception
     */
    public function enrollStudent(string $studentCode, int $unitId, int $semesterId): Enrollment
    {
        // Check if enrollment already exists
        $existingEnrollment = Enrollment::where([
            'student_code' => $studentCode,
            'unit_id' => $unitId,
            'semester_id' => $semesterId,
        ])->first();

        if ($existingEnrollment) {
            throw new \Exception('Student is already enrolled in this unit for the selected semester.');
        }

        // Assign the student to a group
        $group = $this->assignGroup($unitId, $semesterId);

        if (!$group) {
            throw new \Exception('All groups for this unit are full.');
        }

        // Create the enrollment
        return Enrollment::create([
            'student_code' => $studentCode,
            'unit_id' => $unitId,
            'semester_id' => $semesterId,
            'group' => $group,
        ]);
    }

    /**
     * Assign a student to a group based on capacity.
     *
     * @param int $unitId
     * @param int $semesterId
     * @return string|null
     */
    private function assignGroup(int $unitId, int $semesterId): ?string
    {
        $groups = ['A', 'B', 'C', 'D'];

        foreach ($groups as $group) {
            $groupCount = Enrollment::where('unit_id', $unitId)
                ->where('semester_id', $semesterId)
                ->where('group', $group)
                ->count();

            if ($groupCount < 60) {
                return $group; // Return the first group with available capacity
            }
        }

        return null; // All groups are full
    }
}
