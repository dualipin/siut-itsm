<?php

namespace App\Policies;

use App\Models\PetitionRequest;
use App\Models\User;

class PetitionRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function view(User $user, PetitionRequest $request): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PetitionRequest $request): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function delete(User $user, PetitionRequest $request): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function restore(User $user, PetitionRequest $request): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function forceDelete(User $user, PetitionRequest $request): bool
    {
        return $user->isLeaderOrAdmin();
    }
}
