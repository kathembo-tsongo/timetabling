<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Inertia\Inertia;

class ClassController extends Controller
{
    public function bbitClasses(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $page = $request->page ?? 1;
        $search = $request->search ?? '';
        
        // Build the base query
        $query = DB::table('bbit_classes')
            ->leftJoin('semesters', 'bbit_classes.semester_id', '=', 'semesters.id')
            ->leftJoin('programs', 'bbit_classes.program_id', '=', 'programs.id')
            ->select([
                'bbit_classes.id',
                'bbit_classes.name',
                'bbit_classes.semester_id',
                'bbit_classes.program_id',
                'bbit_classes.created_at',
                'bbit_classes.updated_at',
                'semesters.name as semester_name',
                'programs.name as program_name'
            ]);
        
        // Add search functionality
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('bbit_classes.name', 'like', '%' . $search . '%')
                  ->orWhere('semesters.name', 'like', '%' . $search . '%')
                  ->orWhere('programs.name', 'like', '%' . $search . '%');
            });
        }
        
        // Filter for BBIT program if needed
        $bbitProgram = DB::table('programs')->where('name', 'like', '%BBIT%')->first();
        if ($bbitProgram) {
            $query->where('bbit_classes.program_id', $bbitProgram->id);
        }
        
        // Get total count for pagination
        $total = $query->count();
        
        // Get paginated results
        $classes = $query->orderBy('bbit_classes.created_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();
        
        // Transform the data to match the expected format
        $transformedClasses = $classes->map(function ($class) {
            return [
                'id' => $class->id,
                'name' => $class->name,
                'semester_id' => $class->semester_id,
                'program_id' => $class->program_id,
                'created_at' => $class->created_at,
                'updated_at' => $class->updated_at,
                'semester' => $class->semester_name ? ['id' => $class->semester_id, 'name' => $class->semester_name] : null,
                'program' => $class->program_name ? ['id' => $class->program_id, 'name' => $class->program_name] : null,
            ];
        });
        
        // Create pagination object
        $paginatedClasses = new LengthAwarePaginator(
            $transformedClasses,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
        
        // Add query string to pagination links
        $paginatedClasses->withQueryString();
        
        // Get semesters and programs for dropdowns
        $semesters = DB::table('semesters')->select('id', 'name')->orderBy('name')->get();
        $programs = DB::table('programs')->select('id', 'name')->orderBy('name')->get();
        
        return Inertia::render('FacultyAdmin/sces/bbit/Classes', [
            'classes' => $paginatedClasses,
            'semesters' => $semesters,
            'programs' => $programs,
            'perPage' => (int) $perPage,
            'search' => $search,
            'userPermissions' => auth()->user()->permissions->pluck('name')->toArray(),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }
    
    public function storeBbitClass(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'semester_id' => 'required|exists:semesters,id',
            'program_id' => 'required|exists:programs,id',
        ]);
        
        try {
            DB::table('bbit_classes')->insert([
                'name' => $request->name,
                'semester_id' => $request->semester_id,
                'program_id' => $request->program_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            return redirect()->back()->with('success', 'Class created successfully!');
        } catch (\Exception $e) {
            \Log::error('Error creating BBIT class: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error creating class: ' . $e->getMessage());
        }
    }
    
    public function updateBbitClass(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'semester_id' => 'required|exists:semesters,id',
            'program_id' => 'required|exists:programs,id',
        ]);
        
        try {
            $affected = DB::table('bbit_classes')
                ->where('id', $id)
                ->update([
                    'name' => $request->name,
                    'semester_id' => $request->semester_id,
                    'program_id' => $request->program_id,
                    'updated_at' => now(),
                ]);
            
            if ($affected === 0) {
                return redirect()->back()->with('error', 'Class not found.');
            }
            
            return redirect()->back()->with('success', 'Class updated successfully!');
        } catch (\Exception $e) {
            \Log::error('Error updating BBIT class: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error updating class: ' . $e->getMessage());
        }
    }
    public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'semester_id' => 'required|exists:semesters,id',
        'program_id' => 'required|exists:programs,id',
    ]);

    try {
        DB::table('bbit_classes')->insert([
            'name' => $request->name,
            'semester_id' => $request->semester_id,
            'program_id' => $request->program_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Class created successfully!');
    } catch (\Exception $e) {
        \Log::error('Error creating BBIT class: ' . $e->getMessage());
        return redirect()->back()->with('error', 'Error creating class: ' . $e->getMessage());
    }
}

    
    public function destroyBbitClass($id)
    {
        try {
            // Check if class exists
            $class = DB::table('bbit_classes')->where('id', $id)->first();
            
            if (!$class) {
                return redirect()->back()->with('error', 'Class not found.');
            }
            
            // Delete the class
            $deleted = DB::table('bbit_classes')->where('id', $id)->delete();
            
            if ($deleted) {
                return redirect()->back()->with('success', 'Class deleted successfully!');
            } else {
                return redirect()->back()->with('error', 'Failed to delete class.');
            }
        } catch (\Exception $e) {
            \Log::error('Error deleting BBIT class: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error deleting class: ' . $e->getMessage());
        }
    }
    
    public function getBbitClassSemesters()
    {
        try {
            $semesters = DB::table('semesters')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
            
            return response()->json($semesters);
        } catch (\Exception $e) {
            \Log::error('Error fetching semesters: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch semesters'], 500);
        }
    }
    
    public function getBbitClassPrograms()
    {
        try {
            $programs = DB::table('programs')
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
            
            return response()->json($programs);
        } catch (\Exception $e) {
            \Log::error('Error fetching programs: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch programs'], 500);
        }
    }
    
    // Additional helper method to get class statistics
    public function getBbitClassStats()
    {
        try {
            $stats = [
                'total_classes' => DB::table('bbit_classes')->count(),
                'classes_by_semester' => DB::table('bbit_classes')
                    ->join('semesters', 'bbit_classes.semester_id', '=', 'semesters.id')
                    ->select('semesters.name', DB::raw('count(*) as count'))
                    ->groupBy('semesters.id', 'semesters.name')
                    ->get(),
                'classes_by_program' => DB::table('bbit_classes')
                    ->join('programs', 'bbit_classes.program_id', '=', 'programs.id')
                    ->select('programs.name', DB::raw('count(*) as count'))
                    ->groupBy('programs.id', 'programs.name')
                    ->get(),
            ];
            
            return response()->json($stats);
        } catch (\Exception $e) {
            \Log::error('Error fetching class stats: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch statistics'], 500);
        }
    }
    
    // Method to bulk operations
    public function bulkDeleteBbitClasses(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:bbit_classes,id'
        ]);
        
        try {
            $deleted = DB::table('bbit_classes')->whereIn('id', $request->ids)->delete();
            
            return redirect()->back()->with('success', "Successfully deleted {$deleted} classes.");
        } catch (\Exception $e) {
            \Log::error('Error bulk deleting BBIT classes: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error deleting classes: ' . $e->getMessage());
        }
    }
}