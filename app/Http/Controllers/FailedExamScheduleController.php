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
                    ->orWhere('class_names', 'like', "%{$search}%");
            });
        }

        $failedExams = $query->paginate(20)->withQueryString();

        // ✅ ENHANCED: Add formatted data for each record
        $failedExams->getCollection()->transform(function ($exam) {
            // Format attempted time
            $attemptedTime = 'N/A';
            if ($exam->attempted_date) {
                $attemptedTime = \Carbon\Carbon::parse($exam->attempted_date)->format('M d, Y');
                
                if ($exam->attempted_start_time && $exam->attempted_end_time) {
                    $attemptedTime .= ' | ' . \Carbon\Carbon::parse($exam->attempted_start_time)->format('g:i A') 
                                    . ' - ' . \Carbon\Carbon::parse($exam->attempted_end_time)->format('g:i A');
                }
            }

            return [
                'id' => $exam->id,
                'unit_code' => $exam->unit_code,
                'unit_name' => $exam->unit_name,
                'class_names' => $exam->class_names, // e.g., "BBIT 4.2 (Section: A)"
                'student_count' => $exam->student_count,
                'program' => $exam->program ? [
                    'id' => $exam->program->id,
                    'name' => $exam->program->name,
                    'code' => $exam->program->code,
                ] : null,
                'school' => $exam->school ? [
                    'id' => $exam->school->id,
                    'name' => $exam->school->name,
                    'code' => $exam->school->code,
                ] : null,
                'attempted_date' => $exam->attempted_date 
                    ? \Carbon\Carbon::parse($exam->attempted_date)->format('M d, Y') 
                    : null,
                'attempted_time' => $attemptedTime,
                'attempted_slot' => $exam->assigned_slot_number 
                    ? "Slot #{$exam->assigned_slot_number}" 
                    : null,
                'failure_reason' => $exam->failure_reason,
                'conflict_details' => $exam->conflict_details,
                'status' => $exam->status,
                'batch_id' => $exam->batch_id,
                'created_at' => $exam->created_at->format('M d, Y g:i A'),
                'resolved_at' => $exam->resolved_at 
                    ? $exam->resolved_at->format('M d, Y g:i A') 
                    : null,
                'resolved_by' => $exam->resolver ? [
                    'id' => $exam->resolver->id,
                    'name' => trim($exam->resolver->first_name . ' ' . $exam->resolver->last_name),
                ] : null,
                'resolution_notes' => $exam->resolution_notes,
            ];
        });

        // Get statistics
        $stats = $this->getStatistics($user);

        // Get filter options
        $schools = School::select('id', 'name', 'code')->orderBy('name')->get();
        $programs = Program::select('id', 'name', 'code', 'school_id')->orderBy('name')->get();

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
            'can' => [
                'view' => true,
                'delete' => $user->can('delete-exam-timetables'),
                'resolve' => $user->can('edit-exam-timetables'),
                'ignore' => $user->can('edit-exam-timetables'),
                'revert' => $user->can('edit-exam-timetables'),
            ]
        ]);
    }

    public function show(FailedExamSchedule $failedExam)
    {
        $failedExam->load(['program', 'school', 'creator', 'resolver']);

        // Format the data for detailed view
        $formattedExam = [
            'id' => $failedExam->id,
            'batch_id' => $failedExam->batch_id,
            'unit_code' => $failedExam->unit_code,
            'unit_name' => $failedExam->unit_name,
            'class_names' => $failedExam->class_names,
            'class_ids' => $failedExam->class_ids,
            'student_count' => $failedExam->student_count,
            'program' => $failedExam->program,
            'school' => $failedExam->school,
            'attempted_date' => $failedExam->attempted_date 
                ? \Carbon\Carbon::parse($failedExam->attempted_date)->format('F d, Y') 
                : null,
            'attempted_start_time' => $failedExam->attempted_start_time 
                ? \Carbon\Carbon::parse($failedExam->attempted_start_time)->format('g:i A') 
                : null,
            'attempted_end_time' => $failedExam->attempted_end_time 
                ? \Carbon\Carbon::parse($failedExam->attempted_end_time)->format('g:i A') 
                : null,
            'assigned_slot_number' => $failedExam->assigned_slot_number,
            'failure_reason' => $failedExam->failure_reason,
            'conflict_details' => $failedExam->conflict_details,
            'status' => $failedExam->status,
            'created_at' => $failedExam->created_at->format('F d, Y g:i A'),
            'resolved_at' => $failedExam->resolved_at 
                ? $failedExam->resolved_at->format('F d, Y g:i A') 
                : null,
            'resolver' => $failedExam->resolver,
            'resolution_notes' => $failedExam->resolution_notes,
        ];

        return Inertia::render('ExamOffice/FailedScheduleDetail', [
            'failedExam' => $formattedExam,
        ]);
    }

    public function destroy(FailedExamSchedule $failedExam)
    {
        $unitCode = $failedExam->unit_code;
        $className = $failedExam->class_names;
        
        $failedExam->delete();

        Log::info('Failed exam schedule deleted', [
            'unit_code' => $unitCode,
            'class' => $className,
            'deleted_by' => Auth::id(),
        ]);

        return redirect()->route('examoffice.failed-scheduled-exams')
            ->with('success', "Failed exam record for {$unitCode} - {$className} deleted successfully.");
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