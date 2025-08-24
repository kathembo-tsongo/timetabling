<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $programs = [
            // 'BSICS' => 'SCES',
            // 'BBIT' => 'SCES',
            // 'BSEEE' => 'SCES',
            'BSCNCS' => 'SCES',
        ];

        foreach ($programs as $program => $school) {
            for ($i = 1; $i <= 400; $i++) {
                User::create([
                    'first_name' => 'Student' . $i,
                    'last_name' => $program,
                    'email' => strtolower($program) . $i . '@strathmore.edu',
                    'phone' => '07003' . str_pad($i, 4, '0', STR_PAD_LEFT),
                    'code' => strtoupper($program) . str_pad($i, 4, '0', STR_PAD_LEFT),
                    'schools' => $school,
                    'programs' => $program,
                    'password' => Hash::make('password'),
                ])->assignRole('Student');
            }
        }

        // Uncomment the following lines to create additional users for each program

        
        // foreach ($programs as $program => $school) {
        //     // Start from 400 and create 130 users (400-529)
        //     for ($i = 401; $i < 530; $i++) {
        //         User::create([
        //             'first_name' => 'Student' . $i,
        //             'last_name' => $program,
        //             'email' => strtolower($program) . $i . '@strathmore.edu',
        //             'phone' => '07013' . str_pad($i, 4, '0', STR_PAD_LEFT),
        //             'code' => strtoupper($program) . str_pad($i, 4, '0', STR_PAD_LEFT),
        //             'schools' => $school,
        //             'programs' => $program,
        //             'password' => Hash::make('password'),
        //         ])->assignRole('Student');
        //     }
        // }
    }
}
