<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\ClassTimetable;
use App\Notifications\ClassTimetableUpdate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class TestClassTimetableUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'class:test-update {user_id?} {class_id?} {--debug : Enable detailed debugging} {--force : Skip database tests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the class timetable update notification';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $classId = $this->argument('class_id');
        $debug = $this->option('debug');
        $force = $this->option('force');
        
        if ($debug && !$force) {
            $this->info('Debug mode enabled - checking notification_logs table...');
            try {
                // Check table structure
                $columns = DB::getSchemaBuilder()->getColumnListing('notification_logs');
                $this->info("notification_logs table columns: " . implode(', ', $columns));
                
                $count = DB::table('notification_logs')->count();
                $this->info("notification_logs table has {$count} records");
                
                // Test direct insert to verify we can write to the table
                try {
                    // Try direct SQL insert
                    $result = DB::statement(
                        'INSERT INTO notification_logs (notification_type, notifiable_type, notifiable_id, channel, success, data, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                        [
                            'TestDirectSQL',
                            'TestUser',
                            0,
                            'test',
                            true,
                            json_encode(['test' => true]),
                            now(),
                            now()
                        ]
                    );
                    
                    $this->info("Direct SQL insert result: " . ($result ? 'Success' : 'Failed'));
                    
                    // Get the ID of the inserted record
                    $testRecord = DB::table('notification_logs')
                        ->where('notification_type', 'TestDirectSQL')
                        ->where('notifiable_id', 0)
                        ->orderBy('id', 'desc')
                        ->first();
                        
                    if ($testRecord) {
                        $this->info("Test record inserted with ID: {$testRecord->id}");
                        
                        // Clean up test record
                        DB::table('notification_logs')->where('id', $testRecord->id)->delete();
                        $this->info("Test record deleted");
                    } else {
                        $this->warn("Test record was not found after insert!");
                    }
                } catch (\Exception $e) {
                    $this->error("Direct SQL insert failed: " . $e->getMessage());
                }
            } catch (\Exception $e) {
                $this->error('Error accessing notification_logs table: ' . $e->getMessage());
                if (!$force) {
                    return 7;
                }
            }
        }
        
        // If no user ID is provided, prompt for one
        if (!$userId) {
            $userId = $this->ask('Enter a user ID to send the test notification to');
        }
        
        // Find the user
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 7;
        }
        
        $this->info("Sending test class timetable update notification to {$user->name} ({$user->email})...");
        
        // If a class ID is provided, use real class data
        if ($classId) {
            $classTimetable = ClassTimetable::with(['unit', 'semester'])->find($classId);
            
            if (!$classTimetable) {
                $this->error("Class timetable with ID {$classId} not found.");
                return 7;
            }
            
            if ($debug) {
                $this->info('Using class timetable data:');
                $this->info(json_encode($classTimetable->toArray(), JSON_PRETTY_PRINT));
            }
            
            // Prepare notification data with real class information
            $data = [
                'subject' => "Important: Class Schedule Update for {$classTimetable->unit->code}",
                'unit_code' => $classTimetable->unit->code,
                'unit_name' => $classTimetable->unit->name,
                'greeting' => "Hello {$user->first_name}",
                'message' => "There has been an update to your class schedule for {$classTimetable->unit->code} - {$classTimetable->unit->name}. Please review the changes below:",
                'class_details' => "Unit: {$classTimetable->unit->code} - {$classTimetable->unit->name}\n" .
                                  "Day: {$classTimetable->day}\n" .
                                  "Time: {$classTimetable->start_time} - {$classTimetable->end_time}\n" .
                                  "Venue: {$classTimetable->venue}\n" .
                                  ($classTimetable->location ? "Location: {$classTimetable->location}\n" : ""),
                'changes' => "- Venue: Changed from \"Old Venue\" to \"{$classTimetable->venue}\"\n" .
                            "- Time: Changed from \"09:00:00 - 10:00:00\" to \"{$classTimetable->start_time} - {$classTimetable->end_time}\"\n",
                'closing' => 'Please make note of these changes and adjust your schedule accordingly. If you have any questions, please contact your course admin.'
            ];
        } else {
            // Use sample data
            $data = [
                'subject' => 'Important: Class Schedule Update for BIT202',
                'unit_code' => 'BIT202',
                'unit_name' => 'Web Development',
                'greeting' => "Hello {$user->first_name}",
                'message' => 'There has been an update to your class schedule for BIT202 - Web Development. Please review the changes below:',
                'class_details' => "Unit: BIT202 - Web Development\n" .
                                  "Day: Tuesday\n" .
                                  "Time: 09:00:00 - 11:00:00\n" .
                                  "Venue: MSB 3 (Updated) (Updated) (Phase2)\n",
                'changes' => "- Venue: Changed from \"MSB 3 (Updated)\" to \"MSB 3 (Updated) (Updated) (Phase2)\"\n",
                'closing' => 'Please make note of these changes and adjust your schedule accordingly. If you have any questions, please contact your your course admin.'
            ];
        }
        
        if ($debug) {
            $this->info('Notification data:');
            $this->info(json_encode($data, JSON_PRETTY_PRINT));
        }
        
        try {
            // Log the attempt
            Log::info('Sending test class timetable update notification', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            // Try a different approach - use Notification facade directly
            $this->info('Sending notification using Notification facade...');
            Notification::send($user, new ClassTimetableUpdate($data));
            
            $this->info('Test notification sent successfully!');
            
            // Check if the notification was logged
            $log = DB::table('notification_logs')
                ->where('notification_type', 'App\\Notifications\\ClassTimetableUpdate')
                ->where('notifiable_id', $user->id)
                ->orderBy('id', 'desc')
                ->first();
                
            if ($log) {
                $this->info('Notification logged successfully:');
                $this->info("ID: {$log->id}, Success: " . ($log->success ? 'Yes' : 'No'));
                if (!$log->success && $log->error_message) {
                    $this->error("Error: {$log->error_message}");
                }
            } else {
                $this->warn('Notification was not logged in the database.');
                
                // Try a direct check with SQL
                $this->info('Trying direct SQL query to check for notification logs...');
                $results = DB::select('SELECT * FROM notification_logs WHERE notification_type = ? AND notifiable_id = ? ORDER BY id DESC LIMIT 1', [
                    'App\\Notifications\\ClassTimetableUpdate',
                    $user->id
                ]);
                
                if (!empty($results)) {
                    $this->info('Found notification log using direct SQL:');
                    $this->info(json_encode($results[0], JSON_PRETTY_PRINT));
                } else {
                    $this->warn('No notification logs found with direct SQL either.');
                    
                    // Try to manually insert a log entry
                    $this->info('Attempting to manually insert a log entry...');
                    try {
                        $result = DB::statement(
                            'INSERT INTO notification_logs (notification_type, notifiable_type, notifiable_id, channel, success, data, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                            [
                                'App\\Notifications\\ClassTimetableUpdate',
                                get_class($user),
                                $user->id,
                                'mail',
                                true,
                                json_encode($data),
                                now(),
                                now()
                            ]
                        );
                        
                        if ($result) {
                            $this->info('Manual log entry created successfully!');
                        } else {
                            $this->error('Failed to create manual log entry.');
                        }
                    } catch (\Exception $e) {
                        $this->error('Error creating manual log entry: ' . $e->getMessage());
                    }
                    
                    $this->info('Check Laravel logs for more information.');
                }
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to send notification: ' . $e->getMessage());
            
            if ($debug) {
                $this->error('Stack trace:');
                $this->error($e->getTraceAsString());
            }
            
            return 1;
        }
    }
}
