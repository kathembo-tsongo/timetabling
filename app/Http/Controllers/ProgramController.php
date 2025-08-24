<?php

namespace App\Http\Controllers;

use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;



class ProgramController extends Controller
{
    public function index(Request $request, string $schoolCode)
{
    // Find school by code
    $school = \App\Models\School::where('code', $schoolCode)->firstOrFail();

    $programs = Program::where('school_id', $school->id)->get();

    return inertia('Programs/Index', [
        'programs' => $programs,
        'schoolId' => $school->id,
        'schoolCode' => $schoolCode,
        'school' => $school,
    ]);
}


    public function create(int $schoolId)
    {
        return inertia('Programs/Create', [
            'schoolId' => $schoolId,
        ]);
    }



public function store(Request $request, string $schoolCode)
{
    try {
        // Find school by its code
        $school = School::where('code', $schoolCode)->firstOrFail();

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                Rule::unique('programs')->where(fn ($query) =>
                    $query->where('school_id', $school->id)
                ),
            ],
            'name' => 'required|string|max:255',
            'degree_type' => 'required|string|max:255',
            'duration_years' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['school_id'] = $school->id;

        $program = Program::create($validated);

        Log::info("Program created successfully", [
            'school_code' => $schoolCode,
            'school_id'   => $school->id,
            'program_id'  => $program->id,
            'user_id'     => $request->user()->id,
        ]);

        return redirect()->route("schools.programs.index", $schoolCode)
                         ->with('success', 'Program created successfully.');
    } catch (\Throwable $e) {
        Log::error("Error creating program", [
            'data'       => $request->all(),
            'schoolCode' => $schoolCode,
            'error'      => $e->getMessage(),
            'user_id'    => $request->user()->id,
        ]);
        return back()->withErrors(['error' => $e->getMessage()]);
    }
}

    public function update(Request $request, int $schoolId, Program $program)
    {
        try {
            $validated = $request->validate([
                'code' => [
                    'required',
                    'string',
                    Rule::unique('programs')
                        ->where(fn ($query) =>
                            $query->where('school_id', $schoolId)
                        )
                        ->ignore($program->id),
                ],
                'name' => 'required|string|max:255',
                'degree_type' => 'required|string|max:255',
                'duration_years' => 'required|integer|min:1',
                'description' => 'nullable|string',
                'contact_email' => 'nullable|email',
                'contact_phone' => 'nullable|string',
                'is_active' => 'boolean',
                'sort_order' => 'nullable|integer',
            ]);

            $program->update($validated);

            Log::info("Program updated successfully", [
                'school_id' => $schoolId,
                'program_id' => $program->id,
                'user_id' => $request->user()->id,
            ]);

            return redirect()->route("schools.programs.index", $schoolId)
                             ->with('success', 'Program updated successfully.');
        } catch (\Throwable $e) {
            Log::error("Error updating program", [
                'data' => $request->all(),
                'school_id' => $schoolId,
                'program_id' => $program->id,
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(Request $request, int $schoolId, Program $program)
    {
        $program->delete();

        return redirect()->route("schools.programs.index", $schoolId)
                         ->with('success', 'Program deleted successfully.');
    }
}
