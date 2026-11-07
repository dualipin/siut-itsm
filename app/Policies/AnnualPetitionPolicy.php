<?php

namespace App\Policies;

use App\Models\AnnualPetition;
use App\Models\User;

class AnnualPetitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function view(User $user, AnnualPetition $petition): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function update(User $user, AnnualPetition $petition): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function delete(User $user, AnnualPetition $petition): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function restore(User $user, AnnualPetition $petition): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function forceDelete(User $user, AnnualPetition $petition): bool
    {
        return $user->isLeaderOrAdmin();
    }
}
