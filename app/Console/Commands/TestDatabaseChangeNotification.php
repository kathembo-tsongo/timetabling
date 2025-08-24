<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExamTimetable;
use App\Models\User;
use App\Notifications\DatabaseChangeNotification;

class TestDatabaseChangeNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:test-db-change {model_id?} {--email=} {--action=updated}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test database change notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing database change notification...');
        
        // Get the model ID from the argument or use a default
        $modelId = $this->argument('model_id');
        $testEmail = $this->option('email');
        $action = $this->option('action');
        
        if (!in_array($action, ['created', 'updated', 'deleted'])) {
            $this->error("Invalid action. Must be one of: created, updated, deleted");
            return 2;
        }
        
        // Find the exam
        if ($modelId) {
            $exam = ExamTimetable::with(['unit', 'semester'])->find($modelId);
            
            if (!$exam) {
                $this->error("Exam with ID {$modelId} not found.");
                return 2;
            }
        } else {
            // Get the first available exam
            $exam = ExamTimetable::with(['unit', 'semester'])->first();
            
            if (!$exam) {
                $this->error("No exams found in the database.");
                return 2;
            }
        }
        
        $this->info("Testing notification for exam: {$exam->unit->code} - {$exam->unit->name}");
        
        // Simulate changes for testing
        $changes = [];
        if ($action === 'updated') {
            $changes = [
                'venue' => 'New Test Venue',
                'start_time' => '10:00',
                'end_time' => '12:00',
            ];
        }
        
        // Get notification data
        $notificationData = $exam->getNotificationData($action, $changes);
        
        // If test email is provided, send to that email only
        if ($testEmail) {
            $this->info("Sending test notification to: {$testEmail}");
            
            // Find or create a test user
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
            
            // Send notification
            $testUser->notify(new DatabaseChangeNotification($notificationData));
            $this->info("Test notification sent successfully!");
        } else {
            // Get users who should be notified
            $users = $exam->getUsersToNotify();
            
            if (empty($users)) {
                $this->warn("No users found to notify for this exam.");
                return 0;
            }
            
            $this->info("Found " . count($users) . " users to notify.");
            
            // Send notification to each user
            foreach ($users as $user) {
                $this->info("Sending notification to: {$user->email}");
                $user->notify(new DatabaseChangeNotification($notificationData));
            }
            
            $this->info("Test notifications sent successfully!");
        }
        
        return 0;
    }
}
