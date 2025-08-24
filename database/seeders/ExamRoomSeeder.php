<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Examroom;

class ExamroomSeeder extends Seeder
{
    public function run()
    {
        $examrooms = [
            ['name' => 'Bleu Sky', 'capacity' => 40, 'location' => 'Phase1'],
            ['name' => 'AUDITORIUM', 'capacity' => 50, 'location' => 'Phase2'],
            ['name' => 'STMB 5th Floor', 'capacity' => 45, 'location' => 'Phase2'],
        ];

        foreach ($examrooms as $examroom) {
            Examroom::create($examroom);
        }
    }
}
