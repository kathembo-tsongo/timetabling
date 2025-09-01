<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        // Get current system settings
        $settings = [
            'system_name' => config('app.name', 'Timetabling System'),
            'system_email' => config('mail.from.address'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'per_page_default' => 15,
            'session_timeout' => config('session.lifetime'),
            'maintenance_mode' => app()->isDownForMaintenance(),
            
            // Academic settings
            'current_semester' => 'Fall 2024', // You can get this from your database
            'academic_year' => '2024/2025',
            'registration_enabled' => true,
            'timetable_generation_enabled' => true,
            
            // Notification settings
            'email_notifications' => true,
            'sms_notifications' => false,
            'push_notifications' => true,
            
            // Security settings
            'two_factor_auth' => false,
            'password_expiry_days' => 90,
            'max_login_attempts' => 5,
        ];

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'system_name' => 'required|string|max:255',
            'system_email' => 'required|email',
            'timezone' => 'required|string',
            'locale' => 'required|string',
            'per_page_default' => 'required|integer|min:5|max:100',
            'session_timeout' => 'required|integer|min:60|max:1440',
            'current_semester' => 'required|string',
            'academic_year' => 'required|string',
            'registration_enabled' => 'boolean',
            'timetable_generation_enabled' => 'boolean',
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'two_factor_auth' => 'boolean',
            'password_expiry_days' => 'required|integer|min:30|max:365',
            'max_login_attempts' => 'required|integer|min:3|max:10',
        ]);

        // Here you would typically save these settings to a database table
        // or update configuration files/environment variables
        
        // For now, we'll just return success
        // In a real implementation, you might want to:
        // 1. Store in a settings table
        // 2. Update .env file for some settings
        // 3. Clear config cache

        return back()->with('success', 'Settings updated successfully!');
    }
}