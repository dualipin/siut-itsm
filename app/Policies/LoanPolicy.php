<?php

namespace App\Policies;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Loan $loan): bool
    {
        if ($user->isLeaderOrAdmin()) {
            return true;
        }

        return $user->id === $loan->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return (bool) $user->is_active;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Loan $loan): bool
    {
        if ($loan->status !== LoanStatus::Borrador) {
            return false;
        }

        return $user->id === $loan->user_id || $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Loan $loan): bool
    {
        if ($user->isLeaderOrAdmin()) {
            return true;
        }

        return $user->id === $loan->user_id && $loan->status === LoanStatus::Borrador;
    }

    /**
     * Determine whether the user can submit the loan for review.
     */
    public function submit(User $user, Loan $loan): bool
    {
        return $user->id === $loan->user_id && $loan->status === LoanStatus::Borrador;
    }

    /**
     * Determine whether the user can review/approve/reject/waitlist the loan.
     * Separation of duties: nobody can review their own loan.
     */
    public function review(User $user, Loan $loan): bool
    {
        return $user->isLeaderOrAdmin() && $user->id !== $loan->user_id;
    }

    /**
     * Determine whether the user can disburse the loan.
     * Separation of duties: nobody can disburse their own loan.
     */
    public function disburse(User $user, Loan $loan): bool
    {
        return $user->isLeaderOrAdmin() && $loan->status === LoanStatus::Aprobada && $user->id !== $loan->user_id;
    }

    /**
     * Determine whether the user can regenerate documents.
     */
    public function regenerateDocuments(User $user, Loan $loan): bool
    {
        return $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can download loan documents.
     */
    public function downloadDocument(User $user, Loan $loan): bool
    {
        if ($user->isLeaderOrAdmin()) {
            return true;
        }

        return $user->id === $loan->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Loan $loan): bool
    {
        return $user->isLeaderOrAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Loan $loan): bool
    {
        return $user->isLeaderOrAdmin();
    }
}
