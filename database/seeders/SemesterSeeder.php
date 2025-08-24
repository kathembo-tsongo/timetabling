<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Semester;

class SemesterSeeder extends Seeder
{
    public function run()
    {
        $semesters = [
            
            ['name' => 'Semester 1.1'],
            ['name' => 'Semester 1.2'],
            ['name' => 'Semester 2.1'], 
            ['name' => 'Semester 2.2'],
            ['name' => 'Semester 3.1'],
            ['name' => 'Semester 3.2'],
            ['name' => 'Semester 4.1'],
            ['name' => 'Semester 4.2']
        ];

        foreach ($semesters as $semester) {
            Semester::create($semester);
        }
    }
}
