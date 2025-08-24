<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\ExamTimetable;
use App\Notifications\Exam_reminder;
use Carbon\Carbon;

class RetryFailedNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:retry-failed {--hours=24 : Hours to look back for failed notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry sending failed notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $cutoff = Carbon::now()->subHours($hours);
        
        $this->info("Looking for failed notifications in the last {$hours} hours...");
        
        // Get failed notifications
        $failedNotifications = DB::table('notification_logs')
            ->where('success', false)
            ->where('created_at', '>=', $cutoff)
            ->where('notification_type', 'App\\Notifications\\Exam_reminder')
            ->get();
            
        $this->info("Found {$failedNotifications->count()} failed notifications.");
        
        $retried = 0;
        $succeeded = 0;
        
        foreach ($failedNotifications as $failed) {
            $this->info("Retrying notification ID: {$failed->id}");
            
            // Get the user
            $user = User::find($failed->notifiable_id);
            
            if (!$user) {
                $this->error("User not found for ID: {$failed->notifiable_id}");
                continue;
            }
            
            // Get the exam details from the notification data
            // In a real implementation, you might need to store the exam ID in the notification logs
            // For now, we'll try to find the exam based on the user's enrollments
            $tomorrow = Carbon::tomorrow()->toDateString();
            $exams = ExamTimetable::where('date', $tomorrow)
                ->whereHas('enrollments', function ($query) use ($user) {
                    $query->where('student_code', $user->code);
                })
                ->with(['unit', 'semester'])
                ->get();
                
            if ($exams->isEmpty()) {
                $this->error("No exams found for user: {$user->code}");
                continue;
            }
            
            // Retry each exam notification
            foreach ($exams as $exam) {
                try {
                    $this->info("Retrying notification for exam: {$exam->unit->code}");
                    
                    $data = [
                        'subject' => 'Humble reminder',
                        'greeting' => 'Hello ' . $user->first_name,
                        'message' => 'We wish to remind you that you will be having an exam tomorrow. Please keep checking your timetable for further details.',
                        'exam_details' => [
                            'unit' => $exam->unit->code . ' - ' . $exam->unit->name,
                            'date' => $exam->date,
                            'day' => $exam->day,
                            'time' => $exam->start_time . ' - ' . $exam->end_time,
                            'venue' => $exam->venue . ' - ' . $exam->location
                        ],
                        'closing' => 'We wish all the best in your preparation!'
                    ];
                    
                    $user->notify(new Exam_reminder($data));
                    
                    // Log the successful retry
                    DB::table('notification_logs')->insert([
                        'notification_type' => 'App\\Notifications\\Exam_reminder',
                        'notifiable_type' => 'App\\Models\\User',
                        'notifiable_id' => $user->id,
                        'channel' => $failed->channel,
                        'success' => true,
                        'error_message' => null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    $succeeded++;
                    $this->info("Successfully retried notification for user: {$user->code}");
                } catch (\Exception $e) {
                    $this->error("Failed to retry notification: {$e->getMessage()}");
                    
                    // Log the failed retry
                    DB::table('notification_logs')->insert([
                        'notification_type' => 'App\\Notifications\\Exam_reminder',
                        'notifiable_type' => 'App\\Models\\User',
                        'notifiable_id' => $user->id,
                        'channel' => $failed->channel,
                        'success' => false,
                        'error_message' => $e->getMessage(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
            
            $retried++;
        }
        
        $this->info("Retry process completed. Retried: {$retried}, Succeeded: {$succeeded}");
        return 0;
    }
}
