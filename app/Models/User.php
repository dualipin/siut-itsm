<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name',
    'surnames',
    'email',
    'role',
    'is_active',
    'curp',
    'birth_date',
    'phone',
    'address',
    'photo_path',
    'category',
    'nss',
    'salary',
    'hiring_date',
    'password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'birth_date' => 'date',
            'hiring_date' => 'date',
            'salary' => 'decimal:2',
        ];
    }

    /**
     * Get the user's full name.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim("{$this->name} {$this->surnames}"),
        );
    }

    /**
     * Get the user's avatar URL for Filament.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        if (filter_var($this->photo_path, FILTER_VALIDATE_URL)) {
            return $this->photo_path;
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    /**
     * Get the posts authored by the user.
     *
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'author_id');
    }

    /**
     * Get the transparency records created by the user.
     *
     * @return HasMany<TransparencyRecord, $this>
     */
    public function transparencyRecords(): HasMany
    {
        return $this->hasMany(TransparencyRecord::class, 'created_by');
    }

    /**
     * Determine if the user has the admin role.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Determine if the user has the leader role.
     */
    public function isLider(): bool
    {
        return $this->role === UserRole::Lider;
    }

    /**
     * Determine if the user has the agremiado role.
     */
    public function isAgremiado(): bool
    {
        return $this->role === UserRole::Agremiado;
    }
}
