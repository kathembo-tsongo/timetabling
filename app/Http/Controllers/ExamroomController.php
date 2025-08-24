<?php

namespace App\Http\Controllers;

use App\Models\Examroom;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExamroomController extends Controller
{
    
    public function index()
    {
        try {
            $examrooms = Examroom::paginate(10); // Use pagination
            return Inertia::render('Examrooms/index', [
                'examrooms' => $examrooms,
                'perPage' => 10,
                'search' => request('search', ''),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error fetching examrooms: ' . $e->getMessage());
            abort(500, 'An error occurred while fetching examrooms.');
        }
    }

    public function create()
    {
        return Inertia::render('Examrooms/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'capacity' => 'required|integer',
            'location' => 'required',
        ]);

        Examroom::create($request->all());

        return redirect()->route('examrooms.index')
                         ->with('success', 'Examroom created successfully.');
    }

    public function show(Examroom $examroom)
    {
        return Inertia::render('Examrooms/Show', ['examroom' => $examroom]);
    }

    public function edit(Examroom $examroom)
    {
        return Inertia::render('Examrooms/Edit', ['examroom' => $examroom]);
    }

    public function update(Request $request, Examroom $examroom)
    {
        $request->validate([
            'name' => 'required',
            'capacity' => 'required|integer',
            'location' => 'required',
        ]);

        $examroom->update($request->all());

        return redirect()->route('examrooms.index')
                         ->with('success', 'Examroom updated successfully.');
    }

    public function destroy(Examroom $examroom)
    {
        $examroom->delete();

        return redirect()->route('examrooms.index')
                         ->with('success', 'Examroom deleted successfully.');
    }
}
