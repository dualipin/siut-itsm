<?php

namespace App\Policies;

use App\Models\ContactSubmission;
use App\Models\User;

class ContactSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function view(User $user, ContactSubmission $submission): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ContactSubmission $submission): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function delete(User $user, ContactSubmission $submission): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function restore(User $user, ContactSubmission $submission): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function forceDelete(User $user, ContactSubmission $submission): bool
    {
        return $user->isLeaderOrAdmin();
    }
}
