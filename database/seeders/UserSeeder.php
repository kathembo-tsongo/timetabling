<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        User::create([
            'first_name' => 'Admin',
            'last_name'  => 'User',
            'email'      => 'admin@example.com',
            'code'       => 'ADM001',
            'password'   => Hash::make('password'),
        ])->assignRole('Admin');

        // Exam office user
        User::create([
            'first_name' => 'Exam',
            'last_name'  => 'Office',
            'email'      => 'exam@example.com',
            'phone'      => '1234567891',
            'code'       => 'EXM001',
            'schools'    => 'SCES',
            'password'   => Hash::make('password'),
        ])->assignRole('Exam Office'); // ← capital O, matches seeder

        // Faculty admin user
        User::create([
            'first_name' => 'Faculty',
            'last_name'  => 'Admin',
            'email'      => 'faculty@example.com',
            'phone'      => '1234567892',
            'code'       => 'FAC001',
            'schools'    => 'SCES',
            'password'   => Hash::make('password'),
        ])->assignRole('Faculty Admin - SCES'); // ← school-specific role
    }
}
