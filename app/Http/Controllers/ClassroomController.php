<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Building;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ClassroomController extends Controller
{
    public function index(Request $request)
    {
        // Get filter parameters
        $search = $request->input('search', '');
        $isActive = $request->input('is_active');
        $buildingFilter = $request->input('building', '');
        $type = $request->input('type', '');
        $sortField = $request->input('sort_field', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');

        // Build query with filters
        $query = Classroom::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%');
                  
                // Only search building if the column exists
                if (Schema::hasColumn('classrooms', 'building')) {
                    $q->orWhere('building', 'like', '%' . $search . '%');
                }
            });
        }

        if ($isActive !== null) {
            $query->where('is_active', $isActive == '1');
        }

        if ($buildingFilter) {
            // Only filter by building if the column exists
            if (Schema::hasColumn('classrooms', 'building')) {
                $query->where('building', $buildingFilter);
            }
        }

        if ($type) {
            $query->where('type', $type);
        }

        // Apply sorting
        $query->orderBy($sortField, $sortDirection);

        // Get classrooms with building relationships
        $classrooms = $query->with('building')->get()->map(function ($classroom) {
            // Add usage stats using the model attribute
            $classroom->usage_stats = $classroom->usage_stats;
            
            // Ensure facilities is always an array
            $classroom->facilities = is_string($classroom->facilities) 
                ? json_decode($classroom->facilities, true) ?? []
                : ($classroom->facilities ?? []);

            // Add building name for display - try relationship first, then fallback to string field
            if ($classroom->building && is_object($classroom->building)) {
                $classroom->building_name = $classroom->building->name;
            } else {
                $classroom->building_name = $classroom->building ?? 'Unknown Building';
            }

            return $classroom;
        });

        // Get statistics
        $stats = [
            'total' => Classroom::count(),
            'active' => Classroom::where('is_active', true)->count(),
            'inactive' => Classroom::where('is_active', false)->count(),
            'by_type' => Classroom::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type')
                ->toArray(),
            'by_building' => $this->getBuildingStats()
        ];

        // Get buildings for dropdown - now from the building table
        $buildings = $this->getBuildingsForDropdown();
            
        $types = ['lecture_hall', 'laboratory', 'seminar_room', 'computer_lab', 'auditorium', 'meeting_room', 'other'];

        // User permissions - use actual permission system
        $user = Auth::user();
        $can = [
            'create' => $user->can('create-classrooms'),
            'update' => $user->can('edit-classrooms'),
            'delete' => $user->can('delete-classrooms')
        ];

        return Inertia::render('Admin/Classrooms/Index', [
            'classrooms' => $classrooms,
            'stats' => $stats,
            'buildings' => $buildings,
            'types' => $types,
            'filters' => [
                'search' => $search,
                'is_active' => $isActive !== null ? ($isActive == '1') : null,
                'building' => $buildingFilter ?: null,
                'type' => $type ?: null,
                'sort_field' => $sortField,
                'sort_direction' => $sortDirection
            ],
            'can' => $can,
            'error' => session('error')
        ]);
    }

    private function getBuildingStats()
    {
        try {
            if (Schema::hasColumn('classrooms', 'building')) {
                return Classroom::selectRaw('building, COUNT(*) as count')
                    ->whereNotNull('building')
                    ->where('building', '!=', '')
                    ->groupBy('building')
                    ->pluck('count', 'building')
                    ->toArray();
            }
        } catch (\Exception $e) {
            \Log::warning('Error getting building stats: ' . $e->getMessage());
        }
        
        return [];
    }

    private function getBuildingsForDropdown()
    {
        try {
            // Check if building table exists (singular 'building')
            if (Schema::hasTable('building')) {
                $buildings = Building::where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'code']);

                \Log::info('Buildings found: ' . $buildings->count());
                
                return $buildings->map(function($building) {
                    return [
                        'id' => $building->id,
                        'name' => $building->name,
                        'code' => $building->code
                    ];
                })->toArray();
            }
            
            // Also check for 'buildings' table (plural)
            if (Schema::hasTable('buildings')) {
                $buildings = Building::where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'code']);

                \Log::info('Buildings found in buildings table: ' . $buildings->count());
                
                return $buildings->map(function($building) {
                    return [
                        'id' => $building->id,
                        'name' => $building->name,
                        'code' => $building->code
                    ];
                })->toArray();
            }
            
            // Fallback: get from classrooms table if building table doesn't exist
            if (Schema::hasColumn('classrooms', 'building')) {
                $buildings = Classroom::distinct()
                    ->whereNotNull('building')
                    ->where('building', '!=', '')
                    ->pluck('building')
                    ->sort()
                    ->values();

                \Log::info('Buildings from classrooms: ' . $buildings->count());
                    
                return $buildings->map(function($name) {
                    return [
                        'id' => $name, 
                        'name' => $name, 
                        'code' => ''
                    ];
                })->toArray();
            }
        } catch (\Exception $e) {
            \Log::error('Error getting buildings for dropdown: ' . $e->getMessage());
        }
        
        \Log::warning('No buildings found - returning empty array');
        return [];
    }

    public function create()
    {
        try {
            // Debug: Check if Building model is accessible
            \Log::info('Attempting to load buildings...');
            
            // Try raw DB query first
            $buildingsRaw = \DB::table('building')->get();
            \Log::info('Raw DB query result: ' . $buildingsRaw->count() . ' buildings found');
            
            // Try to get buildings from database
            $buildings = \DB::table('building')
                ->where('is_active', 1)
                ->orderBy('name')
                ->select('id', 'name', 'code')
                ->get()
                ->map(function($building) {
                    return [
                        'id' => $building->id,
                        'name' => $building->name,
                        'code' => $building->code
                    ];
                })
                ->toArray();

            \Log::info('Processed buildings: ' . json_encode($buildings));

            // If no buildings found, use fallback
            if (empty($buildings)) {
                \Log::warning('No buildings found in database, using fallback');
                $buildings = [
                    ['id' => 1, 'name' => 'Main Building', 'code' => 'MAIN'],
                    ['id' => 2, 'name' => 'Science Building', 'code' => 'SCI'],
                    ['id' => 3, 'name' => 'Arts Building', 'code' => 'ARTS'],
                    ['id' => 4, 'name' => 'Engineering Building', 'code' => 'ENG'],
                    ['id' => 5, 'name' => 'Library Building', 'code' => 'LIB']
                ];
            }

        } catch (\Exception $e) {
            \Log::error('Error loading buildings: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Fallback buildings
            $buildings = [
                ['id' => 1, 'name' => 'Main Building', 'code' => 'MAIN'],
                ['id' => 2, 'name' => 'Science Building', 'code' => 'SCI'],
                ['id' => 3, 'name' => 'Arts Building', 'code' => 'ARTS'],
                ['id' => 4, 'name' => 'Engineering Building', 'code' => 'ENG'],
                ['id' => 5, 'name' => 'Library Building', 'code' => 'LIB']
            ];
        }

        $types = ['lecture_hall', 'laboratory', 'seminar_room', 'computer_lab', 'auditorium', 'meeting_room', 'other'];

        \Log::info('Final buildings array for create form: ' . json_encode($buildings));

        return Inertia::render('Admin/Classrooms/Create', [
            'buildings' => $buildings,
            'types' => $types
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:classrooms,code',
            'floor' => 'nullable|string|max:10',
            'capacity' => 'required|integer|min:1',
            'type' => 'required|string',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ];

        // Add building validation - should match building names from the building table
        if (Schema::hasColumn('classrooms', 'building')) {
            $rules['building'] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        // Ensure facilities is properly formatted
        $validated['facilities'] = json_encode($validated['facilities'] ?? []);
        $validated['is_active'] = $validated['is_active'] ?? true;

        try {
            Classroom::create($validated);

            return redirect()->route('admin.classrooms.index')
                ->with('success', 'Classroom created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create classroom. Please try again.']);
        }
    }

    public function show(Classroom $classroom)
    {
        // Add usage stats using the model attribute
        $classroom->usage_stats = $classroom->usage_stats;

        // Ensure facilities is an array
        $classroom->facilities = is_string($classroom->facilities) 
            ? json_decode($classroom->facilities, true) ?? []
            : ($classroom->facilities ?? []);

        return Inertia::render('Admin/Classrooms/Show', [
            'classroom' => $classroom
        ]);
    }

    public function edit(Classroom $classroom)
    {
        $buildings = $this->getBuildingsForDropdown();
        $types = ['lecture_hall', 'laboratory', 'seminar_room', 'computer_lab', 'auditorium', 'meeting_room', 'other'];

        // Ensure facilities is an array
        $classroom->facilities = is_string($classroom->facilities) 
            ? json_decode($classroom->facilities, true) ?? []
            : ($classroom->facilities ?? []);

        return Inertia::render('Admin/Classrooms/Edit', [
            'classroom' => $classroom,
            'buildings' => $buildings,
            'types' => $types
        ]);
    }

    public function update(Request $request, Classroom $classroom)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:classrooms,code,' . $classroom->id,
            'floor' => 'nullable|string|max:10',
            'capacity' => 'required|integer|min:1',
            'type' => 'required|string',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ];

        // Add building validation
        if (Schema::hasColumn('classrooms', 'building')) {
            $rules['building'] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        // Ensure facilities is properly formatted
        $validated['facilities'] = json_encode($validated['facilities'] ?? []);
        $validated['is_active'] = $validated['is_active'] ?? true;

        try {
            $classroom->update($validated);

            return redirect()->route('admin.classrooms.index')
                ->with('success', 'Classroom updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update classroom. Please try again.']);
        }
    }

    public function destroy(Request $request, Classroom $classroom)
    {
        try {
            $classroom->delete();

            // Return JSON response for AJAX requests
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Classroom deleted successfully!'
                ]);
            }

            return redirect()->back()->with('success', 'Classroom deleted successfully!');
        } catch (\Exception $e) {
            // Return JSON error response for AJAX requests
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete classroom. Please try again.'
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to delete classroom. Please try again.');
        }
    }
}