<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    /**
     * Determine if the user can view any enrollments.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole('Admin') || $user->hasRole('Faculty Admin') || $user->hasRole('Student');
    }

    /**
     * Determine if the user can view a specific enrollment.
     */
    public function view(User $user, Enrollment $enrollment)
    {
        return $user->hasRole('Admin') || $user->hasRole('Faculty Admin') || $user->code === $enrollment->student_code;
    }

    /**
     * Determine if the user can create enrollments.
     */
    public function create(User $user)
    {
        return $user->hasRole('Admin') || $user->hasRole('Faculty Admin') || $user->hasRole('Student');
    }

    /**
     * Determine if the user can update an enrollment.
     */
    public function update(User $user, Enrollment $enrollment)
    {
        return $user->hasRole('Admin') || $user->hasRole('Faculty Admin');
    }

    /**
     * Determine if the user can delete an enrollment.
     */
    public function delete(User $user, Enrollment $enrollment)
    {
        return $user->hasRole('Admin');
    }
}
