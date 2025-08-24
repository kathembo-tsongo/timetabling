<?php

namespace App\Policies;

use App\Models\ExamTimetable;
use App\Models\User;

class ExamTimetablePolicy
{
    /**
     * Determine if the user can view any exam timetables.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole('Admin') || $user->hasRole('Exam Office');
    }

    /**
     * Determine if the user can view a specific exam timetable.
     */
    public function view(User $user, ExamTimetable $examTimetable)
    {
        return $user->hasRole('Admin') || $user->hasRole('Exam Office');
    }

    /**
     * Determine if the user can create exam timetables.
     */
    public function create(User $user)
    {
        return $user->hasRole('Admin') || $user->hasRole('Exam Office');
    }

    /**
     * Determine if the user can update an exam timetable.
     */
    public function update(User $user, ExamTimetable $examTimetable)
    {
        return $user->hasRole('Admin') || $user->hasRole('Exam Office');
    }

    /**
     * Determine if the user can delete an exam timetable.
     */
    public function delete(User $user, ExamTimetable $examTimetable)
    {
        return $user->hasRole('Admin');
    }
}
