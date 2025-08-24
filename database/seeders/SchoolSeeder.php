<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\School;

class SchoolSeeder extends Seeder
{
    public function run()
    {
        $schools = [
            [
                'name' => 'School of Computing and Engineering Sciences',
                'code' => 'SCES',
                'description' => 'Computing, Engineering, and Technology programs',
                'is_active' => true
            ],
            [
                'name' => 'School of Business Studies',
                'code' => 'SBS',
                'description' => 'Business, Management, and Commerce programs',
                'is_active' => true
            ],
            [
                'name' => 'Strathmore of Law School',
                'code' => 'SLS',
                'description' => 'Law, Legal Studies, and Paralegal programs',
                'is_active' => true
            ],
            [
                'name' => 'Strathmore School of Tourism and Hospitality',
                'code' => 'TOURISM',
                'description' => 'Tourism, Hospitality, and Travel programs',
                'is_active' => true
            ],
            [
                'name' => 'School of Health and Medicine',
                'code' => 'SHM',
                'description' => 'Health Sciences, Medical, and Nursing programs',
                'is_active' => true
            ],
        ];

        foreach ($schools as $schoolData) {
            School::updateOrCreate(
                ['code' => $schoolData['code']],
                $schoolData
            );
            
            $this->command->info("Created/Updated school: {$schoolData['name']} ({$schoolData['code']})");
        }
    }
}
