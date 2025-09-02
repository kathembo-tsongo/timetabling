<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ClassroomController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of classrooms.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Check permissions
        if (!$user->hasRole('Admin') && !$user->can('manage-classrooms')) {
            abort(403, 'Unauthorized access to classrooms.');
        }

        try {
            $query = Classroom::query();

            // Apply filters
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('building') && $request->filled('building')) {
                $query->where('building', 'like', '%' . $request->input('building') . '%');
            }

            if ($request->has('type') && $request->filled('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->has('search') && $request->filled('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('building', 'like', "%{$search}%")
                      ->orWhere('floor', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortField = $request->input('sort_field', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // Get classrooms with usage statistics
            $classrooms = $query->get()->map(function ($classroom) {
                return [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'code' => $classroom->code,
                    'building' => $classroom->building,
                    'floor' => $classroom->floor,
                    'capacity' => $classroom->capacity,
                    'type' => $classroom->type,
                    'facilities' => $classroom->facilities ? json_decode($classroom->facilities, true) : [],
                    'is_active' => $classroom->is_active,
                    'location' => $classroom->location,
                    'description' => $classroom->description,
                    'created_at' => $classroom->created_at,
                    'updated_at' => $classroom->updated_at,
                    'usage_stats' => $this->getClassroomUsageStats($classroom->id),
                ];
            });

            // Get summary statistics
            $stats = [
                'total' => Classroom::count(),
                'active' => Classroom::where('is_active', true)->count(),
                'inactive' => Classroom::where('is_active', false)->count(),
                'by_type' => Classroom::selectRaw('type, count(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray(),
                'by_building' => Classroom::selectRaw('building, count(*) as count')
                    ->groupBy('building')
                    ->pluck('count', 'building')
                    ->toArray(),
            ];

            // Get unique values for filters
            $buildings = Classroom::distinct()->pluck('building')->filter()->sort()->values();
            $types = Classroom::distinct()->pluck('type')->filter()->sort()->values();

            return Inertia::render('Admin/Classrooms/Index', [
                'classrooms' => $classrooms,
                'stats' => $stats,
                'buildings' => $buildings,
                'types' => $types,
                'filters' => $request->only([
                    'search', 'is_active', 'building', 'type', 'sort_field', 'sort_direction'
                ]),
                'can' => [
                    'create' => $user->hasRole('Admin') || $user->can('manage-classrooms'),
                    'update' => $user->hasRole('Admin') || $user->can('manage-classrooms'),
                    'delete' => $user->hasRole('Admin') || $user->can('manage-classrooms'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching classrooms', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Inertia::render('Admin/Classrooms/Index', [
                'classrooms' => [],
                'stats' => ['total' => 0, 'active' => 0, 'inactive' => 0, 'by_type' => [], 'by_building' => []],
                'buildings' => [],
                'types' => [],
                'error' => 'Unable to load classrooms. Please try again.',
                'filters' => $request->only([
                    'search', 'is_active', 'building', 'type', 'sort_field', 'sort_direction'
                ]),
            ]);
        }
    }

    /**
     * Store a newly created classroom.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-classrooms')) {
            abort(403, 'Unauthorized to create classrooms.');
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:classrooms,code',
                'building' => 'required|string|max:100',
                'floor' => 'nullable|string|max:50',
                'capacity' => 'required|integer|min:1|max:1000',
                'type' => 'required|string|in:lecture_hall,laboratory,seminar_room,computer_lab,auditorium,meeting_room,other',
                'facilities' => 'array',
                'facilities.*' => 'string',
                'location' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:500',
                'is_active' => 'boolean',
            ]);

            $validated['facilities'] = json_encode($validated['facilities'] ?? []);

            $classroom = Classroom::create($validated);

            Log::info('Classroom created', [
                'classroom_id' => $classroom->id,
                'name' => $classroom->name,
                'created_by' => $user->id
            ]);

            return back()->with('success', 'Classroom created successfully.');

        } catch (\Exception $e) {
            Log::error('Error creating classroom', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return back()
                ->withErrors(['error' => 'Failed to create classroom. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Display the specified classroom.
     */
    public function show(Classroom $classroom)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('view-classrooms') && !$user->can('manage-classrooms')) {
            abort(403, 'Unauthorized to view classroom details.');
        }

        try {
            $usageStats = $this->getClassroomUsageStats($classroom->id);
            
            return Inertia::render('Admin/Classrooms/Show', [
                'classroom' => [
                    'id' => $classroom->id,
                    'name' => $classroom->name,
                    'code' => $classroom->code,
                    'building' => $classroom->building,
                    'floor' => $classroom->floor,
                    'capacity' => $classroom->capacity,
                    'type' => $classroom->type,
                    'facilities' => $classroom->facilities ? json_decode($classroom->facilities, true) : [],
                    'is_active' => $classroom->is_active,
                    'location' => $classroom->location,
                    'description' => $classroom->description,
                    'created_at' => $classroom->created_at,
                    'updated_at' => $classroom->updated_at,
                ],
                'usage_stats' => $usageStats,
                'can' => [
                    'update' => $user->hasRole('Admin') || $user->can('manage-classrooms'),
                    'delete' => $user->hasRole('Admin') || $user->can('manage-classrooms'),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error showing classroom', [
                'classroom_id' => $classroom->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return redirect()->route('admin.classrooms.index')
                ->withErrors(['error' => 'Unable to load classroom details.']);
        }
    }

    /**
     * Update the specified classroom.
     */
    public function update(Request $request, Classroom $classroom)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-classrooms')) {
            abort(403, 'Unauthorized to update classrooms.');
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('classrooms', 'code')->ignore($classroom->id),
                ],
                'building' => 'required|string|max:100',
                'floor' => 'nullable|string|max:50',
                'capacity' => 'required|integer|min:1|max:1000',
                'type' => 'required|string|in:lecture_hall,laboratory,seminar_room,computer_lab,auditorium,meeting_room,other',
                'facilities' => 'array',
                'facilities.*' => 'string',
                'location' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:500',
                'is_active' => 'boolean',
            ]);

            $validated['facilities'] = json_encode($validated['facilities'] ?? []);

            $classroom->update($validated);

            Log::info('Classroom updated', [
                'classroom_id' => $classroom->id,
                'name' => $classroom->name,
                'updated_by' => $user->id,
                'changes' => $classroom->getChanges()
            ]);

            return back()->with('success', 'Classroom updated successfully.');

        } catch (\Exception $e) {
            Log::error('Error updating classroom', [
                'classroom_id' => $classroom->id,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return back()
                ->withErrors(['error' => 'Failed to update classroom. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Remove the specified classroom from storage.
     */
    public function destroy(Classroom $classroom)
    {
        $user = auth()->user();
        
        if (!$user->hasRole('Admin') && !$user->can('manage-classrooms')) {
            abort(403, 'Unauthorized to delete classrooms.');
        }

        try {
            // Check if classroom has any scheduled classes or timetables
            $hasScheduledClasses = $this->hasScheduledClasses($classroom->id);
            
            if ($hasScheduledClasses) {
                return back()->withErrors([
                    'error' => 'Cannot delete classroom because it has scheduled classes or timetables.'
                ]);
            }

            $classroomName = $classroom->name;
            $classroom->delete();

            Log::info('Classroom deleted', [
                'classroom_name' => $classroomName,
                'deleted_by' => $user->id
            ]);

            return back()->with('success', "Classroom '{$classroomName}' deleted successfully.");

        } catch (\Exception $e) {
            Log::error('Error deleting classroom', [
                'classroom_id' => $classroom->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return back()->withErrors(['error' => 'Failed to delete classroom. Please try again.']);
        }
    }

    // Private helper methods

    /**
     * Get classroom usage statistics.
     */
    private function getClassroomUsageStats($classroomId)
    {
        try {
            // This would depend on your timetable/booking system
            // For now, returning mock data structure
            return [
                'total_bookings' => 0,
                'weekly_hours' => 0,
                'utilization_rate' => 0,
                'recent_bookings' => [],
            ];
        } catch (\Exception $e) {
            Log::warning('Error getting classroom usage stats', [
                'classroom_id' => $classroomId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'total_bookings' => 0,
                'weekly_hours' => 0,
                'utilization_rate' => 0,
                'recent_bookings' => [],
            ];
        }
    }

    /**
     * Check if classroom has scheduled classes.
     */
    private function hasScheduledClasses($classroomId)
    {
        try {
            // Check class_timetables table if it exists
            if (\Schema::hasTable('class_timetables')) {
                return \DB::table('class_timetables')
                    ->where('classroom_id', $classroomId)
                    ->exists();
            }
            
            return false;
        } catch (\Exception $e) {
            Log::warning('Error checking scheduled classes', [
                'classroom_id' => $classroomId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}