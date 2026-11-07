<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AuditService
{
    /**
     * Log a sensitive administrative action to the audit channel.
     * Guaranteed never to log PII or credentials.
     *
     * @param  array<string, mixed>  $context
     */
    public static function log(
        string $action,
        ?int $actorId,
        ?int $targetId = null,
        array $context = []
    ): void {
        $disallowed = [
            'password',
            'password_confirmation',
            'current_password',
            'email',
            'curp',
            'phone',
            'address',
            'nss',
            'salary',
            'name',
            'surnames',
            'birth_date',
        ];

        $cleanContext = array_diff_key($context, array_flip($disallowed));

        Log::channel('audit')->info("Audit: [{$action}]", [
            'action' => $action,
            'actor_id' => $actorId,
            'target_id' => $targetId,
            ...$cleanContext,
        ]);
    }
}
