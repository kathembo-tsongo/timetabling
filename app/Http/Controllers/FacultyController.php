<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class FacultyController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        Log::info('Accessing Faculties', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);

        $faculties = Faculty::all();
        return Inertia::render('Faculties/index', ['faculties' => $faculties]);
    }

    public function create()
    {
        return Inertia::render('Faculties/Create');
    }

    public function edit(Faculty $faculty)
    {
        return Inertia::render('Faculties/Edit', ['faculty' => $faculty]);
    }


    public function store(Request $request)
    {
        $request->validate([
        'name' => 'required|string|max:255|unique:faculties,name',
        ]);

        Faculty::create($request->only('name'));
        return redirect()->route('faculties.index');
    }

    public function update(Request $request, Faculty $faculty)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:faculties,name,' . $faculty->id,
        ]);

        $faculty->update($request->only('name'));
        return redirect()->route('faculties.index');
    }

    public function destroy(Faculty $faculty)
    {
        $faculty->delete();
        return redirect()->route('faculties.index');
    }
}
