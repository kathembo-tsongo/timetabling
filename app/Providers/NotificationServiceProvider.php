<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\DB;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Log successful notifications
        Event::listen(NotificationSent::class, function (NotificationSent $event) {
            $this->logNotification($event->notification, $event->notifiable, $event->channel, true);
        });

        // Log failed notifications
        Event::listen(NotificationFailed::class, function (NotificationFailed $event) {
            $this->logNotification(
                $event->notification, 
                $event->notifiable, 
                $event->channel, 
                false, 
                $event->exception->getMessage()
            );
        });
    }

    /**
     * Log a notification to the database.
     */
    protected function logNotification($notification, $notifiable, $channel, $success, $errorMessage = null): void
    {
        try {
            // Extract data from notification if available
            $data = method_exists($notification, 'toArray') 
                ? $notification->toArray($notifiable) 
                : null;

            DB::table('notification_logs')->insert([
                'notification_type' => get_class($notification),
                'notifiable_type' => get_class($notifiable),
                'notifiable_id' => $notifiable->id,
                'channel' => $channel,
                'success' => $success,
                'error_message' => $errorMessage,
                'data' => $data ? json_encode($data) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log any errors but don't interrupt the application
            \Log::error('Failed to log notification', [
                'error' => $e->getMessage(),
                'notification' => get_class($notification),
                'notifiable' => get_class($notifiable),
            ]);
        }
    }
}