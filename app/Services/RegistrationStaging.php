<?php

namespace App\Services;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RegistrationStaging
{
    private const SESSION_KEY = 'registration.staged';

    /**
     * Stage an uploaded document into the current session.
     *
     * @param  array<string, mixed>|null  $aiResult
     * @param  list<string>  $warnings
     */
    public function stage(string $kind, string $path, ?array $aiResult = null, array $warnings = []): string
    {
        $token = (string) Str::uuid();

        $staged = Session::get(self::SESSION_KEY, []);

        $staged[$token] = [
            'token' => $token,
            'kind' => $kind,
            'path' => $path,
            'ai' => $aiResult,
            'warnings' => $warnings,
            'exp' => now()->addHours(2)->timestamp,
        ];

        Session::put(self::SESSION_KEY, $staged);

        return $token;
    }

    /**
     * Resolve a staged document by token and expected kind.
     *
     * @return array{token: string, kind: string, path: string, ai: ?array<string, mixed>, warnings: list<string>, exp: int}|null
     */
    public function resolve(string $token, string $kind): ?array
    {
        $staged = Session::get(self::SESSION_KEY, []);

        $entry = $staged[$token] ?? null;

        if (! is_array($entry)) {
            return null;
        }

        if (($entry['kind'] ?? null) !== $kind) {
            return null;
        }

        if (now()->timestamp > ($entry['exp'] ?? 0)) {
            $this->forget($token);

            return null;
        }

        $path = $entry['path'] ?? '';

        if (! filled($path) || ! Storage::disk('local')->exists($path)) {
            $this->forget($token);

            return null;
        }

        return $entry;
    }

    /**
     * Remove a staged token from session and optionally delete its temporary file.
     */
    public function forget(string $token): void
    {
        $staged = Session::get(self::SESSION_KEY, []);

        if (isset($staged[$token])) {
            unset($staged[$token]);
            Session::put(self::SESSION_KEY, $staged);
        }
    }

    /**
     * Clear all staged entries in the session.
     */
    public function forgetAll(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
