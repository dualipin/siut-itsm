<?php

namespace App\Policies;

use App\Models\TransparencyDocument;
use App\Models\User;

class TransparencyDocumentPolicy
{
    /**
     * Determine whether the user can view the transparency document.
     * Agremiados can only view documents belonging to published records.
     */
    public function view(User $user, TransparencyDocument $document): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isLeaderOrAdmin()) {
            return true;
        }

        return (bool) $document->transparencyRecord?->isPublished();
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
     */
    public function update(User $user, TransparencyDocument $document): bool
    {
        return $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TransparencyDocument $document): bool
    {
        return $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TransparencyDocument $document): bool
    {
        return $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TransparencyDocument $document): bool
    {
        return $user->isLeaderOrAdmin();
    }
}
