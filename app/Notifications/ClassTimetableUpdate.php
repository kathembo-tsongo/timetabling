<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

class ClassTimetableUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     *
     * @param array $data
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        try {
            // Extract data with defaults
            $subject = $this->data['subject'] ?? 'Class Schedule Update';
            $greeting = $this->data['greeting'] ?? 'Hello';
            $message = $this->data['message'] ?? 'There has been an update to your class schedule.';
            $classDetails = $this->data['class_details'] ?? '';
            $changes = $this->data['changes'] ?? '';
            $closing = $this->data['closing'] ?? 'Please review these changes and plan accordingly.';
            $isLecturer = $this->data['is_lecturer'] ?? false;

            // Check if the route exists - different routes for lecturers and students
            $url = '#';
            if ($isLecturer) {
                if (Route::has('lecturer.timetable')) {
                    $url = route('lecturer.timetable');
                } elseif (Route::has('lecturer.classtimetable')) {
                    $url = route('lecturer.classtimetable');
                } elseif (Route::has('lecturer.dashboard')) {
                    $url = route('lecturer.dashboard');
                } elseif (Route::has('dashboard')) {
                    $url = route('dashboard');
                }
            } else {
                if (Route::has('student.timetable')) {
                    $url = route('student.timetable');
                } elseif (Route::has('student.classtimetable')) {
                    $url = route('student.classtimetable');
                } elseif (Route::has('student.dashboard')) {
                    $url = route('student.dashboard');
                } elseif (Route::has('dashboard')) {
                    $url = route('dashboard');
                }
            }

            $mailMessage = (new MailMessage)
                ->subject($subject)
                ->greeting($greeting)
                ->line($message)
                ->when(!empty($classDetails), function ($message) use ($classDetails) {
                    return $message->line('Class Details:')
                        ->line($classDetails);
                })
                ->when(!empty($changes), function ($message) use ($changes) {
                    return $message->line('Changes Made:')
                        ->line($changes);
                })
                ->line($closing);
                
            // Different button text for lecturers and students
            if ($isLecturer) {
                // $mailMessage->action('View Your Timetable', $url);
            } else {
                // $mailMessage->action('View Updated Timetable', $url);
            }
            
            return $mailMessage;
                
        } catch (\Exception $e) {
            Log::error('Failed to create class timetable update notification', [
                'error' => $e->getMessage(),
                'notifiable' => $notifiable->id ?? 'unknown'
            ]);
            
            throw $e;
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'data' => $this->data
        ];
    }
}
