<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can update the model.
     * Admin cannot edit the Leader.
     */
    public function update(User $user, User $model): bool
    {
        if (! $user->isLeaderOrAdmin()) {
            return false;
        }

        if ($model->isLider() && ! $user->isLider()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the model.
     * Users cannot delete themselves; Admin cannot delete Leader;
     * System must never be left without at least one active Admin/Leader.
     */
    public function delete(User $user, User $model): bool
    {
        if (! $user->isLeaderOrAdmin()) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        if ($model->isLider() && ! $user->isLider()) {
            return false;
        }

        if ($model->is_active && $model->isLeaderOrAdmin()) {
            $otherActiveCount = User::whereIn('role', [UserRole::Admin, UserRole::Lider])
                ->where('is_active', true)
                ->where('id', '!=', $model->id)
                ->count();

            if ($otherActiveCount === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the user can bulk delete models.
     */
    public function deleteAny(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }
}
