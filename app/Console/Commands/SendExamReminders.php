<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExamTimetable;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Exam_reminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SendExamReminders extends Command
{
    protected $signature = 'exams:send-reminders';
    protected $description = 'Send exam reminders to students with exams scheduled for tomorrow';

    public function handle()
    {
        $tomorrow = Carbon::tomorrow()->toDateString();
        $this->info("Checking for exams scheduled on: $tomorrow");

        // Get all exams scheduled for tomorrow
        $exams = ExamTimetable::where('date', $tomorrow)
            ->with(['unit', 'semester'])
            ->get();

        $this->info("Found {$exams->count()} exams scheduled for tomorrow.");
        $notificationsSent = 0;

        foreach ($exams as $exam) {
            $this->info("Processing exam: {$exam->unit->code} - {$exam->unit->name}");

            // Find all student codes enrolled in this unit for this semester
            $studentCodes = Enrollment::where('unit_id', $exam->unit_id)
                ->where('semester_id', $exam->semester_id)
                ->pluck('student_code')
                ->toArray();

            $this->info("Found " . count($studentCodes) . " student codes enrolled in this exam.");

            if (empty($studentCodes)) {
                $this->warn("No students enrolled in this exam. Skipping.");
                continue;
            }

            // Get all users with these codes
            $students = User::whereIn('code', $studentCodes)->get();
            
            $this->info("Found {$students->count()} student records from the database.");

            foreach ($students as $student) {
                try {
                    // Prepare notification data with more detailed exam information
                   $data = [
    'subject' => 'A humble Reminder',
    'greeting' => 'Hello ' . ($student->first_name ?? $student->name ?? 'Student'),
    'message' => 'We wish to remind you that you will be having an exam tomorrow. Please keep checking your timetable for further details.',
    'exam_details' => [
        'unit' => $exam->unit->code . ' - ' . $exam->unit->name,
        'date' => $exam->date,
        'day' => $exam->day,
        'time' => $exam->start_time . ' - ' . $exam->end_time,
        'venue' => $exam->venue . ' (' . ($exam->location ?? 'Main Campus') . ')',
    ],
    'closing' => 'Good luck with your exam preparation!'
];

                    // Send notification
                    $student->notify(new Exam_reminder($data));
                    $notificationsSent++;
                    
                    $this->info("Sent notification to {$student->first_name} {$student->last_name} ({$student->email})");
                    // Add this after sending the notification
DB::table('notification_logs')->insert([
    'notification_type' => 'App\\Notifications\\Exam_reminder',
    'notifiable_type' => 'App\\Models\\User',
    'notifiable_id' => $student->id,
    'channel' => 'mail',
    'success' => true,
    'error_message' => null,
    'created_at' => now(),
    'updated_at' => now(),
]);
                } catch (\Exception $e) {
                    $this->error("Failed to send notification to student {$student->code}: " . $e->getMessage());
                    Log::error("Failed to send exam notification", [
                        'student_code' => $student->code,
                        'student_email' => $student->email,
                        'exam_id' => $exam->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $this->info("Process completed. Sent $notificationsSent notifications.");
        return 0;
    }
}