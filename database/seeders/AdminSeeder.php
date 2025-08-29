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
            'first_name' => 'KATHEMBO',
            'last_name' => 'TSONGO',
            'email' => 'admin@strathmore.edu',
            'phone' => '0723456789',
            'code' => 'ADM1',
            'schools' => '-',
            'programs' => null,
            'password' => Hash::make('password')
        ]);
    }
}