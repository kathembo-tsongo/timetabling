<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClassroomController extends Controller
{
    
    public function index()
    {
        $classrooms = Classroom::paginate(10); // Use pagination
        return Inertia::render('Classrooms/index', [
            'classrooms' => $classrooms,
            'perPage' => 10,
            'search' => request('search', ''),
        ]);
    }

    public function create()
    {
        return Inertia::render('Classrooms/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'capacity' => 'required|integer',
            'location' => 'required',
        ]);

        Classroom::create($request->all());

        return redirect()->route('classrooms.index')
                         ->with('success', 'Classroom created successfully.');
    }

    public function show(Classroom $classroom)
    {
        return Inertia::render('Classrooms/Show', ['classroom' => $classroom]);
    }

    public function edit(Classroom $classroom)
    {
        return Inertia::render('Classrooms/Edit', ['classroom' => $classroom]);
    }

    public function update(Request $request, Classroom $classroom)
    {
        $request->validate([
            'name' => 'required',
            'capacity' => 'required|integer',
            'location' => 'required',
        ]);

        $classroom->update($request->all());

        return redirect()->route('classrooms.index')
                         ->with('success', 'Classroom updated successfully.');
    }

    public function destroy(Classroom $classroom)
    {
        try {
            $classroom->delete();
            
            // Return JSON response for AJAX requests
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Classroom deleted successfully!'
                ]);
            }
            
            return redirect()->back()->with('success', 'Classroom deleted successfully!');
            
        } catch (\Exception $e) {
            // Return JSON error response for AJAX requests
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete classroom. Please try again.'
                ], 500);
            }
            
            return redirect()->back()->with('error', 'Failed to delete classroom. Please try again.');
        }
    }
}
