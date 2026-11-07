<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function view(User $user, Post $post): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function update(User $user, Post $post): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->isLeaderOrAdmin();
    }
}
