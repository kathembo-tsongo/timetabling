<?php

namespace App\Policies;

use App\Models\ClassTimetable;
use App\Models\User;

class ClassTimetablePolicy
{
    /**
     * Determine if the user can view any class timetables.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole('Admin') || $user->hasRole('Faculty Admin');
    }

    /**
     * Determine if the user can view a specific class timetable.
     */
    public function view(User $user, ClassTimetable $classTimetable)
    {
        return $user->hasRole('Admin') || $user->hasRole('Faculty Admin');
    }

    /**
     * Determine if the user can create class timetables.
     */
    public function create(User $user)
    {
        return $user->hasRole('Admin') || $user->hasRole('Faculty Admin');
    }

    /**
     * Determine if the user can update a class timetable.
     */
    public function update(User $user, ClassTimetable $classTimetable)
    {
        return $user->hasRole('Admin') || $user->hasRole('Faculty Admin');
    }

    /**
     * Determine if the user can delete a class timetable.
     */
    public function delete(User $user, ClassTimetable $classTimetable)
    {
        return $user->hasRole('Admin');
    }
}
