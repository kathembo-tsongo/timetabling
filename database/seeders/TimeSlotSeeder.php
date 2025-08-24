<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TimeSlotSeeder extends Seeder
{
    public function run()
    {
        $startTime = strtotime('08:00:00'); // Start time: 8:00 AM
        $endTime = strtotime('17:30:00'); // End time: 5:30 PM
        $slotDuration = 2 * 60 * 60; // 2 hours in seconds
        $breakDuration = 30 * 60; // 30 minutes in seconds

        $startDate = '2025-03-03'; // Starting date (Monday of the first week)
        $timeSlots = [];

        // Loop through 14 days (2 weeks) and skip weekends
        for ($dayCount = 0; $dayCount < 14; $dayCount++) {
            $currentDate = date('Y-m-d', strtotime($startDate . " +{$dayCount} days"));
            $dayOfWeek = date('l', strtotime($currentDate)); // Get the day of the week

            // Skip Saturdays and Sundays
            if ($dayOfWeek === 'Saturday' || $dayOfWeek === 'Sunday') {
                continue;
            }

            $currentStartTime = $startTime;

            while ($currentStartTime + $slotDuration <= $endTime) {
                $timeSlots[] = [
                    'day' => $dayOfWeek,
                    'date' => $currentDate,
                    'start_time' => date('H:i:s', $currentStartTime),
                    'end_time' => date('H:i:s', $currentStartTime + $slotDuration),
                ];

                // Move to the next slot (add slot duration + break duration)
                $currentStartTime += $slotDuration + $breakDuration;
            }
        }

        // Insert the generated time slots into the database
        DB::table('time_slots')->insert($timeSlots);
    }
}
