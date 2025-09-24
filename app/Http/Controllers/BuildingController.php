<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Building;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BuildingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(['permission:view-buildings'])->only(['index', 'show']);
        $this->middleware(['permission:create-buildings'])->only(['store']);
        $this->middleware(['permission:edit-buildings'])->only(['update', 'toggleStatus']);
        $this->middleware(['permission:delete-buildings'])->only(['destroy']);
    }

    /**
     * Display a listing of buildings
     */
    public function index(Request $request): Response
    {
        try {
            Log::info('Accessing buildings index', [
                'user_id' => Auth::id(),
                'filters' => $request->all()
            ]);

            $query = Building::query()->withCount('classroom');

            // Apply search filter
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('classroom', 'like', "%{$search}%")
                      ->orWhere('address', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Apply status filter
            if ($request->filled('status')) {
                $status = $request->input('status');
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $query->where('is_active', false);
                }
            }

            // Pagination
            $perPage = $request->input('per_page', 10);
            $buildings = $query->orderBy('name')
                              ->paginate($perPage)
                              ->withQueryString();

            return Inertia::render('Admin/Buildings/Index', [
                'buildings' => $buildings,
                'filters' => $request->only(['search', 'status', 'per_page']),
                'can' => [
                    'create_buildings' => Auth::user()->can('create-buildings'),
                    'update_buildings' => Auth::user()->can('edit-buildings'),
                    'delete_buildings' => Auth::user()->can('delete-buildings'),
                    'view_buildings' => Auth::user()->can('view-buildings'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error in buildings index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return an Inertia response with error instead of redirect
            return Inertia::render('Admin/Buildings/Index', [
                'buildings' => [
                    'data' => [],
                    'links' => [],
                    'total' => 0,
                    'per_page' => 10,
                    'current_page' => 1
                ],
                'filters' => $request->only(['search', 'status', 'per_page']),
                'can' => [
                    'create_buildings' => Auth::user()->can('create-buildings'),
                    'update_buildings' => Auth::user()->can('edit-buildings'),
                    'delete_buildings' => Auth::user()->can('delete-buildings'),
                    'view_buildings' => Auth::user()->can('view-buildings'),
                ],
                'errors' => [
                    'error' => 'Error loading buildings: ' . $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * Store a newly created building
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:10|unique:building,code',
                'description' => 'nullable|string|max:1000',
                'classroom' => 'nullable|integer|min:0',
                'address' => 'nullable|string|max:500',
                'is_active' => 'boolean'
            ], [
                'name.required' => 'Building name is required.',
                'code.required' => 'Building code is required.',
                'code.unique' => 'This building code is already in use.',
                'code.max' => 'Building code cannot exceed 10 characters.',
                'classroom.min' => 'Classroom count must be at least 0.',
                'classroom.integer' => 'Classroom count must be a valid number.',
                'description.max' => 'Description cannot exceed 1000 characters.',
                'address.max' => 'Address cannot exceed 500 characters.',
            ]);

            $validated['is_active'] = $validated['is_active'] ?? false;

            $building = Building::create($validated);

            Log::info('Building created', [
                'building_id' => $building->id,
                'created_by' => Auth::id(),
                'data' => $validated
            ]);

            return redirect()->route('admin.buildings.index')
                           ->with('success', 'Building created successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                           ->withErrors($e->errors())
                           ->withInput();
        } catch (\Exception $e) {
            Log::error('Error creating building', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'user_id' => Auth::id()
            ]);

            return redirect()->back()
                           ->with('error', 'Failed to create building: ' . $e->getMessage())
                           ->withInput();
        }
    }

    /**
     * Display the specified building
     */
    public function show(Building $building): Response
    {
        try {
            $building->load(['classroom' => function($query) {
                $query->orderBy('name');
            }]);

            return Inertia::render('Admin/Buildings/Show', [
                'building' => $building,
                'can' => [
                    'update_buildings' => Auth::user()->can('edit-buildings'),
                    'delete_buildings' => Auth::user()->can('delete-buildings'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error viewing building', [
                'building_id' => $building->id,
                'error' => $e->getMessage()
            ]);

            // Return an Inertia response with error instead of redirect
            return Inertia::render('Admin/Buildings/Show', [
                'building' => null,
                'can' => [
                    'update_buildings' => Auth::user()->can('edit-buildings'),
                    'delete_buildings' => Auth::user()->can('delete-buildings'),
                ],
                'errors' => [
                    'error' => 'Error loading building details.'
                ]
            ]);
        }
    }

    /**
     * Update the specified building
     */
    public function update(Request $request, Building $building)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => [
                    'required',
                    'string',
                    'max:10',
                    Rule::unique('building')->ignore($building->id)
                ],
                'description' => 'nullable|string|max:1000',
                'classroom' => 'required|integer|min:0',
                'address' => 'nullable|string|max:500',
                'is_active' => 'boolean'
            ], [
                'name.required' => 'Building name is required.',
                'code.required' => 'Building code is required.',
                'code.unique' => 'This building code is already in use.',
                'code.max' => 'Building code cannot exceed 10 characters.',
                'classroom.min' => 'Classroom count must be at least 0.',
                'description.max' => 'Description cannot exceed 1000 characters.',
                'address.max' => 'Address cannot exceed 500 characters.',
            ]);

            $validated['is_active'] = $validated['is_active'] ?? false;

            $building->update($validated);

            Log::info('Building updated', [
                'building_id' => $building->id,
                'updated_by' => Auth::id(),
                'changes' => $building->getChanges()
            ]);

            return redirect()->route('admin.buildings.index')
                           ->with('success', 'Building updated successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                           ->withErrors($e->errors())
                           ->withInput();
        } catch (\Exception $e) {
            Log::error('Error updating building', [
                'building_id' => $building->id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return redirect()->back()
                           ->with('error', 'Failed to update building: ' . $e->getMessage())
                           ->withInput();
        }
    }

    /**
     * Remove the specified building
     */
    public function destroy(Building $building)
    {
        try {
            // Check if building has classrooms
            $classroomCount = $building->classroom()->count();
            
            if ($classroomCount > 0) {
                return redirect()->back()
                               ->with('error', "Cannot delete building '{$building->name}' because it has {$classroomCount} classroom(s) associated with it.");
            }

            $buildingName = $building->name;
            $building->delete();

            Log::info('Building deleted', [
                'building_id' => $building->id,
                'building_name' => $buildingName,
                'deleted_by' => Auth::id()
            ]);

            return redirect()->route('admin.buildings.index')
                           ->with('success', "Building '{$buildingName}' deleted successfully.");

        } catch (\Exception $e) {
            Log::error('Error deleting building', [
                'building_id' => $building->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                           ->with('error', 'Failed to delete building: ' . $e->getMessage());
        }
    }

    // Add these methods to your BuildingController

/**
 * Restore a soft-deleted building
 */
public function restore($id)
{
    try {
        $building = Building::withTrashed()->findOrFail($id);
        
        if (!$building->trashed()) {
            return redirect()->back()
                           ->with('error', 'Building is not deleted and cannot be restored.');
        }
        
        $building->restore();
        
        Log::info('Building restored', [
            'building_id' => $building->id,
            'building_name' => $building->name,
            'restored_by' => Auth::id()
        ]);
        
        return redirect()->back()
                       ->with('success', "Building '{$building->name}' restored successfully.");
                       
    } catch (\Exception $e) {
        Log::error('Error restoring building', [
            'building_id' => $id,
            'error' => $e->getMessage()
        ]);
        
        return redirect()->back()
                       ->with('error', 'Failed to restore building: ' . $e->getMessage());
    }
}

/**
 * Permanently delete a building (force delete)
 */
public function forceDelete($id)
{
    try {
        $building = Building::withTrashed()->findOrFail($id);
        
        if (!$building->trashed()) {
            return redirect()->back()
                           ->with('error', 'Building must be deleted first before permanent deletion.');
        }
        
        // Check if building has classrooms even in trash
        $classroomCount = $building->classroom()->count();
        if ($classroomCount > 0) {
            return redirect()->back()
                           ->with('error', "Cannot permanently delete building '{$building->name}' because it still has {$classroomCount} classroom(s) associated with it.");
        }
        
        $buildingName = $building->name;
        $building->forceDelete();
        
        Log::info('Building permanently deleted', [
            'building_id' => $id,
            'building_name' => $buildingName,
            'deleted_by' => Auth::id()
        ]);
        
        return redirect()->back()
                       ->with('success', "Building '{$buildingName}' permanently deleted.");
                       
    } catch (\Exception $e) {
        Log::error('Error permanently deleting building', [
            'building_id' => $id,
            'error' => $e->getMessage()
        ]);
        
        return redirect()->back()
                       ->with('error', 'Failed to permanently delete building: ' . $e->getMessage());
    }
}

/**
 * Get deleted buildings for the trash section
 */
public function getTrashedBuildings(Request $request)
{
    try {
        $query = Building::onlyTrashed()->withCount('classroom');
        
        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
        
        $perPage = $request->input('per_page', 10);
        $trashedBuildings = $query->orderBy('deleted_at', 'desc')
                                 ->paginate($perPage)
                                 ->withQueryString();
        
        return response()->json($trashedBuildings);
        
    } catch (\Exception $e) {
        Log::error('Error loading trashed buildings', [
            'error' => $e->getMessage()
        ]);
        
        return response()->json(['error' => 'Failed to load deleted buildings'], 500);
    }
}

    /**
     * Toggle building status
     */
    public function toggleStatus(Building $building)
    {
        try {
            $building->update(['is_active' => !$building->is_active]);
            
            $status = $building->is_active ? 'activated' : 'deactivated';
            
            Log::info("Building {$status}", [
                'building_id' => $building->id,
                'new_status' => $building->is_active,
                'updated_by' => Auth::id()
            ]);

            return redirect()->back()
                           ->with('success', "Building '{$building->name}' {$status} successfully.");

        } catch (\Exception $e) {
            Log::error('Error toggling building status', [
                'building_id' => $building->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                           ->with('error', 'Failed to update building status.');
        }
    }

    /**
     * Get buildings for API calls (for dropdowns, etc.)
     */
    public function getBuildings(Request $request)
    {
        try {
            $query = Building::query();

            // Only active buildings for general use
            if (!$request->boolean('include_inactive')) {
                $query->where('is_active', true);
            }

            // Search functionality
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('code', 'like', '%' . $search . '%');
                });
            }

            $buildings = $query->select('id', 'name', 'code', 'is_active')
                              ->orderBy('name')
                              ->get()
                              ->map(function($building) {
                                  return [
                                      'id' => $building->id,
                                      'name' => $building->name,
                                      'code' => $building->code,
                                      'display_name' => "{$building->name} ({$building->code})",
                                      'is_active' => $building->is_active
                                  ];
                              });

            return response()->json($buildings);

        } catch (\Exception $e) {
            Log::error('Error getting buildings for API', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Failed to load buildings'], 500);
        }
    }
}