<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferLeadershipAction
{
    /**
     * Atomically transfer leadership from current leader to target user.
     *
     * @throws AuthorizationException|InvalidArgumentException
     */
    public function execute(User $currentLeader, User $newLeader): void
    {
        if (! $currentLeader->isLider()) {
            throw new AuthorizationException('Solo el líder actual puede transferir el liderazgo.');
        }

        if ($currentLeader->id === $newLeader->id) {
            throw new InvalidArgumentException('No puedes transferir el liderazgo a ti mismo.');
        }

        DB::transaction(function () use ($currentLeader, $newLeader): void {
            /** @var User $leader */
            $leader = User::where('id', $currentLeader->id)->lockForUpdate()->firstOrFail();
            /** @var User $target */
            $target = User::where('id', $newLeader->id)->lockForUpdate()->firstOrFail();

            if (! $leader->isLider()) {
                throw new InvalidArgumentException('El usuario remitente no cuenta con el rol de Líder.');
            }

            // Degrade current leader to Admin first
            $leader->update(['role' => UserRole::Admin]);

            // Promote target user to Lider
            $target->update(['role' => UserRole::Lider]);

            AuditService::log(
                action: 'transfer_leadership',
                actorId: $leader->id,
                targetId: $target->id,
                context: [
                    'old_leader_id' => $leader->id,
                    'new_leader_id' => $target->id,
                ],
            );
        });
    }
}
