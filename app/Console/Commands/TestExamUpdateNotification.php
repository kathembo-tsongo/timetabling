<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExamTimetable;
use App\Models\User;
use App\Notifications\ExamTimetableUpdate;
use Illuminate\Support\Facades\Log;

class TestExamUpdateNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exams:test-update-notification {exam_id? : The ID of the exam to test} {--email= : Test email address}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test sending exam update notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting exam update notification test...');
        
        // Get the exam ID from the argument or use the first exam
        $examId = $this->argument('exam_id');
        $testEmail = $this->option('email');
        
        // Find the exam
        $exam = $examId 
            ? ExamTimetable::with(['unit', 'semester'])->find($examId)
            : ExamTimetable::with(['unit', 'semester'])->first();
        
        if (!$exam) {
            $this->error("No exam found to test with.");
            return 2;
        }
        
        $this->info("Testing update notification for exam: {$exam->unit->code} - {$exam->unit->name}");
        
        // Create mock changes for testing
        $changes = [
            'venue' => [
                'old' => $exam->venue,
                'new' => 'New Test Venue'
            ],
            'start_time' => [
                'old' => $exam->start_time,
                'new' => '10:00'
            ],
            'end_time' => [
                'old' => $exam->end_time,
                'new' => '12:00'
            ]
        ];
        
        // Prepare notification data
        $data = [
            'subject' => "[TEST] Exam Schedule Update for {$exam->unit->code}",
            'greeting' => "Hello",
            'message' => "This is a TEST notification. There has been an update to your exam schedule for {$exam->unit->code} - {$exam->unit->name}. Please review the changes below:",
            'exam_details' => [
                'unit' => $exam->unit->code . ' - ' . $exam->unit->name,
                'date' => $exam->date,
                'day' => $exam->day,
                'time' => $exam->start_time . ' - ' . $exam->end_time,
                'venue' => $exam->venue . ' (' . $exam->location . ')'
            ],
            'changes' => $changes,
            'closing' => 'This is a TEST notification. No actual changes have been made to your exam schedule.'
        ];
        
        try {
            if ($testEmail) {
                // Send to specific email for testing
                $this->info("Sending test notification to: {$testEmail}");
                
                // Create or find test user
                $testUser = User::where('email', $testEmail)->first();
                
                if (!$testUser) {
                    $this->info("Creating temporary test user with email: {$testEmail}");
                    $testUser = new User([
                        'name' => 'Test User',
                        'first_name' => 'Test',
                        'last_name' => 'User',
                        'email' => $testEmail,
                        'code' => 'TEST001',
                    ]);
                }
                
                $testUser->notify(new ExamTimetableUpdate($data));
                $this->info("✓ Test notification sent successfully to {$testEmail}");
            } else {
                // Find enrolled students
                $this->info("Finding enrolled students...");
                $students = $exam->getEnrolledStudents();
                
                if ($students->isEmpty()) {
                    $this->warn("No students found enrolled in this exam.");
                    return 0;
                }
                
                $this->info("Found {$students->count()} enrolled students.");
                
                // Send to first 3 students only for testing
                $testStudents = $students->take(3);
                
                foreach ($testStudents as $student) {
                    $this->info("Sending test notification to: {$student->email}");
                    $student->notify(new ExamTimetableUpdate($data));
                    $this->info("✓ Test notification sent successfully to {$student->email}");
                }
            }
            
            $this->info("Test completed successfully!");
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Failed to send test notifications: {$e->getMessage()}");
            Log::error("Failed to send test exam update notifications", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 2;
        }
    }
}
