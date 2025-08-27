<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class AssignRoles extends Command
{
    protected $signature = 'users:assign-roles';
    protected $description = 'Assign roles to users based on their codes';

    public function handle()
    {
        // Students
        $students = User::where('code', 'like', 'BBIT%')->get();
        foreach($students as $student) {
            $student->assignRole('Student');
        }
        $this->info("Assigned Student role to {$students->count()} users");

        // Lecturers
        $lecturers = User::where('code', 'like', 'BBITLEC%')->get();
        foreach($lecturers as $lecturer) {
            $lecturer->assignRole('Lecturer');
        }
        $this->info("Assigned Lecturer role to {$lecturers->count()} users");
    }
}