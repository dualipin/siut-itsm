<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\User;
use App\Models\UserRequest;

class UserRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UserRequest $request): bool
    {
        if ($user->isLeaderOrAdmin()) {
            return true;
        }

        return $user->id === $request->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return (bool) $user->is_active;
    }

    /**
     * Determine whether the user can update the model.
     * Agremiados cannot edit user requests once submitted.
     */
    public function update(User $user, UserRequest $request): bool
    {
        return $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UserRequest $request): bool
    {
        return $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can cancel the request.
     * Agremiados can only cancel their own pending requests.
     */
    public function cancel(User $user, UserRequest $request): bool
    {
        if ($user->id === $request->user_id && $request->status === RequestStatus::Pending) {
            return true;
        }

        return $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, UserRequest $request): bool
    {
        return $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, UserRequest $request): bool
    {
        return $user->isLeaderOrAdmin();
    }
}
