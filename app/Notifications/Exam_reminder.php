<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Route;

class Exam_reminder extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];
        
        // Check user preferences
        if ($notifiable->notificationPreference) {
            $prefs = $notifiable->notificationPreference;
            
            // Only send notifications if reminders are enabled
            if ($prefs->reminder_enabled) {
                if ($prefs->email_enabled) {
                    $channels[] = 'mail';
                }
                
                if ($prefs->sms_enabled && $notifiable->phone) {
                    $channels[] = 'vonage';
                }
            }
        } else {
            // Default to email if no preferences are set
            $channels[] = 'mail';
        }
        
        return $channels;
    }

   
    /**
 * Get the mail representation of the notification.
 */
public function toMail(object $notifiable): MailMessage
{
    // Use the subject key directly
    $subject = $this->data['subject'] ?? 'Exam Reminder';
    
    // Use the greeting from the data array if provided, otherwise generate it
    $greeting = $this->data['greeting'] ?? 'Hello ' . $notifiable->first_name;
    
    $message = $this->data['message'] ?? 'This is a reminder. You have an exam scheduled as detailed below:';
    $examDetails = $this->data['exam_details'] ?? [];
    $closing = $this->data['closing'] ?? 'Good luck with your exam preparation!';

    return (new MailMessage)
        ->subject($subject)
        ->markdown('emails.exam-reminder', [
            'subject' => $subject,
            'greeting' => $greeting,
            'message' => $message,
            'exam_details' => $examDetails,
            'closing' => $closing,
            'url' => null // Remove the button by passing null for the URL
        ]);
}
    
    /**
     * Get the Vonage / SMS representation of the notification.
     */
    public function toVonage(object $notifiable): VonageMessage
    {
        $examInfo = $this->data['exam_details'] ?? [];
        
        $content = "EXAM REMINDER: ";
        
        if (!empty($examInfo['unit'])) {
            $content .= "{$examInfo['unit']} on ";
        }
        
        if (!empty($examInfo['date'])) {
            $content .= "{$examInfo['date']} at ";
        }
        
        if (!empty($examInfo['time'])) {
            $content .= "{$examInfo['time']} in ";
        }
        
        if (!empty($examInfo['venue'])) {
            $content .= "{$examInfo['venue']}. ";
        }
        
        $content .= "Good luck!";
        
        return (new VonageMessage)
            ->content($content)
            ->unicode();
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subject' => $this->data['subject'] ?? 'Exam Reminder',
            'greeting' => $this->data['greeting'] ?? 'Hello',
            'message' => $this->data['message'] ?? 'We wish to remind you that you will be having an exam tomorrow.',
            'exam_details' => $this->data['exam_details'] ?? [],
            'closing' => $this->data['closing'] ?? 'We wish all the best in your preparation!'
        ];
    }
}