<?php

namespace App\Observers;

use App\Models\ExamTimetable;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Exam_reminder;
use App\Notifications\ExamTimetableUpdate;
use Illuminate\Support\Facades\Log;

class ExamTimetableObserver
{
    /**
     * Write debug information directly to a file.
     */
    private function debugToFile($message, $data = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $content = "[{$timestamp}] {$message}\n";
        
        if (!empty($data)) {
            $content .= json_encode($data, JSON_PRETTY_PRINT) . "\n";
        }
        
        $content .= "------------------------------\n";
        
        file_put_contents(
            storage_path('logs/exam_observer_debug.log'),
            $content,
            FILE_APPEND
        );
    }

    /**
     * Handle the ExamTimetable "updated" event.
     */
    public function updated(ExamTimetable $examTimetable): void
    {
        $this->debugToFile('Exam Timetable Observer triggered', [
            'exam_id' => $examTimetable->id,
            'dirty' => $examTimetable->getDirty(),
            'original' => $examTimetable->getOriginal()
        ]);
        
        // Get the changed attributes
        $changes = [];
        $dirty = $examTimetable->getDirty();
        
        // Only track specific fields that are relevant to students and lecturers
        $relevantFields = [
            'date', 'day', 'start_time', 'end_time', 'venue', 'location'
        ];
        
        foreach ($relevantFields as $field) {
            if (array_key_exists($field, $dirty)) {
                $oldValue = $examTimetable->getOriginal($field);
                $newValue = $dirty[$field];
                
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }
        
        $this->debugToFile('Changes detected', $changes);
        
        // Only send notifications if relevant fields were changed
        if (!empty($changes)) {
            $this->debugToFile('Preparing to notify students and lecturers');
            $this->notifyStudentsAndLecturers($examTimetable, $changes);
        } else {
            $this->debugToFile('No relevant changes, skipping notifications');
        }
    }
    
    /**
     * Notify students and lecturers about exam timetable changes.
     */
    private function notifyStudentsAndLecturers(ExamTimetable $examTimetable, array $changes): void
    {
        try {
            $this->debugToFile('Loading relationships');
            // Load relationships if not already loaded
            $examTimetable->load(['unit', 'semester']);
            
            // Find all enrollments for this unit and semester
            $enrollments = Enrollment::where('unit_id', $examTimetable->unit_id)
                ->where('semester_id', $examTimetable->semester_id)
                ->get();
                
            $this->debugToFile('Enrollments found', [
                'count' => $enrollments->count()
            ]);
            
            if ($enrollments->isEmpty()) {
                $this->debugToFile('No enrollments found, skipping notifications');
                Log::info("No enrollments found for exam update notification", [
                    'exam_id' => $examTimetable->id,
                    'unit_id' => $examTimetable->unit_id,
                    'semester_id' => $examTimetable->semester_id
                ]);
                return;
            }
            
            // Extract student codes and lecturer code
            $studentCodes = $enrollments->pluck('student_code')->toArray();
            
            // Find the lecturer code - it should be the same for all enrollments in this unit/semester
            $lecturerCode = null;
            foreach ($enrollments as $enrollment) {
                if (!empty($enrollment->lecturer_code)) {
                    $lecturerCode = $enrollment->lecturer_code;
                    break;
                }
            }
            
            $this->debugToFile('Student and lecturer codes found', [
                'student_count' => count($studentCodes),
                'lecturer_code' => $lecturerCode
            ]);
            
            // Get all students with these codes
            $students = User::whereIn('code', $studentCodes)->get();
            
            // Get the lecturer if a code was found
            $lecturer = null;
            if ($lecturerCode) {
                $lecturer = User::where('code', $lecturerCode)->first();
                
                if ($lecturer) {
                    $this->debugToFile('Lecturer found', [
                        'id' => $lecturer->id,
                        'email' => $lecturer->email
                    ]);
                } else {
                    $this->debugToFile('Lecturer not found for code: ' . $lecturerCode);
                }
            }
            
            $this->debugToFile('Students found', [
                'count' => $students->count(),
                'first_few' => $students->take(3)->pluck('email')->toArray()
            ]);
            
            if ($students->isEmpty() && !$lecturer) {
                $this->debugToFile('No students or lecturer found, skipping notifications');
                Log::info("No recipients found for exam update notification", [
                    'exam_id' => $examTimetable->id
                ]);
                return;
            }
            
            $this->debugToFile('Preparing notification data');
            
            // Format date for display
            $formattedDate = date('F j, Y', strtotime($examTimetable->date));
            
            // Format venue display
            $venueDisplay = $examTimetable->venue;
            if (!empty($examTimetable->location)) {
                $venueDisplay .= ' (' . $examTimetable->location . ')';
            }
            
            // Format changes for notification
            $changesText = '';
            foreach ($changes as $field => $change) {
                $fieldName = ucfirst($field);
                $changesText .= "- {$fieldName}: Changed from \"{$change['old']}\" to \"{$change['new']}\"\n";
            }
            
            // Count notifications
            $notificationsSent = 0;
            $notificationsFailed = 0;
            
            // First, notify the lecturer if found
            if ($lecturer) {
                try {
                    // Get the lecturer's first name
                    $firstName = $lecturer->first_name ?? $lecturer->name ?? 'Lecturer';
                    $this->debugToFile("Preparing notification for lecturer {$lecturer->id} ({$lecturer->email}) - {$firstName}");
                    
                    // Prepare notification data with personalized greeting for lecturer
                    $data = [
                        'subject' => "Important: Exam Schedule Update for {$examTimetable->unit->code}",
                        'greeting' => "Hello {$firstName}",
                        'message' => "There has been an update to the exam schedule for {$examTimetable->unit->code} - {$examTimetable->unit->name}. Please review the changes below:",
                        'exam_details' => "Unit: {$examTimetable->unit->code} - {$examTimetable->unit->name}\n" .
                                          "Date: {$formattedDate}\n" .
                                          "Day: {$examTimetable->day}\n" .
                                          "Time: {$examTimetable->start_time} - {$examTimetable->end_time}\n" .
                                          "Venue: {$venueDisplay}",
                        'changes' => $changesText,
                        'closing' => 'Please make note of these changes. Your students have also been notified of this update.',
                        'is_lecturer' => true
                    ];
                    
                    $this->debugToFile("Sending to lecturer {$lecturer->id} ({$lecturer->email})");
                    $lecturer->notify(new ExamTimetableUpdate($data));
                    
                    $this->debugToFile("Notification sent successfully to lecturer {$lecturer->email}");
                    
                    // Log the notification
                    Log::info("Sent exam update notification to lecturer", [
                        'lecturer_code' => $lecturer->code,
                        'lecturer_email' => $lecturer->email,
                        'exam_id' => $examTimetable->id
                    ]);
                    
                    // Log to notification_logs table
                    $this->logNotificationToDatabase($lecturer, $examTimetable, true, null, true);
                    $notificationsSent++;
                } catch (\Exception $e) {
                    $this->debugToFile("Error sending to lecturer {$lecturer->email}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    // Log the failed notification
                    $this->logNotificationToDatabase($lecturer, $examTimetable, false, $e->getMessage(), true);
                    $notificationsFailed++;
                }
            }
            
            // Then, notify all students
            foreach ($students as $student) {
                try {
                    // Get the student's first name
                    $firstName = $student->first_name ?? $student->name ?? 'Student';
                    $this->debugToFile("Preparing notification for student {$student->id} ({$student->email}) - {$firstName}");
                    
                    // Prepare notification data with personalized greeting
                    $data = [
                        'subject' => "Important: Exam Schedule Update for {$examTimetable->unit->code}",
                        'greeting' => "Hello {$firstName}",
                        'message' => "There has been an update to your exam schedule for {$examTimetable->unit->code} - {$examTimetable->unit->name}. Please review the changes below:",
                        'exam_details' => "Unit: {$examTimetable->unit->code} - {$examTimetable->unit->name}\n" .
                                          "Date: {$formattedDate}\n" .
                                          "Day: {$examTimetable->day}\n" .
                                          "Time: {$examTimetable->start_time} - {$examTimetable->end_time}\n" .
                                          "Venue: {$venueDisplay}",
                        'changes' => $changesText,
                        'closing' => 'Please make note of these changes and adjust your schedule accordingly. If you have any questions, please contact your instructor.'
                    ];
                    
                    $this->debugToFile("Sending to student {$student->id} ({$student->email})");
                    $student->notify(new ExamTimetableUpdate($data));
                    
                    $this->debugToFile("Notification sent successfully to {$student->email}");
                    
                    // Log the notification
                    Log::info("Sent exam update notification to student", [
                        'student_code' => $student->code,
                        'student_email' => $student->email,
                        'exam_id' => $examTimetable->id
                    ]);
                    
                    // Log to notification_logs table
                    $this->logNotificationToDatabase($student, $examTimetable, true);
                    $notificationsSent++;
                } catch (\Exception $e) {
                    $this->debugToFile("Error sending to {$student->email}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    // Log the failed notification
                    $this->logNotificationToDatabase($student, $examTimetable, false, $e->getMessage());
                    $notificationsFailed++;
                }
            }
            
            $this->debugToFile("All notifications processed: {$notificationsSent} sent, {$notificationsFailed} failed");
            
        } catch (\Exception $e) {
            $this->debugToFile("Error in notifyStudentsAndLecturers method", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Log::error("Failed to send exam update notifications", [
                'exam_id' => $examTimetable->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Log notification to database.
     */
    private function logNotificationToDatabase($user, $examTimetable, $success, $errorMessage = null, $isLecturer = false): void
    {
        try {
            $this->debugToFile("Logging notification to database", [
                'user_id' => $user->id,
                'exam_id' => $examTimetable->id,
                'success' => $success,
                'is_lecturer' => $isLecturer
            ]);
            
            \DB::table('notification_logs')->insert([
                'notification_type' => 'App\\Notifications\\ExamTimetableUpdate',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $user->id,
                'channel' => 'mail',
                'success' => $success,
                'error_message' => $errorMessage,
                'data' => json_encode([
                    'exam_id' => $examTimetable->id,
                    'unit_code' => $examTimetable->unit->code ?? null,
                    'unit_name' => $examTimetable->unit->name ?? null,
                    'is_lecturer' => $isLecturer
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $this->debugToFile("Successfully logged to database");
        } catch (\Exception $e) {
            $this->debugToFile("Error logging to database", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Log::error("Failed to log notification to database", [
                'error' => $e->getMessage()
            ]);
        }
    }
}
