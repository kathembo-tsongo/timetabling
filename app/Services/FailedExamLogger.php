<?php

namespace App\Services;

use App\Models\FailedExamSchedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FailedExamLogger
{
    /**
     * Log a failed exam schedule attempt
     */
    public static function logFailure(array $examData, array $conflicts, ?array $attemptedDates = null): void
    {
        try {
            $failureReasons = self::formatConflicts($conflicts);

            $failedExam = FailedExamSchedule::create([
                'program_id' => $examData['program_id'],
                'school_id' => $examData['school_id'],
                'class_name' => $examData['class_name'],
                'section' => $examData['section'] ?? null,
                'unit_code' => $examData['unit_code'],
                'unit_name' => $examData['unit_name'],
                'student_count' => $examData['student_count'],
                'lecturer_name' => $examData['lecturer_name'] ?? null,
                'failure_reasons' => $failureReasons,
                'attempted_dates' => $attemptedDates,
                'status' => 'pending',
                'created_by' => Auth::id(),
            ]);

            Log::info('Failed exam schedule logged', [
                'failed_exam_id' => $failedExam->id,
                'unit_code' => $examData['unit_code'],
                'conflict_count' => count($conflicts),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log exam scheduling failure', [
                'unit_code' => $examData['unit_code'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Format conflict information for storage
     */
    private static function formatConflicts(array $conflicts): array
    {
        $formatted = [];

        foreach ($conflicts as $conflict) {
            $formatted[] = [
                'type' => $conflict['type'] ?? 'unknown',
                'message' => $conflict['message'] ?? 'No details provided',
                'date' => $conflict['date'] ?? null,
                'time' => $conflict['time'] ?? null,
                'venue' => $conflict['venue'] ?? null,
                'lecturer' => $conflict['lecturer'] ?? null,
                'details' => $conflict['details'] ?? null,
            ];
        }

        return $formatted;
    }

    /**
     * Check if a similar failure already exists for this unit (to avoid duplicates)
     */
    public static function hasSimilarFailure(string $unitCode, int $programId): bool
    {
        return FailedExamSchedule::where('unit_code', $unitCode)
            ->where('program_id', $programId)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * Get conflict types from failure reasons
     */
    public static function getConflictTypes(array $failureReasons): array
    {
        return array_unique(array_column($failureReasons, 'type'));
    }
}