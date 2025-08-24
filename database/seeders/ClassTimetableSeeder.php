
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClassTimetable;

class ClassTimetableSeeder extends Seeder
{
    public function run()
    {
        ClassTimetable::factory()->count(10)->create();
    }
}
