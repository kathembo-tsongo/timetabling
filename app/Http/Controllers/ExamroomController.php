<?php

namespace App\Http\Controllers;

use App\Models\Examroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ExamroomController extends Controller
{
    /**
     * Display a listing of exam rooms with pagination and search
     */
    public function index(Request $request)
    {
        try {
            // ✅ Get pagination and search parameters
            $perPage = (int) $request->input('per_page', 15);
            $search = $request->input('search', '');

            // ✅ Build query with search
            $query = Examroom::query()
                ->when($search, function($q) use ($search) {
                    $q->where(function($subQ) use ($search) {
                        $subQ->where('name', 'like', "%{$search}%")
                             ->orWhere('location', 'like', "%{$search}%")
                             ->orWhere('capacity', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name');

            // ✅ CRITICAL: Use paginate() with withQueryString()
            $examrooms = $query->paginate($perPage)->withQueryString();

            return Inertia::render('ExamOffice/Rooms', [
                'examrooms' => $examrooms, // ← This now includes pagination data
                'filters' => [
                    'search' => $search,
                    'per_page' => $perPage,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching examrooms: ' . $e->getMessage());
            
            return Inertia::render('ExamOffice/Rooms', [
                'examrooms' => [
                    'data' => [],
                    'links' => [],
                    'total' => 0,
                    'per_page' => 15,
                    'current_page' => 1,
                    'from' => 0,
                    'to' => 0,
                    'last_page' => 1,
                ],
                'filters' => [
                    'search' => '',
                    'per_page' => 15,
                ],
                'error' => 'An error occurred while fetching exam rooms.',
            ]);
        }
    }

    /**
     * Show the form for creating a new exam room
     */
    public function create()
    {
        return Inertia::render('ExamOffice/Rooms/Create');
    }

    /**
     * Store a newly created exam room
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1|max:1000',
            'location' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }

        try {
            Examroom::create([
                'name' => $request->name,
                'capacity' => $request->capacity,
                'location' => $request->location,
            ]);

            return redirect()->route('examrooms.index')
                ->with('success', 'Exam room created successfully.');

        } catch (\Exception $e) {
            Log::error('Error creating exam room: ' . $e->getMessage());
            
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error creating exam room. Please try again.');
        }
    }

    /**
     * Display the specified exam room
     */
    public function show(Examroom $examroom)
    {
        return Inertia::render('ExamOffice/Rooms/Show', [
            'examroom' => $examroom
        ]);
    }

    /**
     * Show the form for editing the specified exam room
     */
    public function edit(Examroom $examroom)
    {
        return Inertia::render('ExamOffice/Rooms/Edit', [
            'examroom' => $examroom
        ]);
    }

    /**
     * Update the specified exam room
     */
    public function update(Request $request, Examroom $examroom)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1|max:1000',
            'location' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Please check the form for errors.');
        }

        try {
            $examroom->update([
                'name' => $request->name,
                'capacity' => $request->capacity,
                'location' => $request->location,
            ]);

            return redirect()->route('examrooms.index')
                ->with('success', 'Exam room updated successfully.');

        } catch (\Exception $e) {
            Log::error('Error updating exam room: ' . $e->getMessage());
            
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error updating exam room. Please try again.');
        }
    }

    /**
     * Remove the specified exam room
     */
    public function destroy(Examroom $examroom)
    {
        try {
            //Check if room is being used (you can add this check later)
            $isUsed = ExamSchedule::where('examroom_id', $examroom->id)->exists();
            if ($isUsed) {
                return redirect()->back()
                    ->with('error', 'Cannot delete exam room that is currently in use.');
            }

            $examroom->delete();

            return redirect()->route('examrooms.index')
                ->with('success', 'Exam room deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Error deleting exam room: ' . $e->getMessage());
            
            return redirect()->back()
                ->with('error', 'Error deleting exam room. Please try again.');
        }
    }
}