<?php

namespace App\Policies;

use App\Models\Inquiry;
use App\Models\User;

class InquiryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function view(User $user, Inquiry $inquiry): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function update(User $user, Inquiry $inquiry): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function delete(User $user, Inquiry $inquiry): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function restore(User $user, Inquiry $inquiry): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function forceDelete(User $user, Inquiry $inquiry): bool
    {
        return $user->isLeaderOrAdmin();
    }
}
