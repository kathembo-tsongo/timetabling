<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::create([
            'code' => '112721',
            'first_name' => 'Kathembo',
            'last_name' => 'Tsongo',
            'faculty' => 'SCES',
            'email' => 'kathembo.dieudonne@strathmore.edu',
            'phone' => '0706571995',       
            'password' => Hash::make('password'), // Change 'password' to a secure password
        ]);
    }
}