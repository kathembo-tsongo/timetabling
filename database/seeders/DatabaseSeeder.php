<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\UnitSeederBbit;
use Database\Seeders\FacultySeeder;
use Database\Seeders\ClassroomSeeder;
use Database\Seeders\ExamroomSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\GroupSeeder;
use Database\Seeders\LecturerSeeder;
use Database\Seeders\StudentSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            RoleAndPermissionSeeder::class,  // Run this first
            UserSeeder::class,               // Then seed users
            LecturerSeeder::class,           // Seed lecturers
            StudentSeeder::class,            // Seed students
            FacultySeeder::class,            // Other seeders
            ClassroomSeeder::class,
            ExamroomSeeder::class,
            UnitSeederBbit::class,
            SemesterSeeder::class,
            AdminSeeder::class,
            TimeSlotSeeder::class,
            ClassTimeSlotSeeder::class,
            
    
          
            // Add other seeders here if needed
        ]);
        
    }
}