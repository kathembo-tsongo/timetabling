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
        if ($request->filled('status')) {
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
        $schools = School::select('id', 'name')->orderBy('name')->get();
        $programs = Program::select('id', 'name', 'school_id')->orderBy('name')->get();

        return Inertia::render('ExamTimetables/FailedSchedules', [
            'failedExams' => $failedExams,
            'statistics' => $stats,
            'schools' => $schools,
            'programs' => $programs,
            'filters' => $request->only(['status', 'school_id', 'program_id', 'search']),
        ]);
    }

    public function show(FailedExamSchedule $failedExam)
    {
        $this->authorize('view', $failedExam);

        $failedExam->load(['program', 'school', 'creator', 'resolver']);

        return Inertia::render('ExamTimetables/FailedScheduleDetail', [
            'failedExam' => $failedExam,
        ]);
    }

    public function resolve(Request $request, FailedExamSchedule $failedExam)
    {
        $this->authorize('update', $failedExam);

        $validated = $request->validate([
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        $failedExam->markAsResolved(
            Auth::user(),
            $validated['resolution_notes'] ?? null
        );

        Log::info('Failed exam schedule resolved', [
            'failed_exam_id' => $failedExam->id,
            'unit_code' => $failedExam->unit_code,
            'resolved_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Exam marked as resolved successfully.');
    }

    public function ignore(Request $request, FailedExamSchedule $failedExam)
    {
        $this->authorize('update', $failedExam);

        $validated = $request->validate([
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        $failedExam->markAsIgnored(
            Auth::user(),
            $validated['resolution_notes'] ?? null
        );

        Log::info('Failed exam schedule ignored', [
            'failed_exam_id' => $failedExam->id,
            'unit_code' => $failedExam->unit_code,
            'ignored_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Exam marked as ignored.');
    }

    public function revert(FailedExamSchedule $failedExam)
    {
        $this->authorize('update', $failedExam);

        $failedExam->update([
            'status' => 'pending',
            'resolved_by' => null,
            'resolved_at' => null,
            'resolution_notes' => null,
        ]);

        Log::info('Failed exam schedule reverted to pending', [
            'failed_exam_id' => $failedExam->id,
            'unit_code' => $failedExam->unit_code,
            'reverted_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Exam status reverted to pending.');
    }

    public function bulkResolve(Request $request)
    {
        $validated = $request->validate([
            'failed_exam_ids' => 'required|array',
            'failed_exam_ids.*' => 'exists:failed_exam_schedules,id',
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        $resolvedCount = 0;

        foreach ($validated['failed_exam_ids'] as $id) {
            $failedExam = FailedExamSchedule::find($id);
            
            if ($failedExam && $user->can('update', $failedExam)) {
                $failedExam->markAsResolved($user, $validated['resolution_notes'] ?? null);
                $resolvedCount++;
            }
        }

        Log::info('Bulk resolved failed exam schedules', [
            'count' => $resolvedCount,
            'resolved_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', "Successfully resolved {$resolvedCount} exams.");
    }

    public function destroy(FailedExamSchedule $failedExam)
    {
        $this->authorize('delete', $failedExam);

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
            'total' => $query->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'resolved' => (clone $query)->where('status', 'resolved')->count(),
            'ignored' => (clone $query)->where('status', 'ignored')->count(),
        ];
    }
}