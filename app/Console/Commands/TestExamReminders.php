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

class TestExamReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exams:test-reminders {exam_id? : The ID of the exam to test} {--email= : Test email address} {--user_id= : Specific user ID to test with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test sending exam reminders for a specific exam';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting exam reminder test...');
        
        // Get the exam ID from the argument or use the Sunday exam (ID 1)
        $examId = $this->argument('exam_id') ?? 1;
        $testEmail = $this->option('email');
        $userId = $this->option('user_id');
        
        // Find the exam
        $exam = ExamTimetable::with(['unit', 'semester'])->find($examId);
        
        if (!$exam) {
            $this->error("Exam with ID {$examId} not found.");
            return 1;
        }
        
        $this->info("Testing notifications for exam: {$exam->unit->code} - {$exam->unit->name}");
        $this->info("Scheduled for: {$exam->date} at {$exam->start_time} - {$exam->end_time}");
        
        // Initialize students collection
        $students = collect();
        
        // If a specific user ID is provided, use that user
        if ($userId) {
            $specificUser = User::find($userId);
            
            if ($specificUser) {
                $this->info("Using specific user: {$specificUser->name} ({$specificUser->email})");
                $students = collect([$specificUser]);
            } else {
                $this->error("User with ID {$userId} not found.");
                return 1;
            }
        }
        // If test email is provided, find that user
        else if ($testEmail) {
            $this->info("Using test email: {$testEmail}");
            
            // Try to find the user with this email
            $testUser = User::where('email', $testEmail)->first();
            
            if (!$testUser) {
                $this->error("User with email {$testEmail} not found in the database.");
                $this->info("Please use an existing user's email or create the user in the database first.");
                return 1;
            }
            
            $this->info("Found user: {$testUser->name} ({$testUser->email})");
            $this->info("User details: First Name: {$testUser->first_name}, Last Name: {$testUser->last_name}, Code: {$testUser->code}");
            
            $students = collect([$testUser]);
        }
        // Otherwise, find enrolled students
        else {
            // Find enrolled students
            $studentCodes = Enrollment::where('unit_id', $exam->unit_id)
                ->where('semester_id', $exam->semester_id)
                ->pluck('student_code')
                ->toArray();
                
            $this->info("Found " . count($studentCodes) . " enrolled students.");
            
            // Get the users
            $students = User::whereIn('code', $studentCodes)->get();
            
            if ($students->isEmpty()) {
                $this->error("No students found enrolled in this exam.");
                return 1;
            }
            
            $this->info("Found {$students->count()} student accounts.");
        }
        
        $notificationsSent = 0;
        $notificationsFailed = 0;
        
        // Send notification to each student
        foreach ($students as $student) {
            try {
                $this->info("Sending a notification to: {$student->email}");
                
                // Debug: Check if first_name exists
                $firstName = $student->first_name ?? $student->name ?? 'Student';
                $this->info("Using first name: {$firstName}");
                
                $data = [
                    'subject' => 'A humble Reminder',
                    'greeting' => 'Hello ' . $firstName,
                    'message' => 'This is a reminder. You have an exam scheduled as detailed below:',
                    'exam_details' => [
                        'unit' => $exam->unit->code . ' - ' . $exam->unit->name,
                        'date' => $exam->date,
                        'day' => $exam->day,
                        'time' => $exam->start_time . ' - ' . $exam->end_time,
                        'venue' => $exam->venue . ' (' . ($exam->location ?? 'Main Campus') . ')',
                    ],
                    'closing' => 'Good luck with your exam preparation!'
                ];
                
                // Debug: Output the data being sent
                $this->info("Notification data: " . json_encode($data));
                
                $student->notify(new Exam_reminder($data));
                
                // Log the notification
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
                
                $notificationsSent++;
                $this->info("✓ Notification sent successfully to {$student->email}");
                
            } catch (\Exception $e) {
                $this->error("Failed to send notification to {$student->email}: {$e->getMessage()}");
                $notificationsFailed++;
                
                // Log the failed notification
                DB::table('notification_logs')->insert([
                    'notification_type' => 'App\\Notifications\\Exam_reminder',
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $student->id,
                    'channel' => 'mail',
                    'success' => false,
                    'error_message' => $e->getMessage(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
        
        $this->info("Test completed. Sent: {$notificationsSent}, Failed: {$notificationsFailed}");
        return 0;
    }
}