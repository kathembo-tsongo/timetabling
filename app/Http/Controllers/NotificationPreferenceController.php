<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationPreferenceController extends Controller
{
    /**
     * Display the notification preferences page.
     */
    public function index()
    {
        $preferences = auth()->user()->notificationPreference;
        
        return Inertia::render('Notifications/Preferences', [
            'preferences' => $preferences,
        ]);
    }

    /**
     * Update the user's notification preferences.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'hours_before' => 'integer|min:1|max:72',
            'reminder_enabled' => 'boolean',
        ]);

        auth()->user()->notificationPreference()->update($validated);

        return redirect()->back()->with('success', 'Notification preferences updated successfully.');
    }
}
