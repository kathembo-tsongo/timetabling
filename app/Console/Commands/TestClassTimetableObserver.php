<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClassTimetable;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TestClassTimetableObserver extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'classes:test-observer {class_id? : The ID of the class to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the ClassTimetableObserver by updating a class timetable';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // If no class ID is provided, list available classes
        if (!$this->argument('class_id')) {
            $this->listAvailableClasses();
            return 0;
        }
        
        $classId = $this->argument('class_id');
        $this->info("Testing observer with class ID: {$classId}");
        
        // Find the class timetable
        $class = ClassTimetable::with(['unit', 'semester'])->find($classId);
        
        if (!$class) {
            $this->error("Class timetable with ID {$classId} not found.");
            return 1;
        }
        
        $this->info("Class: {$class->unit->code} - {$class->unit->name}");
        $this->info("Semester: {$class->semester->name}");
        
        // Check if there are students enrolled in this class
        $enrollments = Enrollment::where('unit_id', $class->unit_id)
            ->where('semester_id', $class->semester_id)
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
            $this->warn("No lecturer is assigned to this class.");
        }
        
        if ($enrollments->count() === 0 && !$lecturerCode) {
            $this->warn("No students or lecturer found for this class. No notifications will be sent.");
            if (!$this->confirm("Do you want to continue anyway?")) {
                return 0;
            }
        }
        
        // Get current venue
        $currentVenue = $class->venue;
        $this->info("Current venue: {$currentVenue}");
        
        // Clean the venue first to avoid accumulating (Updated)
        $cleanVenue = preg_replace('/\s*$$Updated$$\s*/', '', $currentVenue);
        $cleanVenue = trim($cleanVenue);
        
        // Update with a clean venue + single (Updated)
        $newVenue = $cleanVenue . " (Updated)";
        
        // Update the class timetable
        $class->venue = $newVenue;
        $this->info("Updated venue to: {$newVenue}");
        
        // Save the changes
        $class->save();
        
        $this->info("Observer should have been triggered. Check logs and notification_logs table.");
        
        // Check notification logs
        $this->info("Checking notification logs...");
        $logs = \DB::table('notification_logs')
            ->where('notification_type', 'App\\Notifications\\ClassTimetableUpdate')
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
     * List available classes for testing.
     */
    private function listAvailableClasses()
    {
        $this->info("Available class timetables:");
        
        $classes = ClassTimetable::with(['unit', 'semester'])
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();
            
        $headers = ['ID', 'Unit Code', 'Unit Name', 'Day', 'Time', 'Venue'];
        $rows = [];
        
        foreach ($classes as $class) {
            $rows[] = [
                $class->id,
                $class->unit->code ?? 'N/A',
                $class->unit->name ?? 'N/A',
                $class->day,
                $class->start_time . ' - ' . $class->end_time,
                $class->venue
            ];
        }
        
        $this->table($headers, $rows);
        
        $this->info("To test a specific class, run:");
        $this->line("php artisan classes:test-observer [class_id]");
    }
}
