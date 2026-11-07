<?php

namespace App\Policies;

use App\Models\TransparencyRecord;
use App\Models\User;

class TransparencyRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function view(User $user, TransparencyRecord $record): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function update(User $user, TransparencyRecord $record): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function delete(User $user, TransparencyRecord $record): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function restore(User $user, TransparencyRecord $record): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function forceDelete(User $user, TransparencyRecord $record): bool
    {
        return $user->isLeaderOrAdmin();
    }
}
