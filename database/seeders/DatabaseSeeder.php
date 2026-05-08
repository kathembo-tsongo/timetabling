<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Database\Seeders\UnitSeederBbit;
use Database\Seeders\ClassroomSeeder;
use Database\Seeders\ExamRoomSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\GroupSeeder;
use Database\Seeders\LecturerSeeder;
use Database\Seeders\StudentSeeder;
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            UserSeeder::class,
            LecturerSeeder::class,
            StudentSeeder::class,
            ClassroomSeeder::class,
            ExamRoomSeeder::class,
            UnitSeederBbit::class,
            SemesterSeeder::class,
            AdminSeeder::class,
            TimeSlotSeeder::class,
            ClassTimeSlotSeeder::class,
        ]);
    }
}
