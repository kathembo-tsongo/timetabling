<?php
namespace App\Http\Controllers;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LecturerAssignmentController extends Controller
{
    /**
     * Remove the specified lecturer assignment.
     *
     * @param  int  $unitId
     * @param  string  $lecturerCode
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroyByUnit($unitId)
    {
        try {
            // Remove all lecturer assignments for this unit
            Enrollment::where('unit_id', $unitId)
                ->update(['lecturer_code' => null]);

            return redirect()->back()->with('success', 'All lecturer assignments for this unit deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting lecturer assignments by unit: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to delete lecturer assignments by unit.']);
        }
    }
}
