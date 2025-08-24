<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DatabaseChangeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $data)
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
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject($this->data['subject'])
            ->greeting($this->data['greeting'] ?? 'Hello ' . $notifiable->first_name)
            ->line($this->data['message']);

        // Add action button if URL is provided
        if (!empty($this->data['action_url'])) {
            $mailMessage->action(
                $this->data['action_text'] ?? 'View Details',
                $this->data['action_url']
            );
        }

        // Add any additional lines
        if (!empty($this->data['additional_lines'])) {
            foreach ($this->data['additional_lines'] as $line) {
                $mailMessage->line($line);
            }
        }

        return $mailMessage->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->data;
    }
}
