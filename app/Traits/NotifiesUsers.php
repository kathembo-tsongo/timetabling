<?php

namespace App\Traits;

use App\Models\User;
use App\Notifications\DatabaseChangeNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

trait NotifiesUsers
{
    /**
     * Get the users who should be notified about changes to this model.
     * 
     * Override this method in your model to define which users should be notified.
     */
    public function getUsersToNotify(): array
    {
        return [];
    }
    
    /**
     * Get the notification data for this model.
     * 
     * Override this method in your model to customize notification content.
     */
    public function getNotificationData(string $action, array $changes = []): array
    {
        $modelName = class_basename($this);
        
        return [
            'subject' => "{$modelName} Update Notification",
            'message' => "A {$modelName} record has been {$action}.",
            'additional_lines' => $this->formatChangesForNotification($changes),
            'model_id' => $this->id,
            'model_type' => get_class($this),
            'action' => $action,
        ];
    }
    
    /**
     * Format changes for notification.
     */
    protected function formatChangesForNotification(array $changes): array
    {
        if (empty($changes)) {
            return [];
        }
        
        $lines = ["The following details have been updated:"];
        
        foreach ($changes as $field => $newValue) {
            $oldValue = $this->getOriginal($field) ?? 'not set';
            $lines[] = "• " . ucfirst(str_replace('_', ' ', $field)) . ": Changed from '{$oldValue}' to '{$newValue}'";
        }
        
        return $lines;
    }
    
    /**
     * Send notifications about model changes.
     */
    public function sendChangeNotifications(string $action, array $changes = []): void
    {
        try {
            $users = $this->getUsersToNotify();
            
            if (empty($users)) {
                return;
            }
            
            $notificationData = $this->getNotificationData($action, $changes);
            
            foreach ($users as $user) {
                $user->notify(new DatabaseChangeNotification($notificationData));
                
                // Log the notification
                Log::info("Sent database change notification", [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'model_type' => get_class($this),
                    'model_id' => $this->id,
                    'action' => $action
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error("Failed to send database change notifications", [
                'model_type' => get_class($this),
                'model_id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Boot the trait.
     */
    public static function bootNotifiesUsers(): void
    {
        static::created(function (Model $model) {
            $model->sendChangeNotifications('created');
        });
        
        static::updated(function (Model $model) {
            $relevantChanges = $model->getDirty();
            
            // Skip notification if no changes or only timestamps changed
            if (empty($relevantChanges) || (count($relevantChanges) === 2 && 
                array_key_exists('updated_at', $relevantChanges) && 
                array_key_exists('created_at', $relevantChanges))) {
                return;
            }
            
            $model->sendChangeNotifications('updated', $relevantChanges);
        });
        
        static::deleted(function (Model $model) {
            $model->sendChangeNotifications('deleted');
        });
    }
}
