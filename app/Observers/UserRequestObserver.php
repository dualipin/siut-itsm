<?php

namespace App\Observers;

use App\Enums\UserRole;
use App\Mail\NewRequestAdminMail;
use App\Mail\RequestStatusUpdatedMail;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Support\Facades\Mail;

class UserRequestObserver
{
    /**
     * Handle the UserRequest "creating" event.
     */
    public function creating(UserRequest $userRequest): void
    {
        if (empty($userRequest->folio)) {
            $year = now()->format('Y');
            $lastId = (int) (UserRequest::withTrashed()->whereYear('created_at', $year)->max('id') ?? 0) + 1;
            $candidate = sprintf('SOL-%s-%05d', $year, $lastId);
            $count = 1;

            while (UserRequest::withTrashed()->where('folio', $candidate)->exists()) {
                $candidate = sprintf('SOL-%s-%05d', $year, $lastId + $count);
                $count++;
            }

            $userRequest->folio = $candidate;
        }
    }

    /**
     * Handle the UserRequest "created" event.
     */
    public function created(UserRequest $userRequest): void
    {
        $userRequest->recordHistory(
            toStatus: $userRequest->status,
            fromStatus: null,
            notes: 'Solicitud creada',
            userId: $userRequest->user_id
        );

        $admins = User::whereIn('role', [UserRole::Admin, UserRole::Lider])
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)
                ->send(new NewRequestAdminMail($userRequest));
        }
    }

    /**
     * Handle the UserRequest "updated" event.
     */
    public function updated(UserRequest $userRequest): void
    {
        if ($userRequest->isDirty('status')) {
            $userRequest->recordHistory(
                toStatus: $userRequest->status,
                fromStatus: $userRequest->getOriginal('status'),
                notes: $userRequest->resolution_notes,
                userId: auth()->id() ?? $userRequest->reviewed_by
            );

            Mail::to($userRequest->user->email)
                ->send(new RequestStatusUpdatedMail($userRequest));
        }
    }

    /**
     * Handle the UserRequest "deleted" event.
     */
    public function deleted(UserRequest $userRequest): void
    {
        //
    }

    /**
     * Handle the UserRequest "restored" event.
     */
    public function restored(UserRequest $userRequest): void
    {
        //
    }

    /**
     * Handle the UserRequest "force deleted" event.
     */
    public function forceDeleted(UserRequest $userRequest): void
    {
        //
    }
}
