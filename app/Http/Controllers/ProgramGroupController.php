<?php

namespace App\Http\Controllers;

use App\Models\ProgramGroup;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProgramGroupController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(ProgramGroup::class, 'programGroup');
    }

    /**
     * Display a listing of the program groups.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $query = ProgramGroup::query()->with('program.school');

        // Filter by program
        if ($request->has('program_id') && $request->input('program_id')) {
            $query->where('program_id', $request->input('program_id'));
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Sorting
        $sortField = $request->input('sort_field', 'group');
        $sortDirection = $request->input('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->input('per_page', 10);
        $programGroups = $query->paginate($perPage)->withQueryString();

        // Get all programs for the filter dropdown
        $programs = Program::select('id', 'code', 'name')->orderBy('name')->get();

        return Inertia::render('ProgramGroups/Index', [
            'programGroups' => $programGroups,
            'programs' => $programs,
            'filters' => $request->only(['program_id', 'is_active', 'sort_field', 'sort_direction', 'per_page']),
            'can' => [
                'create' => Gate::allows('create', ProgramGroup::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new program group.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        $programs = Program::select('id', 'code', 'name')->orderBy('name')->get();

        return Inertia::render('ProgramGroups/Create', [
            'programs' => $programs,
        ]);
    }

    /**
     * Store a newly created program group in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'group' => [
                'required',
                'string',
                'size:1',
                'regex:/^[A-Z]$/',
                function ($attribute, $value, $fail) use ($request) {
                    // Check if the group already exists for this program
                    $exists = ProgramGroup::where('program_id', $request->input('program_id'))
                        ->where('group', $value)
                        ->exists();
                    
                    if ($exists) {
                        $fail('This group already exists for the selected program.');
                    }
                },
            ],
            'capacity' => 'required|integer|min:1|max:200',
            'is_active' => 'boolean',
        ]);

        // Set default values
        $validated['current_count'] = 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        ProgramGroup::create($validated);

        return redirect()->route('program-groups.index')
            ->with('success', 'Program group created successfully.');
    }

    /**
     * Display the specified program group.
     *
     * @param  \App\Models\ProgramGroup  $programGroup
     * @return \Inertia\Response
     */
    public function show(ProgramGroup $programGroup)
    {
        $programGroup->load('program.school');

        // Get students in this group
        $students = $programGroup->enrollments()
            ->select('student_code')
            ->distinct()
            ->with('student:id,code,name,email')
            ->paginate(20);

        return Inertia::render('ProgramGroups/Show', [
            'programGroup' => $programGroup,
            'students' => $students,
            'can' => [
                'update' => Gate::allows('update', $programGroup),
                'delete' => Gate::allows('delete', $programGroup),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified program group.
     *
     * @param  \App\Models\ProgramGroup  $programGroup
     * @return \Inertia\Response
     */
    public function edit(ProgramGroup $programGroup)
    {
        $programGroup->load('program');
        $programs = Program::select('id', 'code', 'name')->orderBy('name')->get();

        return Inertia::render('ProgramGroups/Edit', [
            'programGroup' => $programGroup,
            'programs' => $programs,
        ]);
    }

    /**
     * Update the specified program group in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ProgramGroup  $programGroup
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, ProgramGroup $programGroup)
    {
        $validated = $request->validate([
            'capacity' => 'required|integer|min:' . $programGroup->current_count . '|max:200',
            'is_active' => 'boolean',
        ]);

        $programGroup->update($validated);

        return redirect()->route('program-groups.index')
            ->with('success', 'Program group updated successfully.');
    }

    /**
     * Remove the specified program group from storage.
     *
     * @param  \App\Models\ProgramGroup  $programGroup
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(ProgramGroup $programGroup)
    {
        // Check if the group has any enrollments
        if ($programGroup->enrollments()->exists()) {
            return redirect()->route('program-groups.index')
                ->with('error', 'Cannot delete program group because it has associated enrollments.');
        }

        $programGroup->delete();

        return redirect()->route('program-groups.index')
            ->with('success', 'Program group deleted successfully.');
    }

    /**
     * Reset the count of students in the program group.
     *
     * @param  \App\Models\ProgramGroup  $programGroup
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resetCount(ProgramGroup $programGroup)
    {
        $this->authorize('update', $programGroup);

        // Count actual enrollments
        $actualCount = $programGroup->enrollments()
            ->select('student_code')
            ->distinct()
            ->count();

        $programGroup->update(['current_count' => $actualCount]);

        return redirect()->route('program-groups.show', $programGroup)
            ->with('success', 'Student count has been reset to ' . $actualCount);
    }
}