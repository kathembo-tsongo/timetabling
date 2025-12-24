<?php

namespace App\Http\Controllers;

use App\Models\ClassModel;
use App\Models\Semester;
use App\Models\Program;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ClassController extends Controller
{
    public function index(Request $request)
{
    $perPage = $request->per_page ?? 15;
    $search = $request->search ?? '';
    $semesterId = $request->semester_id;
    $programId = $request->program_id;
    $yearLevel = $request->year_level;
    
    $user = auth()->user(); // Add this
    
    // ... rest of the query code ...
    
    return Inertia::render('Admin/Classes/Index', [
        'classes' => $classes,
        'semesters' => $semesters,
        'programs' => $programs,
        'filters' => [
            'search' => $search,
            'semester_id' => $semesterId ? (int) $semesterId : null,
            'program_id' => $programId ? (int) $programId : null,
            'year_level' => $yearLevel ? (int) $yearLevel : null,
            'per_page' => (int) $perPage,
        ],
        'can' => [
            // ✅ Fixed: Use hyphens
            'create' => $user->hasRole('Admin') || 
                       $user->can('manage-classes') || 
                       $user->can('create-classes'),
            
            'update' => $user->hasRole('Admin') || 
                       $user->can('manage-classes') || 
                       $user->can('edit-classes'),
            
            'delete' => $user->hasRole('Admin') || 
                       $user->can('manage-classes') || 
                       $user->can('delete-classes'),
        ],
        'flash' => [
            'success' => session('success'),
            'error' => session('error'),
        ],
        'errors' => session('errors') ? session('errors')->getBag('default')->toArray() : [],
    ]);
}
    public function store(Request $request)
    {
        Log::info('Form Data Received:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255', // This will be something like "BBIT 1.1"
            'semester_id' => 'required|exists:semesters,id',
            'program_id' => 'required|exists:programs,id',
            'year_level' => 'nullable|integer|min:1|max:6',
            'section' => 'required|string|max:10', // This will be A, B, C, etc.
            'capacity' => 'nullable|integer|min:1|max:200',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }

        try {
            // Use the name as provided (e.g., "BBIT 1.1") and section separately
            $className = $request->name;
            $section = strtoupper($request->section);

            // Check for duplicate class names with same section
            $existingClass = ClassModel::where('name', $className)
                ->where('section', $section)
                ->where('semester_id', $request->semester_id)
                ->where('program_id', $request->program_id)
                ->first();

            if ($existingClass) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', "Class '{$className}' Section '{$section}' already exists for this semester and program.");
            }

            ClassModel::create([
                'name' => $className,
                'semester_id' => $request->semester_id,
                'program_id' => $request->program_id,
                'year_level' => $request->year_level,
                'section' => $section,
                'capacity' => $request->capacity,
                'students_count' => 0,
                'is_active' => true,
            ]);
            
            return redirect()->back()->with('success', 'Class created successfully!');
        } catch (\Exception $e) {
            Log::error('Error creating class: ' . $e->getMessage());
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error creating class. Please try again.');
        }
    }
     public function bulkStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'classes' => 'required|array|min:1',
            'classes.*.semester_id' => 'required|exists:semesters,id',
            'classes.*.program_id' => 'required|exists:programs,id',
            'classes.*.year_level' => 'required|integer|min:1|max:6',
            'classes.*.section' => 'required|string|max:10',
            'classes.*.capacity' => 'nullable|integer|min:1|max:200',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Please check the bulk creation form for errors.');
        }

        try {
            $createdClasses = [];
            $skippedClasses = [];

            DB::beginTransaction();

            foreach ($request->classes as $classData) {
                // Check for duplicate
                $existingClass = DB::table('classes')
                    ->where('name', $classData['name'])
                    ->where('semester_id', $classData['semester_id'])
                    ->where('program_id', $classData['program_id'])
                    ->first();

                if ($existingClass) {
                    $skippedClasses[] = $classData['name'];
                    continue;
                }

                DB::table('classes')->insert([
                    'name' => $classData['name'],
                    'semester_id' => $classData['semester_id'],
                    'program_id' => $classData['program_id'],
                    'year_level' => $classData['year_level'],
                    'section' => $classData['section'],
                    'capacity' => $classData['capacity'] ?? 50,
                    'students_count' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $createdClasses[] = $classData['name'];
            }

            DB::commit();

            $message = count($createdClasses) . ' classes created successfully!';
            if (count($skippedClasses) > 0) {
                $message .= ' ' . count($skippedClasses) . ' classes were skipped (already exist): ' . implode(', ', $skippedClasses);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error bulk creating classes: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error creating classes. Please try again.');
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'semester_id' => 'required|exists:semesters,id',
            'program_id' => 'required|exists:programs,id',
            'year_level' => 'nullable|integer|min:1|max:6',
            'section' => 'required|string|max:10',
            'capacity' => 'nullable|integer|min:1|max:200',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }
        
        try {
            $class = ClassModel::find($id);
            if (!$class) {
                return redirect()->back()->with('error', 'Class not found.');
            }

            $className = $request->name;
            $section = strtoupper($request->section);

            // Check for duplicate class names with same section (excluding current class)
            $duplicateClass = ClassModel::where('name', $className)
                ->where('section', $section)
                ->where('semester_id', $request->semester_id)
                ->where('program_id', $request->program_id)
                ->where('id', '!=', $id)
                ->first();

            if ($duplicateClass) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', "Class '{$className}' Section '{$section}' already exists for this semester and program.");
            }
            
            $class->update([
                'name' => $className,
                'semester_id' => $request->semester_id,
                'program_id' => $request->program_id,
                'year_level' => $request->year_level,
                'section' => $section,
                'capacity' => $request->capacity,
            ]);
            
            return redirect()->back()->with('success', 'Class updated successfully!');
        } catch (\Exception $e) {
            Log::error('Error updating class: ' . $e->getMessage());
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error updating class. Please try again.');
        }
    }

    /**
     * Generate available class names based on program
     */
    public function getAvailableClassNames(Request $request)
    {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
        ]);

        try {
            $program = Program::find($request->program_id);
            
            if (!$program) {
                return response()->json(['error' => 'Program not found'], 404);
            }

            // Generate class names like BBIT 1.1, BBIT 1.2, BBIT 2.1, etc.
            $classNames = [];
            for ($year = 1; $year <= 4; $year++) {
                for ($subclass = 1; $subclass <= 3; $subclass++) {
                    $classNames[] = [
                        'value' => "{$program->code} {$year}.{$subclass}",
                        'label' => "{$program->code} {$year}.{$subclass}",
                        'year_level' => $year
                    ];
                }
            }

            return response()->json([
                'class_names' => $classNames,
                'program_code' => $program->code,
                'program_name' => $program->name
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating class names: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate class names'], 500);
        }
    }

    /**
     * Get available sections for a specific class name
     */
    public function getAvailableSectionsForClass(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'semester_id' => 'required|exists:semesters,id',
            'program_id' => 'required|exists:programs,id'
        ]);

        try {
            $existingSections = ClassModel::where('name', $request->name)
                ->where('semester_id', $request->semester_id)
                ->where('program_id', $request->program_id)
                ->pluck('section')
                ->toArray();

            $allSections = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
            $availableSections = array_diff($allSections, $existingSections);

            return response()->json([
                'existing_sections' => $existingSections,
                'available_sections' => array_values($availableSections),
                'all_sections' => $allSections
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching available sections: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch section data'], 500);
        }
    }
    
    public function destroy($id)
    {
        try {
            // Find class using model
            $class = ClassModel::find($id);
            
            if (!$class) {
                return redirect()->back()->with('error', 'Class not found.');
            }
            
            // Check if class has students
            if ($class->students_count > 0) {
                return redirect()->back()->with('error', "Cannot delete class with {$class->students_count} enrolled students.");
            }
            
            // Delete the class using model
            $class->delete();
            
            return redirect()->back()->with('success', 'Class deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Error deleting class: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error deleting class. Please try again.');
        }
    }

    public function generateClassCode(Request $request)
    {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'year_level' => 'required|integer|min:1|max:6',
            'section' => 'required|string|size:1',
        ]);

        try {
            $program = Program::find($request->program_id);
            
            if (!$program) {
                return response()->json(['error' => 'Program not found'], 404);
            }

            $sectionNumber = ord(strtoupper($request->section)) - 64; // A=1, B=2, etc.
            $generatedName = "{$program->code} {$request->year_level}.{$sectionNumber}";

            return response()->json([
                'generated_name' => $generatedName,
                'program_code' => $program->code,
                'program_name' => $program->name
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating class code: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate class code'], 500);
        }
    }
    
    public function getSemesters()
    {
        try {
            $semesters = Semester::select('id', 'name', 'is_active')
                ->orderBy('name')
                ->get();
            
            return response()->json($semesters);
        } catch (\Exception $e) {
            Log::error('Error fetching semesters: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch semesters'], 500);
        }
    }
    
    public function getPrograms()
    {
        try {
            $programs = Program::with('school:id,name')
                ->select('id', 'name', 'code', 'school_id')
                ->orderBy('name')
                ->get();
            
            return response()->json($programs);
        } catch (\Exception $e) {
            Log::error('Error fetching programs: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch programs'], 500);
        }
    }


/**
 * Get classes by program and semester
 */
public function getByProgramAndSemester(Request $request)
{
    $request->validate([
        'program_id' => 'required|exists:programs,id',
        'semester_id' => 'required|exists:semesters,id',
    ]);

    try {
        $classes = ClassModel::with(['program', 'semester'])
            ->where('program_id', $request->program_id)
            ->where('semester_id', $request->semester_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        return response()->json($classes);
    } catch (\Exception $e) {
        Log::error('Error fetching classes by program and semester: ' . $e->getMessage());
        return response()->json(['error' => 'Failed to fetch classes'], 500);
    }
}

    public function getProgramsBySchool($schoolId)
    {
        try {
            $programs = Program::where('school_id', $schoolId)
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get();
            
            return response()->json($programs);
        } catch (\Exception $e) {
            Log::error('Error fetching programs by school: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch programs'], 500);
        }
    }
    
    // Method to get class statistics using relationships
    public function getStats()
    {
        try {
            $stats = [
                'total_classes' => ClassModel::count(),
                'active_classes' => ClassModel::where('is_active', true)->count(),
                'total_students' => ClassModel::sum('students_count'),
                'classes_by_semester' => ClassModel::with('semester:id,name')
                    ->select('semester_id', DB::raw('count(*) as count'))
                    ->groupBy('semester_id')
                    ->get()
                    ->map(function($item) {
                        return [
                            'name' => $item->semester->name ?? 'Unknown',
                            'count' => $item->count
                        ];
                    }),
                'classes_by_program' => ClassModel::with('program:id,code,name')
                    ->select('program_id', DB::raw('count(*) as count'))
                    ->groupBy('program_id')
                    ->orderBy('count', 'desc')
                    ->get()
                    ->map(function($item) {
                        return [
                            'code' => $item->program->code ?? 'Unknown',
                            'name' => $item->program->name ?? 'Unknown',
                            'count' => $item->count
                        ];
                    }),
                'classes_by_year_level' => ClassModel::select('year_level', DB::raw('count(*) as count'))
                    ->whereNotNull('year_level')
                    ->groupBy('year_level')
                    ->orderBy('year_level')
                    ->get(),
            ];
            
            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error('Error fetching class stats: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch statistics'], 500);
        }
    }
    
    // Method for bulk operations using models
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:classes,id'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Invalid class selection for bulk delete.');
        }
        
        try {
            // Check for classes with students using model
            $classesWithStudents = ClassModel::whereIn('id', $request->ids)
                ->where('students_count', '>', 0)
                ->pluck('name');

            if ($classesWithStudents->isNotEmpty()) {
                return redirect()->back()->with('error', 
                    'Cannot delete classes with students: ' . $classesWithStudents->implode(', '));
            }

            $deleted = ClassModel::whereIn('id', $request->ids)->delete();
            
            return redirect()->back()->with('success', "Successfully deleted {$deleted} classes.");
        } catch (\Exception $e) {
            Log::error('Error bulk deleting classes: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error deleting classes. Please try again.');
        }
    }

    public function bulkUpdateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'exists:classes,id',
            'status' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->with('error', 'Invalid selection for bulk status update.');
        }

        try {
            $updated = ClassModel::whereIn('id', $request->ids)
                ->update(['is_active' => $request->status]);

            $statusText = $request->status ? 'activated' : 'deactivated';
            return redirect()->back()->with('success', "Successfully {$statusText} {$updated} classes.");

        } catch (\Exception $e) {
            Log::error('Error bulk updating class status: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error updating class status. Please try again.');
        }
    }

    // Method to get available sections using relationships
    public function getAvailableSections(Request $request)
    {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'year_level' => 'required|integer|min:1|max:6',
            'semester_id' => 'required|exists:semesters,id'
        ]);

        try {
            $existingSections = ClassModel::where('program_id', $request->program_id)
                ->where('year_level', $request->year_level)
                ->where('semester_id', $request->semester_id)
                ->whereNotNull('section')
                ->pluck('section')
                ->toArray();

            $allSections = array_map(function($i) {
                return chr(65 + $i); // A, B, C, etc.
            }, range(0, 9));

            $availableSections = array_diff($allSections, $existingSections);

            return response()->json([
                'existing_sections' => $existingSections,
                'available_sections' => array_values($availableSections),
                'all_sections' => $allSections
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching available sections: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch section data'], 500);
        }
    }

    // Method to update student count using model
    public function updateStudentCount($classId, $newCount)
    {
        try {
            $class = ClassModel::find($classId);
            if ($class) {
                $class->update(['students_count' => $newCount]);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error('Error updating student count: ' . $e->getMessage());
            return false;
        }
    }

    // Method to get classes by program (useful for enrollment management)
    public function getClassesByProgram($programId)
    {
        try {
            $classes = ClassModel::with(['semester:id,name'])
                ->where('program_id', $programId)
                ->where('is_active', true)
                ->orderBy('year_level')
                ->orderBy('section')
                ->get();
            
            return response()->json($classes);
        } catch (\Exception $e) {
            Log::error('Error fetching classes by program: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch classes'], 500);
        }
    }

    // Method to get class details with relationships
    public function show($id)
    {
        try {
            $class = ClassModel::with(['semester', 'program.school', 'students'])
                ->findOrFail($id);
            
            return response()->json($class);
        } catch (\Exception $e) {
            Log::error('Error fetching class details: ' . $e->getMessage());
            return response()->json(['error' => 'Class not found'], 404);
        }
    }

    
    public function programClasses(Program $program, Request $request, $schoolCode)
{
    // Get pagination parameter with default
    $perPage = (int) $request->input('per_page', 15);
    $search = $request->search ?? '';
    $semesterId = $request->semester_id;
    $yearLevel = $request->year_level;
    
    // Verify program belongs to the correct school
    if ($program->school->code !== $schoolCode) {
        abort(404, 'Program not found in this school.');
    }

    $user = auth()->user();

    // Build the query for program-specific classes
    $query = ClassModel::with(['semester', 'program.school'])
        ->where('program_id', $program->id)
        ->when($search, function($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%')
              ->orWhere('section', 'like', '%' . $search . '%')
              ->orWhereHas('semester', function($sq) use ($search) {
                  $sq->where('name', 'like', '%' . $search . '%');
              });
        })
        ->when($semesterId, function($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        })
        ->when($yearLevel, function($q) use ($yearLevel) {
            $q->where('year_level', $yearLevel);
        })
        ->orderBy('year_level')
        ->orderBy('name')
        ->orderBy('section');
    
    // ✅ Get paginated results with query string preservation
    $classes = $query->paginate($perPage)->withQueryString();
    
    // Get semesters for dropdowns
    $semesters = Semester::select('id', 'name', 'is_active')
        ->where('is_active', true)
        ->orderBy('name')
        ->get();

    $componentPathForProgramClasses = "Schools/{$schoolCode}/Programs/Classes/Index";

    return Inertia::render($componentPathForProgramClasses, [
        'classes' => $classes,
        'program' => $program->load('school'),
        'semesters' => $semesters,
        'schoolCode' => $schoolCode,
        'filters' => [
            'search' => $search,
            'semester_id' => $semesterId ? (int) $semesterId : null,
            'year_level' => $yearLevel ? (int) $yearLevel : null,
            'per_page' => $perPage,
        ],
        'can' => [
            'create' => $user->hasRole('Admin') || 
                       $user->can('manage-classes') || 
                       $user->can('create-classes'),
            
            'update' => $user->hasRole('Admin') || 
                       $user->can('manage-classes') || 
                       $user->can('edit-classes'),
            
            'delete' => $user->hasRole('Admin') || 
                       $user->can('manage-classes') || 
                       $user->can('delete-classes'),
        ],
        'flash' => [
            'success' => session('success'),
            'error' => session('error'),
        ],
    ]);
}

    /**
     * Store a new class for a specific program
     */
    public function storeProgramClass(Program $program, Request $request, $schoolCode)
    {
        // Verify program belongs to the correct school
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'semester_id' => 'required|exists:semesters,id',
            'year_level' => 'nullable|integer|min:1|max:6',
            'section' => 'required|string|max:10',
            'capacity' => 'nullable|integer|min:1|max:200',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }

        try {
            $className = $request->name;
            $section = strtoupper($request->section);

            // Check for duplicate class names with same section in this program
            $existingClass = ClassModel::where('name', $className)
                ->where('section', $section)
                ->where('semester_id', $request->semester_id)
                ->where('program_id', $program->id)
                ->first();

            if ($existingClass) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', "Class '{$className}' Section '{$section}' already exists for this semester and program.");
            }

            ClassModel::create([
                'name' => $className,
                'semester_id' => $request->semester_id,
                'program_id' => $program->id,
                'year_level' => $request->year_level,
                'section' => $section,
                'capacity' => $request->capacity,
                'students_count' => 0,
                'is_active' => true,
            ]);
            
            return redirect()->back()->with('success', 'Class created successfully!');
        } catch (\Exception $e) {
            Log::error('Error creating program class: ' . $e->getMessage());
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error creating class. Please try again.');
        }
    }

    /**
     * Show create form for program class
     */
    public function createProgramClass(Program $program, $schoolCode)
    {
        // Verify program belongs to the correct school
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        $semesters = Semester::select('id', 'name', 'is_active')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Schools/Programs/Classes/Create', [
            'program' => $program->load('school'),
            'semesters' => $semesters,
            'schoolCode' => $schoolCode,
        ]);
    }

    /**
     * Show specific program class
     */
    public function showProgramClass(Program $program, ClassModel $class, $schoolCode)
    {
        // Verify program belongs to the correct school
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        // Verify class belongs to this program
        if ($class->program_id !== $program->id) {
            abort(404, 'Class not found in this program.');
        }

        $class->load(['semester', 'program.school']);

        return Inertia::render('Schools/Programs/Classes/Show', [
            'class' => $class,
            'program' => $program->load('school'),
            'schoolCode' => $schoolCode,
        ]);
    }

    /**
     * Show edit form for program class
     */
    public function editProgramClass(Program $program, ClassModel $class, $schoolCode)
    {
        // Verify program belongs to the correct school
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        // Verify class belongs to this program
        if ($class->program_id !== $program->id) {
            abort(404, 'Class not found in this program.');
        }

        $semesters = Semester::select('id', 'name', 'is_active')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Schools/Programs/Classes/Edit', [
            'class' => $class->load(['semester', 'program.school']),
            'program' => $program->load('school'),
            'semesters' => $semesters,
            'schoolCode' => $schoolCode,
        ]);
    }

    /**
     * Update program class
     */
    public function updateProgramClass(Program $program, ClassModel $class, Request $request, $schoolCode)
    {
        // Verify program belongs to the correct school
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        // Verify class belongs to this program
        if ($class->program_id !== $program->id) {
            abort(404, 'Class not found in this program.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'semester_id' => 'required|exists:semesters,id',
            'year_level' => 'nullable|integer|min:1|max:6',
            'section' => 'required|string|max:10',
            'capacity' => 'nullable|integer|min:1|max:200',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }
        
        try {
            $className = $request->name;
            $section = strtoupper($request->section);

            // Check for duplicate class names with same section (excluding current class)
            $duplicateClass = ClassModel::where('name', $className)
                ->where('section', $section)
                ->where('semester_id', $request->semester_id)
                ->where('program_id', $program->id)
                ->where('id', '!=', $class->id)
                ->first();

            if ($duplicateClass) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', "Class '{$className}' Section '{$section}' already exists for this semester and program.");
            }
            
            $class->update([
                'name' => $className,
                'semester_id' => $request->semester_id,
                'year_level' => $request->year_level,
                'section' => $section,
                'capacity' => $request->capacity,
            ]);
            
            return redirect()->back()->with('success', 'Class updated successfully!');
        } catch (\Exception $e) {
            Log::error('Error updating program class: ' . $e->getMessage());
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error updating class. Please try again.');
        }
    }

    /**
     * Delete program class
     */
    public function destroyProgramClass(Program $program, ClassModel $class, $schoolCode)
    {
        // Verify program belongs to the correct school
        if ($program->school->code !== $schoolCode) {
            abort(404, 'Program not found in this school.');
        }

        // Verify class belongs to this program
        if ($class->program_id !== $program->id) {
            abort(404, 'Class not found in this program.');
        }

        try {
            // Check if class has students
            if ($class->students_count > 0) {
                return redirect()->back()->with('error', "Cannot delete class with {$class->students_count} enrolled students.");
            }
            
            $class->delete();
            
            return redirect()->back()->with('success', 'Class deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Error deleting program class: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error deleting class. Please try again.');
        }
    }
}

   