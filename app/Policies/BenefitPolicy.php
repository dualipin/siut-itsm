<?php

namespace App\Policies;

use App\Models\Benefit;
use App\Models\User;

class BenefitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function view(User $user, Benefit $benefit): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function update(User $user, Benefit $benefit): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function delete(User $user, Benefit $benefit): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function restore(User $user, Benefit $benefit): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function forceDelete(User $user, Benefit $benefit): bool
    {
        return $user->isLeaderOrAdmin();
    }
}
