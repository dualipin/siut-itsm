<?php

namespace App\Policies;

use App\Models\Theme;
use App\Models\User;

class ThemePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function view(User $user, Theme $theme): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function update(User $user, Theme $theme): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function delete(User $user, Theme $theme): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function restore(User $user, Theme $theme): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function forceDelete(User $user, Theme $theme): bool
    {
        return $user->isLeaderOrAdmin();
    }
}
