<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Notifications\Exam_reminder;
use App\Models\User;

class MailController extends Controller
{
    public function index()
    {
        $user = User::find(1563); // Replace with the actual user ID

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $data = [
            'Humble reminder' => 'Exam Reminder Notification',
            'Hello' => 'Hello ' . $user->first_name,
            'Wish' => 'We wish to remind you that you have an exam tomorrow. Please check your timetable for details.',
            'ExamDetails' => [
                'unit' => 'BIT101 - Introduction to Information Technology',
                'date' => '2025-05-02',
                'day' => 'Friday',
                'time' => '08:00 - 10:00',
                'venue' => 'Blue Sky',
                'location' => 'Phase1',
            ],
        ];

        $user->notify(new Exam_reminder($data));

        return response()->json(['message' => 'Notification sent successfully']);
    }
}
