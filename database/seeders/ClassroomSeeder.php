<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Classroom;

class ClassroomSeeder extends Seeder
{
    public function run()
    {
        $classrooms = [
            
            ['name' => 'MSB 5', 'capacity' => 20, 'location' => 'Phase1'],
            ['name' => 'MSB 6', 'capacity' => 12, 'location' => 'Phase2'],
           
            ['name' => 'PH01', 'capacity' => 15, 'location' => 'Phase1'],
            ['name' => 'PH02', 'capacity' => 15, 'location' => 'Phase2'],
            ['name' => 'PH03', 'capacity' => 15, 'location' => 'Phase1'],
            ['name' => 'PH04', 'capacity' => 15, 'location' => 'Phase2'],
            ['name' => 'PH05', 'capacity' => 15, 'location' => 'Phase1'],
            ['name' => 'PH06', 'capacity' => 15, 'location' => 'Phase2'],
            ['name' => 'PH07', 'capacity' => 15, 'location' => 'Phase1'],
            ['name' => 'PH08', 'capacity' => 15, 'location' => 'Phase2'],
            ['name' => 'PH09', 'capacity' => 15, 'location' => 'Phase1'],
            ['name' => 'PH10', 'capacity' => 15, 'location' => 'Phase2'],
            
           
            
        ];

        foreach ($classrooms as $classroom) {
            Classroom::create($classroom);
        }
    }
}
