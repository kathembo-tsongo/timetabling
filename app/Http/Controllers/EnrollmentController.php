<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Unit;
use App\Models\School;
use App\Models\Program;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class EnrollmentController extends Controller
{
    public function index()
{
    $enrollments = Enrollment::with(['student', 'unit.school', 'unit.program', 'semester'])->get();
    
    // Fix: Order by first_name instead of name, or use raw SQL to concat names
    $students = User::with(['school', 'program'])
        ->role('Student')
        ->orderByRaw("CONCAT(first_name, ' ', last_name)")
        ->get();
    
    $units = Unit::with(['school', 'program', 'semester'])->where('is_active', true)->get();
    $schools = School::orderBy('name')->get();
    $programs = Program::orderBy('name')->get();
    $semesters = Semester::orderBy('name')->get();
    
    // Calculate stats
    $stats = [
        'total' => $enrollments->count(),
        'active' => $enrollments->where('status', 'enrolled')->count(),
        'dropped' => $enrollments->where('status', 'dropped')->count(),
        'completed' => $enrollments->where('status', 'completed')->count(),
    ];
    
    return Inertia::render('Admin/Enrollments/Index', [
        'enrollments' => $enrollments,
        'students' => $students,
        'units' => $units,
        'schools' => $schools,
        'programs' => $programs,
        'semesters' => $semesters,
        'stats' => $stats,
        'can' => [
            'create' => auth()->user()->can('create-enrollments'),
            'update' => auth()->user()->can('edit-enrollments'),
            'delete' => auth()->user()->can('delete-enrollments'),
        ]
    ]);
}
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:users,id',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'status' => 'required|in:enrolled,dropped,completed'
        ]);

        try {
            // Verify the user has Student role using Spatie
            $student = User::findOrFail($validated['student_id']);
            if (!$student->hasRole('Student')) {
                return back()->withErrors(['error' => 'Selected user is not a student.']);
            }

            // Check for existing enrollment
            $existingEnrollment = Enrollment::where([
                'student_id' => $validated['student_id'],
                'unit_id' => $validated['unit_id'],
                'semester_id' => $validated['semester_id']
            ])->first();

            if ($existingEnrollment) {
                return back()->withErrors(['error' => 'Student is already enrolled in this unit for the selected semester.']);
            }

            Enrollment::create([
                'student_id' => $validated['student_id'],
                'unit_id' => $validated['unit_id'],
                'semester_id' => $validated['semester_id'],
                'status' => $validated['status'],
                'enrollment_date' => now()
            ]);

            return back()->with('success', 'Enrollment created successfully!');
        } catch (\Exception $e) {
            Log::error('Error creating enrollment', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to create enrollment']);
        }
    }

    public function update(Request $request, Enrollment $enrollment)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:users,id',
            'unit_id' => 'required|exists:units,id',
            'semester_id' => 'required|exists:semesters,id',
            'status' => 'required|in:enrolled,dropped,completed'
        ]);

        try {
            $enrollment->update($validated);
            return back()->with('success', 'Enrollment updated successfully!');
        } catch (\Exception $e) {
            Log::error('Error updating enrollment', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to update enrollment']);
        }
    }

    public function destroy(Enrollment $enrollment)
    {
        try {
            $enrollment->delete();
            return back()->with('success', 'Enrollment deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Error deleting enrollment', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to delete enrollment']);
        }
    }
}