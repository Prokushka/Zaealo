<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ZarkPackage;

class ZarkPackagePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ZarkPackage $zarkPackage): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ZarkPackage $zarkPackage): bool
    {
        return $user->isOwner();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ZarkPackage $zarkPackage): bool
    {
        return $user->isOwner() && ! $zarkPackage->payments()->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ZarkPackage $zarkPackage): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ZarkPackage $zarkPackage): bool
    {
        return false;
    }
}
