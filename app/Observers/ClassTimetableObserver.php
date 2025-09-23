<?php

namespace App\Observers;

use App\Models\ClassTimetable;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\ClassTimetableUpdate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ClassTimetableObserver
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
            storage_path('logs/class_observer_debug.log'),
            $content,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * Clean up venue text to avoid duplication of "(Updated)".
     */
    private function cleanVenueText($venue)
    {
        if (empty($venue)) return '';
        
        // Remove all instances of "(Updated)" from the venue
        $cleanVenue = preg_replace('/\s*\(Updated\)\s*/', '', $venue);
        // Trim extra spaces
        return trim($cleanVenue);
    }

    /**
     * Handle the ClassTimetable "updated" event.
     */
    public function updated(ClassTimetable $classTimetable): void
    {
        $this->debugToFile('=== CLASS TIMETABLE OBSERVER TRIGGERED ===', [
            'timetable_id' => $classTimetable->id,
            'class_id' => $classTimetable->class_id,
            'unit_id' => $classTimetable->unit_id,
            'semester_id' => $classTimetable->semester_id,
            'lecturer_field' => $classTimetable->lecturer ?? 'NULL',
            'dirty' => $classTimetable->getDirty(),
            'original' => $classTimetable->getOriginal()
        ]);
        
        // Get the changed attributes
        $changes = [];
        $dirty = $classTimetable->getDirty();
        
        // Only track specific fields that are relevant to students and lecturers
        $relevantFields = [
            'day', 'start_time', 'end_time', 'venue', 'location'
        ];
        
        foreach ($relevantFields as $field) {
            if (array_key_exists($field, $dirty)) {
                $oldValue = $classTimetable->getOriginal($field);
                $newValue = $dirty[$field];
                
                // Clean up venue values to prevent duplicate "(Updated)"
                if ($field === 'venue') {
                    $oldValue = $this->cleanVenueText($oldValue);
                    $newValue = $this->cleanVenueText($newValue) . ' (Updated)';
                }
                
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
            $this->notifyStudentsAndLecturers($classTimetable, $changes);
        } else {
            $this->debugToFile('No relevant changes, skipping notifications');
        }
    }
    
    /**
     * Notify students and lecturers about class timetable changes.
     */
    private function notifyStudentsAndLecturers(ClassTimetable $classTimetable, array $changes): void
    {
        try {
            $this->debugToFile('=== STARTING NOTIFICATION PROCESS ===');
            // Load relationships if not already loaded
            $classTimetable->load(['unit', 'semester']);
            
            // DETAILED DEBUGGING: Get enrollments for this specific class section
            $this->debugToFile('Searching for enrollments with criteria:', [
                'class_id' => $classTimetable->class_id,
                'semester_id' => $classTimetable->semester_id
            ]);
            
            $enrollments = Enrollment::where('class_id', $classTimetable->class_id)
                ->where('semester_id', $classTimetable->semester_id)
                ->where('status', 'enrolled')
                ->get();
                
            $this->debugToFile('=== ENROLLMENT SEARCH RESULTS ===', [
                'class_id' => $classTimetable->class_id,
                'semester_id' => $classTimetable->semester_id,
                'total_enrollments_found' => $enrollments->count(),
                'all_enrollment_details' => $enrollments->map(function($e) {
                    return [
                        'id' => $e->id,
                        'student_code' => $e->student_code,
                        'class_id' => $e->class_id,
                        'unit_id' => $e->unit_id,
                        'lecturer_code' => $e->lecturer_code ?? 'NULL',
                        'status' => $e->status
                    ];
                })->toArray()
            ]);
            
            if ($enrollments->isEmpty()) {
                $this->debugToFile('=== NO ENROLLMENTS FOUND - INVESTIGATING ===');
                
                // Check if there are ANY enrollments for this unit regardless of class
                $allUnitEnrollments = Enrollment::where('unit_id', $classTimetable->unit_id)
                    ->where('semester_id', $classTimetable->semester_id)
                    ->get();
                    
                $this->debugToFile('All enrollments for this unit (any class):', [
                    'unit_id' => $classTimetable->unit_id,
                    'count' => $allUnitEnrollments->count(),
                    'details' => $allUnitEnrollments->map(function($e) {
                        return [
                            'student_code' => $e->student_code,
                            'class_id' => $e->class_id,
                            'lecturer_code' => $e->lecturer_code ?? 'NULL',
                            'status' => $e->status
                        ];
                    })->take(10)->toArray()
                ]);
                
                return;
            }
            
            // Get students and lecturer
            $studentCodes = $enrollments->pluck('student_code')->unique()->filter()->toArray();
            $this->debugToFile('Student codes extracted:', $studentCodes);
            
            // DETAILED LECTURER FINDING
            $lecturer = $this->findLecturerForClass($classTimetable, $enrollments);
            
            $this->debugToFile('=== RECIPIENT SUMMARY ===', [
                'student_codes_count' => count($studentCodes),
                'student_codes' => $studentCodes,
                'lecturer_found' => $lecturer ? true : false,
                'lecturer_details' => $lecturer ? [
                    'id' => $lecturer->id,
                    'name' => $lecturer->name,
                    'email' => $lecturer->email,
                    'code' => $lecturer->code
                ] : 'NO LECTURER FOUND'
            ]);
            
            // Get students
            $students = collect();
            if (!empty($studentCodes)) {
                $students = User::whereIn('code', $studentCodes)->get();
                $this->debugToFile('Students found in users table:', [
                    'count' => $students->count(),
                    'details' => $students->map(function($s) {
                        return [
                            'code' => $s->code,
                            'email' => $s->email,
                            'name' => $s->name
                        ];
                    })->toArray()
                ]);
            }
            
            if ($students->isEmpty() && !$lecturer) {
                $this->debugToFile('=== NO VALID RECIPIENTS FOUND ===');
                return;
            }
            
            // Prepare notification data
            $notificationData = $this->prepareNotificationData($classTimetable, $changes);
            
            $notificationsSent = 0;
            $notificationsFailed = 0;
            
            // Send to lecturer first
            if ($lecturer) {
                $this->debugToFile('=== SENDING TO LECTURER ===', [
                    'lecturer_email' => $lecturer->email,
                    'lecturer_name' => $lecturer->name
                ]);
                
                $result = $this->sendNotificationSafely($lecturer, $notificationData, true, $classTimetable);
                if ($result) {
                    $notificationsSent++;
                    $this->debugToFile('✅ LECTURER NOTIFICATION SENT SUCCESSFULLY');
                } else {
                    $notificationsFailed++;
                    $this->debugToFile('❌ LECTURER NOTIFICATION FAILED');
                }
            } else {
                $this->debugToFile('❌ NO LECTURER TO NOTIFY');
            }
            
            // Send to students
            foreach ($students as $student) {
                $this->debugToFile("Sending to student: {$student->email}");
                $result = $this->sendNotificationSafely($student, $notificationData, false, $classTimetable);
                if ($result) {
                    $notificationsSent++;
                } else {
                    $notificationsFailed++;
                }
            }
            
            $this->debugToFile("=== NOTIFICATION PROCESS COMPLETE ===", [
                'sent' => $notificationsSent,
                'failed' => $notificationsFailed
            ]);
            
        } catch (\Exception $e) {
            $this->debugToFile("=== CRITICAL ERROR IN NOTIFICATION PROCESS ===", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            Log::error("Failed to send class update notifications", [
                'timetable_id' => $classTimetable->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Find lecturer using unit_assignments table - FIXED FOR YOUR DATABASE STRUCTURE
     */
    private function findLecturerForClass($classTimetable, $enrollments)
    {
        $this->debugToFile('=== STARTING LECTURER SEARCH (FIXED VERSION) ===');
        
        // Method 1: From unit_assignments table using lecturer_code (THIS IS THE FIX!)
        $this->debugToFile('METHOD 1: Searching from unit_assignments table');
        try {
            $this->debugToFile('Searching unit_assignments with:', [
                'unit_id' => $classTimetable->unit_id,
                'semester_id' => $classTimetable->semester_id,
                'class_id' => $classTimetable->class_id
            ]);
            
            // First try exact match with class_id
            $assignment = DB::table('unit_assignments')
                ->where('unit_id', $classTimetable->unit_id)
                ->where('semester_id', $classTimetable->semester_id)
                ->where('class_id', $classTimetable->class_id)
                ->where('is_active', 1)
                ->first();
                
            $this->debugToFile('Unit assignment result (with class_id):', [
                'assignment_found' => $assignment ? true : false,
                'assignment_details' => $assignment ? (array) $assignment : null
            ]);
            
            // If found, get lecturer by lecturer_code
            if ($assignment && !empty($assignment->lecturer_code)) {
                $lecturer = User::where('code', $assignment->lecturer_code)->first();
                if ($lecturer) {
                    $this->debugToFile('✅ METHOD 1A SUCCESS: Lecturer found from unit_assignments with class_id', [
                        'lecturer_code' => $assignment->lecturer_code,
                        'lecturer_name' => $lecturer->name,
                        'lecturer_email' => $lecturer->email
                    ]);
                    return $lecturer;
                }
            }
            
            // Try broader search without class_id constraint
            $this->debugToFile('Trying broader search without class_id constraint');
            $broaderAssignment = DB::table('unit_assignments')
                ->where('unit_id', $classTimetable->unit_id)
                ->where('semester_id', $classTimetable->semester_id)
                ->where('is_active', 1)
                ->first();
                
            $this->debugToFile('Unit assignment result (without class_id):', [
                'assignment_found' => $broaderAssignment ? true : false,
                'assignment_details' => $broaderAssignment ? (array) $broaderAssignment : null
            ]);
                
            if ($broaderAssignment && !empty($broaderAssignment->lecturer_code)) {
                $lecturer = User::where('code', $broaderAssignment->lecturer_code)->first();
                if ($lecturer) {
                    $this->debugToFile('✅ METHOD 1B SUCCESS: Lecturer found from broader unit_assignments', [
                        'lecturer_code' => $broaderAssignment->lecturer_code,
                        'lecturer_name' => $lecturer->name,
                        'lecturer_email' => $lecturer->email
                    ]);
                    return $lecturer;
                }
            }
            
        } catch (\Exception $e) {
            $this->debugToFile('❌ METHOD 1 ERROR: Error finding lecturer from unit_assignments', [
                'error' => $e->getMessage()
            ]);
        }
        
        // Method 2: From enrollments (fallback - but likely empty)
        $this->debugToFile('METHOD 2: Fallback search from enrollments');
        $lecturerCodes = $enrollments->pluck('lecturer_code')->filter()->unique()->toArray();
        $this->debugToFile('Lecturer codes found in enrollments:', $lecturerCodes);
        
        foreach ($lecturerCodes as $lecturerCode) {
            if (!empty($lecturerCode)) {
                $lecturer = User::where('code', $lecturerCode)->first();
                if ($lecturer) {
                    $this->debugToFile('✅ METHOD 2 SUCCESS: Lecturer found from enrollments', [
                        'lecturer_code' => $lecturerCode,
                        'lecturer_name' => $lecturer->name,
                        'lecturer_email' => $lecturer->email
                    ]);
                    return $lecturer;
                }
            }
        }
        
        // Method 3: From timetable lecturer field
        $this->debugToFile('METHOD 3: Searching from timetable lecturer field');
        if (!empty($classTimetable->lecturer)) {
            $this->debugToFile("Searching for lecturer by name: {$classTimetable->lecturer}");
            try {
                $lecturer = User::where('name', 'LIKE', '%' . $classTimetable->lecturer . '%')
                    ->whereHas('roles', function($query) {
                        $query->where('name', 'Lecturer');
                    })
                    ->first();
                    
                if ($lecturer) {
                    $this->debugToFile('✅ METHOD 3 SUCCESS: Lecturer found from timetable field', [
                        'lecturer_name' => $lecturer->name,
                        'lecturer_email' => $lecturer->email
                    ]);
                    return $lecturer;
                }
            } catch (\Exception $e) {
                $this->debugToFile('❌ METHOD 3 ERROR: Error finding lecturer from timetable field', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Method 4: Debug - show all unit assignments for this unit to understand structure
        $this->debugToFile('METHOD 4: DEBUG - Show all unit assignments for this unit');
        try {
            $allAssignments = DB::table('unit_assignments')
                ->where('unit_id', $classTimetable->unit_id)
                ->get();
                
            $this->debugToFile('All unit assignments for unit_id ' . $classTimetable->unit_id . ':', [
                'count' => $allAssignments->count(),
                'assignments' => $allAssignments->map(function($a) {
                    return [
                        'id' => $a->id,
                        'lecturer_code' => $a->lecturer_code ?? 'NULL',
                        'semester_id' => $a->semester_id,
                        'class_id' => $a->class_id,
                        'is_active' => $a->is_active
                    ];
                })->toArray()
            ]);
        } catch (\Exception $e) {
            $this->debugToFile('Error getting all assignments: ' . $e->getMessage());
        }
        
        $this->debugToFile('❌ ALL METHODS FAILED: No lecturer found');
        return null;
    }
    
    /**
     * Prepare notification data
     */
    private function prepareNotificationData($classTimetable, $changes)
    {
        // Get class section info
        $classInfo = DB::table('classes')->where('id', $classTimetable->class_id)->first();
        $sectionText = $classInfo && $classInfo->section ? " Section {$classInfo->section}" : '';
        
        // Format venue display
        $venue = $this->cleanVenueText($classTimetable->venue);
        $location = $classTimetable->location ?? '';
        $venueDisplay = $venue;
        if (!empty($location)) {
            $venueDisplay .= ' (' . $location . ')';
        }
        
        // Format changes
        $changesText = '';
        foreach ($changes as $field => $change) {
            $fieldName = ucfirst($field);
            $changesText .= "- {$fieldName}: Changed from \"{$change['old']}\" to \"{$change['new']}\"\n";
        }
        
        return [
            'unit' => $classTimetable->unit,
            'sectionText' => $sectionText,
            'venueDisplay' => $venueDisplay,
            'changesText' => $changesText,
            'classTimetable' => $classTimetable
        ];
    }
    
    /**
     * Send notification safely with proper error handling
     */
    private function sendNotificationSafely($user, $notificationData, $isLecturer, $classTimetable)
    {
        try {
            $firstName = $user->first_name ?? $user->name ?? ($isLecturer ? 'Lecturer' : 'Student');
            
            $data = [
                'subject' => "Important: Class Schedule Update for {$notificationData['unit']->code}{$notificationData['sectionText']}",
                'greeting' => "Hello {$firstName}",
                'message' => "There has been an update to your class schedule for {$notificationData['unit']->code} - {$notificationData['unit']->name}{$notificationData['sectionText']}. Please review the changes below:",
                'class_details' => "Unit: {$notificationData['unit']->code} - {$notificationData['unit']->name}{$notificationData['sectionText']}\n" .
                                  "Day: {$notificationData['classTimetable']->day}\n" .
                                  "Time: {$notificationData['classTimetable']->start_time} - {$notificationData['classTimetable']->end_time}\n" .
                                  "Venue: {$notificationData['venueDisplay']}",
                'changes' => $notificationData['changesText'],
                'closing' => $isLecturer ? 
                    'Please make note of these changes and adjust your schedule accordingly. Your students have also been notified of this change.' :
                    'Please make note of these changes and adjust your schedule accordingly. If you have any questions, please contact your instructor.',
                'is_lecturer' => $isLecturer
            ];
            
            $this->debugToFile("📧 ATTEMPTING TO SEND to {$user->email} (" . ($isLecturer ? 'lecturer' : 'student') . ")", [
                'subject' => $data['subject'],
                'greeting' => $data['greeting']
            ]);
            
            $user->notify(new ClassTimetableUpdate($data));
            
            $this->debugToFile("✅ NOTIFICATION SENT SUCCESSFULLY to {$user->email}");
            
            Log::info("Sent class update notification", [
                'user_code' => $user->code,
                'user_email' => $user->email,
                'is_lecturer' => $isLecturer,
                'timetable_id' => $classTimetable->id,
                'class_id' => $classTimetable->class_id
            ]);
            
            $this->logNotificationToDatabase($user, $classTimetable, true, null, $isLecturer);
            return true;
            
        } catch (\Exception $e) {
            $this->debugToFile("❌ ERROR SENDING to {$user->email}", [
                'error' => $e->getMessage(),
                'is_lecturer' => $isLecturer,
                'trace' => $e->getTraceAsString()
            ]);
            
            Log::error("Failed to send notification", [
                'user_email' => $user->email,
                'error' => $e->getMessage()
            ]);
            
            $this->logNotificationToDatabase($user, $classTimetable, false, $e->getMessage(), $isLecturer);
            return false;
        }
    }
    
    /**
     * Log notification to database.
     */
    private function logNotificationToDatabase($user, $classTimetable, $success, $errorMessage = null, $isLecturer = false): void
    {
        try {
            DB::table('notification_logs')->insert([
                'notification_type' => 'App\\Notifications\\ClassTimetableUpdate',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $user->id,
                'channel' => 'mail',
                'success' => $success,
                'error_message' => $errorMessage,
                'data' => json_encode([
                    'timetable_id' => $classTimetable->id,
                    'class_id' => $classTimetable->class_id,
                    'unit_code' => $classTimetable->unit->code ?? null,
                    'unit_name' => $classTimetable->unit->name ?? null,
                    'is_lecturer' => $isLecturer
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
        } catch (\Exception $e) {
            $this->debugToFile("Error logging to database", [
                'error' => $e->getMessage()
            ]);
            
            Log::error("Failed to log notification to database", [
                'error' => $e->getMessage()
            ]);
        }
    }
}