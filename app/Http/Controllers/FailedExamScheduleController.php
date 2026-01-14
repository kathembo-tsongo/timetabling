<?php

namespace App\Http\Controllers;

use App\Models\FailedExamSchedule;
use App\Models\Program;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class FailedExamScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Build query with filters
        $query = FailedExamSchedule::with(['program', 'school', 'creator', 'resolver'])
            ->orderBy('created_at', 'desc');

        // Apply role-based filtering
        if ($user->hasRole('School Admin')) {
            $schoolIds = $user->schools->pluck('id');
            $query->whereIn('school_id', $schoolIds);
        } elseif ($user->hasRole('Program Admin')) {
            $programIds = $user->programs->pluck('id');
            $query->whereIn('program_id', $programIds);
        }

        // Apply status filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Apply school filter
        if ($request->filled('school_id')) {
            $query->where('school_id', $request->school_id);
        }

        // Apply program filter
        if ($request->filled('program_id')) {
            $query->where('program_id', $request->program_id);
        }

        // Apply search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('unit_code', 'like', "%{$search}%")
                    ->orWhere('unit_name', 'like', "%{$search}%")
                    ->orWhere('class_name', 'like', "%{$search}%");
            });
        }

        $failedExams = $query->paginate(20)->withQueryString();

        // Get statistics
        $stats = $this->getStatistics($user);

        // Get filter options
        $schools = School::select('id', 'name')->orderBy('name')->get()->toArray();
        $programs = Program::select('id', 'name', 'school_id')->orderBy('name')->get()->toArray();

        return Inertia::render('ExamOffice/failedScheduledExams', [
            'failedExams' => $failedExams,
            'statistics' => $stats,
            'schools' => $schools,
            'programs' => $programs,
            'filters' => [
                'status' => $request->input('status'),
                'school_id' => $request->input('school_id'),
                'program_id' => $request->input('program_id'),
                'search' => $request->input('search'),
            ],
            // Exam Office can view and delete only
            'can' => [
                'view' => true,
                'delete' => true,
                'resolve' => false,
                'ignore' => false,
                'revert' => false,
            ]
        ]);
    }

    public function show(FailedExamSchedule $failedExam)
    {
        $failedExam->load(['program', 'school', 'creator', 'resolver']);

        return Inertia::render('ExamOffice/FailedScheduleDetail', [
            'failedExam' => $failedExam,
        ]);
    }

    public function destroy(FailedExamSchedule $failedExam)
    {
        $unitCode = $failedExam->unit_code;
        $failedExam->delete();

        Log::info('Failed exam schedule deleted', [
            'unit_code' => $unitCode,
            'deleted_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Failed exam record deleted successfully.');
    }

    private function getStatistics($user)
    {
        $query = FailedExamSchedule::query();

        // Apply role-based filtering
        if ($user->hasRole('School Admin')) {
            $schoolIds = $user->schools->pluck('id');
            $query->whereIn('school_id', $schoolIds);
        } elseif ($user->hasRole('Program Admin')) {
            $programIds = $user->programs->pluck('id');
            $query->whereIn('program_id', $programIds);
        }

        return [
            'total' => (int) $query->count(),
            'pending' => (int) (clone $query)->where('status', 'pending')->count(),
            'resolved' => (int) (clone $query)->where('status', 'resolved')->count(),
            'ignored' => (int) (clone $query)->where('status', 'ignored')->count(),
        ];
    }
}