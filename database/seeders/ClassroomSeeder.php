<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Classroom;
use Illuminate\Support\Facades\DB;
class ClassroomSeeder extends Seeder
{
    public function run()
    {
        // Get building IDs
        $mainBuilding = DB::table('buildings')->where('code', 'MAIN')->value('id') ?? 1;
        $sciBuilding  = DB::table('buildings')->where('code', 'SCI')->value('id') ?? 2;

        $classrooms = [
            ['name' => 'MSB 5', 'code' => 'MSB5',  'capacity' => 20, 'location' => 'Phase1', 'building_id' => $mainBuilding],
            ['name' => 'MSB 6', 'code' => 'MSB6',  'capacity' => 12, 'location' => 'Phase2', 'building_id' => $mainBuilding],
            ['name' => 'PH01',  'code' => 'PH01',  'capacity' => 15, 'location' => 'Phase1', 'building_id' => $sciBuilding],
            ['name' => 'PH02',  'code' => 'PH02',  'capacity' => 15, 'location' => 'Phase2', 'building_id' => $sciBuilding],
            ['name' => 'PH03',  'code' => 'PH03',  'capacity' => 15, 'location' => 'Phase1', 'building_id' => $sciBuilding],
            ['name' => 'PH04',  'code' => 'PH04',  'capacity' => 15, 'location' => 'Phase2', 'building_id' => $sciBuilding],
            ['name' => 'PH05',  'code' => 'PH05',  'capacity' => 15, 'location' => 'Phase1', 'building_id' => $sciBuilding],
            ['name' => 'PH06',  'code' => 'PH06',  'capacity' => 15, 'location' => 'Phase2', 'building_id' => $sciBuilding],
            ['name' => 'PH07',  'code' => 'PH07',  'capacity' => 15, 'location' => 'Phase1', 'building_id' => $sciBuilding],
            ['name' => 'PH08',  'code' => 'PH08',  'capacity' => 15, 'location' => 'Phase2', 'building_id' => $sciBuilding],
            ['name' => 'PH09',  'code' => 'PH09',  'capacity' => 15, 'location' => 'Phase1', 'building_id' => $sciBuilding],
            ['name' => 'PH10',  'code' => 'PH10',  'capacity' => 15, 'location' => 'Phase2', 'building_id' => $sciBuilding],
        ];

        foreach ($classrooms as $classroom) {
            Classroom::create($classroom);
        }
    }
}
