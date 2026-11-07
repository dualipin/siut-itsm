<?php

namespace App\Policies;

use App\Models\RequestType;
use App\Models\User;

class RequestTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function view(User $user, RequestType $type): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function update(User $user, RequestType $type): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function delete(User $user, RequestType $type): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function restore(User $user, RequestType $type): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function forceDelete(User $user, RequestType $type): bool
    {
        return $user->isLeaderOrAdmin();
    }
}
