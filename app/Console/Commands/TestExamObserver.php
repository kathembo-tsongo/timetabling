<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExamTimetable;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TestExamObserver extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exams:test-observer {exam_id? : The ID of the exam to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the ExamTimetableObserver by updating an exam timetable';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // If no exam ID is provided, list available exams
        if (!$this->argument('exam_id')) {
            $this->listAvailableExams();
            return 0;
        }
        
        $examId = $this->argument('exam_id');
        $this->info("Testing observer with exam ID: {$examId}");
        
        // Find the exam timetable
        $exam = ExamTimetable::with(['unit', 'semester'])->find($examId);
        
        if (!$exam) {
            $this->error("Exam timetable with ID {$examId} not found.");
            return 1;
        }
        
        $this->info("Exam: {$exam->unit->code} - {$exam->unit->name}");
        $this->info("Semester: {$exam->semester->name}");
        $this->info("Date: {$exam->date} ({$exam->day})");
        $this->info("Time: {$exam->start_time} - {$exam->end_time}");
        
        // Check if there are students enrolled in this exam
        $enrollments = Enrollment::where('unit_id', $exam->unit_id)
            ->where('semester_id', $exam->semester_id)
            ->get();
            
        $this->info("Students enrolled: {$enrollments->count()}");
        
        // Check if there's a lecturer assigned
        $lecturerCode = null;
        foreach ($enrollments as $enrollment) {
            if (!empty($enrollment->lecturer_code)) {
                $lecturerCode = $enrollment->lecturer_code;
                break;
            }
        }
        
        if ($lecturerCode) {
            $lecturer = User::where('code', $lecturerCode)->first();
            if ($lecturer) {
                $this->info("Lecturer assigned: {$lecturer->name} ({$lecturer->email})");
            } else {
                $this->warn("Lecturer code found ({$lecturerCode}), but no matching user found.");
            }
        } else {
            $this->warn("No lecturer is assigned to this exam.");
        }
        
        if ($enrollments->count() === 0 && !$lecturerCode) {
            $this->warn("No students or lecturer found for this exam. No notifications will be sent.");
            if (!$this->confirm("Do you want to continue anyway?")) {
                return 0;
            }
        }
        
        // Get current venue
        $currentVenue = $exam->venue;
        $this->info("Current venue: {$currentVenue}");
        
        // Update with a modified venue
        $newVenue = $currentVenue . " (Updated)";
        
        // Update the exam timetable
        $exam->venue = $newVenue;
        $this->info("Updated venue to: {$newVenue}");
        
        // Save the changes
        $exam->save();
        
        $this->info("Observer should have been triggered. Check logs and notification_logs table.");
        
        // Check notification logs
        $this->info("Checking notification logs...");
        $logs = \DB::table('notification_logs')
            ->where('notification_type', 'App\\Notifications\\ExamTimetableUpdate')
            ->where('created_at', '>=', now()->subMinutes(1))
            ->get();
            
        if ($logs->count() > 0) {
            $this->info("Found {$logs->count()} recent notification logs:");
            foreach ($logs as $log) {
                $user = User::find($log->notifiable_id);
                $data = json_decode($log->data, true);
                $userType = isset($data['is_lecturer']) && $data['is_lecturer'] ? 'Lecturer' : 'Student';
                $this->info("- {$userType}: {$user->email}: " . ($log->success ? 'Success' : 'Failed - ' . $log->error_message));
            }
        } else {
            $this->warn("No recent notification logs found. Check the debug log for more information.");
        }
        
        return 0;
    }
    
    /**
     * List available exams for testing.
     */
    private function listAvailableExams()
    {
        $this->info("Available exam timetables:");
        
        $exams = ExamTimetable::with(['unit', 'semester'])
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();
            
        $headers = ['ID', 'Unit Code', 'Unit Name', 'Date', 'Day', 'Time', 'Venue'];
        $rows = [];
        
        foreach ($exams as $exam) {
            $rows[] = [
                $exam->id,
                $exam->unit->code ?? 'N/A',
                $exam->unit->name ?? 'N/A',
                $exam->date,
                $exam->day,
                $exam->start_time . ' - ' . $exam->end_time,
                $exam->venue
            ];
        }
        
        $this->table($headers, $rows);
        
        $this->info("To test a specific exam, run:");
        $this->line("php artisan exams:test-observer [exam_id]");
    }
}
