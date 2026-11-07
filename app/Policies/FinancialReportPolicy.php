<?php

namespace App\Policies;

use App\Models\FinancialReport;
use App\Models\User;

class FinancialReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function view(User $user, FinancialReport $report): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function update(User $user, FinancialReport $report): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function delete(User $user, FinancialReport $report): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function restore(User $user, FinancialReport $report): bool
    {
        return $user->isLeaderOrAdmin();
    }

    public function forceDelete(User $user, FinancialReport $report): bool
    {
        return $user->isLeaderOrAdmin();
    }
}
