<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates CURP uniqueness regardless of the casing typed by the user.
 *
 * CURPs are always persisted in uppercase (see User::curp()), so the incoming
 * value is normalized before comparing. Soft-deleted users are included
 * because the unique index on users.curp also covers them.
 */
class UniqueCurp implements ValidationRule
{
    public function __construct(private readonly int|string|null $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $exists = User::withTrashed()
            ->where('curp', mb_strtoupper(trim((string) $value)))
            ->when($this->ignoreId, fn ($query, $id) => $query->whereKeyNot($id))
            ->exists();

        if ($exists) {
            $fail('validation.unique')->translate(['attribute' => 'CURP']);
        }
    }
}
