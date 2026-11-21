<?php

namespace App\Policies;

use App\Models\TransparencyRecord;
use App\Models\User;

class TransparencyRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveDirectiva($user);
    }

    public function view(User $user, TransparencyRecord $record): bool
    {
        return $this->isActiveDirectiva($user);
    }

    public function create(User $user): bool
    {
        return $this->isActiveDirectiva($user);
    }

    public function update(User $user, TransparencyRecord $record): bool
    {
        return $this->isActiveDirectiva($user);
    }

    public function delete(User $user, TransparencyRecord $record): bool
    {
        return $this->isActiveDirectiva($user);
    }

    public function restore(User $user, TransparencyRecord $record): bool
    {
        return $this->isActiveDirectiva($user);
    }

    public function forceDelete(User $user, TransparencyRecord $record): bool
    {
        return $this->isActiveDirectiva($user);
    }

    private function isActiveDirectiva(User $user): bool
    {
        return (bool) $user->is_active && $user->isLeaderOrAdmin();
    }
}
