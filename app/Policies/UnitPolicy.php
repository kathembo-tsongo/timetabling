<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    /**
     * Determine if the user can view any units.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole('Admin') || $user->hasRole('Faculty Admin');
    }

    /**
     * Determine if the user can view a specific unit.
     */
    public function view(User $user, Unit $unit)
    {
        return $user->hasRole('Admin') || $user->hasRole('Faculty Admin');
    }

    /**
     * Determine if the user can create units.
     */
    public function create(User $user)
    {
        return $user->hasRole('Admin') || $user->hasRole('Faculty Admin');
    }

    /**
     * Determine if the user can update a unit.
     */
    public function update(User $user, Unit $unit)
    {
        return $user->hasRole('Admin') || $user->hasRole('Faculty Admin');
    }

    /**
     * Determine if the user can delete a unit.
     */
    public function delete(User $user, Unit $unit)
    {
        return $user->hasRole('Admin');
    }
}
