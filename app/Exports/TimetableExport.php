namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class TimetableExport implements FromView
{
    protected $timetables;

    public function __construct($timetables)
    {
        $this->timetables = $timetables;
    }

    public function view(): View
    {
        return view('timetables.excel', ['timetables' => $this->timetables]);
    }
}
