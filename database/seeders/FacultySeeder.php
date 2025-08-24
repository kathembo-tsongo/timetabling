<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faculty;

class FacultySeeder extends Seeder
{
    public function run()
    {
        $faculties = [
            ['name' => 'SCES'], // School of Computer and Engineering Sciences
            ['name' => 'SBS'],  // School of Business Studies
            ['name' => 'SL'],   // School of Law
            ['name' => 'SM'],   // School of Medicine
            ['name' => 'TM'],   // Technical Management
        ];

        foreach ($faculties as $faculty) {
            Faculty::create($faculty);
        }
    }
}
