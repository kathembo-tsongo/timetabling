<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ExamTimetable;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Exam_reminder;
use App\Notifications\ExamTimetableUpdate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    /**
     * Display the notification dashboard.
     */
    public function index()
    {
        // Get upcoming exams for the next 7 days
        $upcomingExams = ExamTimetable::where('date', '>=', Carbon::today()->toDateString())
            ->where('date', '<=', Carbon::today()->addDays(7)->toDateString())
            ->with(['unit', 'semester'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
            
        // Get notification logs
        $recentNotifications = [];
        if (Schema::hasTable('notification_logs')) {
            $recentNotifications = DB::table('notification_logs')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
        }
        
        // Get notification types for filtering
        $notificationTypes = [];
        if (Schema::hasTable('notification_logs')) {
            $notificationTypes = DB::table('notification_logs')
                ->select('notification_type')
                ->distinct()
                ->get()
                ->map(function($item) {
                    // Clean up the notification type name
                    return [
                        'value' => $item->notification_type,
                        'label' => str_replace('App\\Notifications\\', '', $item->notification_type)
                    ];
                });
        }
            
        return Inertia::render('Notifications/Dashboard', [
            'upcomingExams' => $upcomingExams,
            'recentNotifications' => $recentNotifications,
            'notificationTypes' => $notificationTypes,
            'can' => [
                'send_notifications' => auth()->user()->can('send-notifications'),
                'view_logs' => auth()->user()->can('view-notification-logs'),
            ],
        ]);
    }
    
    /**
     * Manually trigger exam reminders.
     */
    public function sendReminders(Request $request)
    {
        // Check permissions
        if (!auth()->user()->can('send-notifications')) {
            return redirect()->back()->with('error', 'You do not have permission to send notifications.');
        }

        try {
            // Run the command
            Artisan::call('exams:send-reminders');
            $output = Artisan::output();
            
            // Log the action
            Log::info('Manual notification trigger', [
                'user_id' => auth()->id(),
                'output' => $output
            ]);

            // Insert success log into notification_logs table
            DB::table('notification_logs')->insert([
                'notification_type' => 'App\Notifications\Exam_reminder',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => auth()->id(),
                'channel' => 'mail',
                'success' => true,
                'error_message' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->back()->with('success', 'Exam reminders sent successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to send exam reminders', [
                'error' => $e->getMessage(),
            ]);

            // Insert error log into notification_logs table
            DB::table('notification_logs')->insert([
                'notification_type' => 'App\Notifications\Exam_reminder',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => auth()->id(),
                'channel' => 'mail',
                'success' => false,
                'error_message' => $e->getMessage(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->back()->with('error', 'Failed to send exam reminders: ' . $e->getMessage());
        }
    }
    
    /**
     * Preview notifications for a specific exam.
     */
    public function previewNotifications(Request $request, $examId)
    {
        $exam = ExamTimetable::with(['unit', 'semester'])->findOrFail($examId);
        
        // Find all student codes enrolled in this unit for this semester
        $studentCodes = Enrollment::where('unit_id', $exam->unit_id)
            ->where('semester_id', $exam->semester_id)
            ->pluck('student_code')
            ->toArray();
            
        // Get all users with these codes
        $students = User::whereIn('code', $studentCodes)
            ->select('id', 'code', 'first_name', 'last_name', 'email')
            ->get();
            
        return Inertia::render('Notifications/Preview', [
            'exam' => $exam,
            'students' => $students,
            'studentCount' => count($students),
        ]);
    }
    
    /**
     * Test sending an exam update notification.
     */
    public function testUpdateNotification(Request $request, $examId)
    {
        // Check permissions
        if (!auth()->user()->can('send-notifications')) {
            return redirect()->back()->with('error', 'You do not have permission to send notifications.');
        }
        
        try {
            // Run the test command
            Artisan::call('exams:test-update-notification', [
                'exam_id' => $examId,
                '--email' => auth()->user()->email
            ]);
            $output = Artisan::output();
            
            // Log the action
            Log::info('Test update notification', [
                'user_id' => auth()->id(),
                'exam_id' => $examId,
                'output' => $output
            ]);
            
            return redirect()->back()->with('success', 'Test update notification sent successfully. Check your email.');
        } catch (\Exception $e) {
            Log::error('Failed to send test update notification', [
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->back()->with('error', 'Failed to send test notification: ' . $e->getMessage());
        }
    }
    
    /**
     * Preview an update notification for a specific exam.
     */
    public function previewUpdateNotification(Request $request, $examId)
    {
        $exam = ExamTimetable::with(['unit', 'semester'])->findOrFail($examId);
        
        // Find all student codes enrolled in this unit for this semester
        $studentCodes = Enrollment::where('unit_id', $exam->unit_id)
            ->where('semester_id', $exam->semester_id)
            ->pluck('student_code')
            ->toArray();
            
        // Get all users with these codes
        $students = User::whereIn('code', $studentCodes)
            ->select('id', 'code', 'first_name', 'last_name', 'email')
            ->get();
        
        // Create mock changes for preview
        $changes = [
            'venue' => [
                'old' => $exam->venue,
                'new' => 'New Example Venue'
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
            
        return Inertia::render('Notifications/PreviewUpdate', [
            'exam' => $exam,
            'students' => $students,
            'studentCount' => count($students),
            'changes' => $changes
        ]);
    }
    
    /**
     * Display user's personal notifications.
     */
    public function userNotifications()
    {
        $user = auth()->user();
        
        // Get unread notifications
        $unreadNotifications = $user->unreadNotifications()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($notification) {
                return [
                    'id' => $notification->id,
                    'type' => str_replace('App\\Notifications\\', '', $notification->type),
                    'data' => $notification->data,
                    'created_at' => $notification->created_at->diffForHumans(),
                    'read_at' => null
                ];
            });
            
        // Get read notifications
        $readNotifications = $user->readNotifications()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function($notification) {
                return [
                    'id' => $notification->id,
                    'type' => str_replace('App\\Notifications\\', '', $notification->type),
                    'data' => $notification->data,
                    'created_at' => $notification->created_at->diffForHumans(),
                    'read_at' => $notification->read_at->diffForHumans()
                ];
            });
            
        return Inertia::render('Notifications/UserNotifications', [
            'unreadNotifications' => $unreadNotifications,
            'readNotifications' => $readNotifications
        ]);
    }
    
    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        
        return redirect()->back();
    }
    
    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request)
    {
        auth()->user()->unreadNotifications->markAsRead();
        
        return redirect()->back();
    }
    
    /**
     * Filter notification logs.
     */
    public function filterLogs(Request $request)
    {
        $query = DB::table('notification_logs');
        
        // Apply filters
        if ($request->has('type') && $request->type) {
            $query->where('notification_type', $request->type);
        }
        
        if ($request->has('success') && $request->success !== null) {
            $query->where('success', $request->success === 'true');
        }
        
        if ($request->has('date_from') && $request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to') && $request->date_to) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }
        
        // Get results
        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();
            
        return Inertia::render('Notifications/Logs', [
            'notifications' => $notifications,
            'filters' => $request->all()
        ]);
    }
}
