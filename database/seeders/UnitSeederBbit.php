<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Unit;

class UnitSeederBbit extends Seeder
{
    public function run()
    {
        $units = [
            ['code' => 'BIT101', 'name' => 'Introduction to Information Technology'],
            ['code' => 'BIT102', 'name' => 'Programming Fundamentals'],
            ['code' => 'BIT201', 'name' => 'Database Systems'],
            ['code' => 'BIT202', 'name' => 'Web Development'],
            ['code' => 'BIT301', 'name' => 'Software Engineering'],
            ['code' => 'BIT302', 'name' => 'Computer Networks'],
            ['code' => 'BIT303', 'name' => 'Cybersecurity Fundamentals'],
            ['code' => 'BIT304', 'name' => 'Mobile Application Development'],
            ['code' => 'BIT401', 'name' => 'Artificial Intelligence'],
            ['code' => 'BIT402', 'name' => 'Cloud Computing'],
            ['code' => 'BIT403', 'name' => 'Data Science'],
            ['code' => 'BIT404', 'name' => 'Blockchain Technology'],
            ['code' => 'BIT405', 'name' => 'Internet of Things'],
            ['code' => 'BIT406', 'name' => 'Big Data Analytics'],
            ['code' => 'BIT407', 'name' => 'Human-Computer Interaction'],
            ['code' => 'BIT408', 'name' => 'Game Development'],
            ['code' => 'BIT409', 'name' => 'Digital Marketing'],
            ['code' => 'BIT410', 'name' => 'IT Project Management'],
            ['code' => 'BIT411', 'name' => 'Ethical Hacking'],
            ['code' => 'BIT412', 'name' => 'Virtual Reality'],
            ['code' => 'BIT413', 'name' => 'Augmented Reality'],
            ['code' => 'BIT414', 'name' => 'DevOps Practices'],
            ['code' => 'BIT415', 'name' => 'Machine Learning'],
            ['code' => 'BIT416', 'name' => 'Natural Language Processing'],
            ['code' => 'BIT417', 'name' => 'Quantum Computing'],
            ['code' => 'BIT418', 'name' => 'Robotics'],
            ['code' => 'BIT419', 'name' => 'IT Governance'],
            ['code' => 'BIT420', 'name' => 'IT Risk Management'],
            ['code' => 'BIT421', 'name' => 'Digital Transformation'],
            ['code' => 'BIT422', 'name' => 'E-Commerce Systems'],
            ['code' => 'BIT423', 'name' => 'Cloud Security'],
            ['code' => 'BIT424', 'name' => 'Mobile Security'],
            ['code' => 'BIT425', 'name' => 'Data Visualization'],
            ['code' => 'BIT426', 'name' => 'IT Compliance'],
            ['code' => 'BIT427', 'name' => 'IT Service Management'],
            ['code' => 'BIT428', 'name' => 'Software Testing'],
            ['code' => 'BIT429', 'name' => 'Embedded Systems'],
            ['code' => 'BIT430', 'name' => 'Digital Forensics'],
            ['code' => 'BIT431', 'name' => 'IT Entrepreneurship'],
            ['code' => 'BIT432', 'name' => 'Cloud Architecture'],
            ['code' => 'BIT433', 'name' => 'IT Strategy'],
            ['code' => 'BIT434', 'name' => 'IT Audit'],
            ['code' => 'BIT435', 'name' => 'IT Infrastructure'],
            ['code' => 'BIT436', 'name' => 'IT Support'],
            ['code' => 'BIT437', 'name' => 'IT Ethics'],
            ['code' => 'BIT438', 'name' => 'IT Law'],
            ['code' => 'BIT439', 'name' => 'IT Communication'],
            ['code' => 'BIT440', 'name' => 'IT Leadership'],
        ];

        $totalUnits = count($units);
        $fourCreditCount = (int) ($totalUnits * 0.4); // 40% of units

        foreach ($units as $index => $unit) {
            $unit['credit_hours'] = $index < $fourCreditCount ? 4 : 3; // Assign 4 credits to the first 40%, 3 credits to the rest
            Unit::create($unit);
        }
    }
}
