<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LecturerSeeder extends Seeder
{
    public function run(): void
    {
        $programs = [
            'BSICS' => 'SCES',
            'BBIT' => 'SCES',
            'BSEEE' => 'SCES',
            'BSCNCS' => 'SCES',
        ];

        foreach ($programs as $program => $school) {
            for ($i = 1; $i <= 20; $i++) {
                User::create([
                    'first_name' => 'Lecturer' . $i,
                    'last_name' => $program,
                    'email' => strtolower($program) . 'lecturer' . $i . '@strathmore.edu',
                    'phone' => '071000' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'code' => strtoupper($program) . 'LEC' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'schools' => $school,
                    'programs' => $program,
                    'password' => Hash::make('password'),
                ])->assignRole('Lecturer');
            }
        }
    }
}
