<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
class User extends Authenticatable implements FilamentUser, HasAvatar, HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithMedia, Notifiable, SoftDeletes;

    /**
     * Determine if the user can access the given Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

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
     * Get the contact replies authored by the user.
     *
     * @return HasMany<ContactReply, $this>
     */
    public function contactReplies(): HasMany
    {
        return $this->hasMany(ContactReply::class, 'user_id');
    }

    /**
     * Get the conversations initiated by the user.
     *
     * @return HasMany<Conversation, $this>
     */
    public function conversationsCreated(): HasMany
    {
        return $this->hasMany(Conversation::class, 'created_by');
    }

    /**
     * Get all conversations the user is participating in.
     *
     * @return BelongsToMany<Conversation, $this>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot(['last_read_at'])
            ->withTimestamps();
    }

    /**
     * Get the messages sent by the user.
     *
     * @return HasMany<ConversationMessage, $this>
     */
    public function conversationMessages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'sender_id');
    }

    /**
     * Get the inquiries submitted by the user.
     *
     * @return HasMany<Inquiry, $this>
     */
    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'user_id');
    }

    /**
     * Get the inquiry answers authored by the user.
     *
     * @return HasMany<InquiryAnswer, $this>
     */
    public function inquiryAnswers(): HasMany
    {
        return $this->hasMany(InquiryAnswer::class, 'user_id');
    }

    /**
     * Get the requests submitted by the user.
     *
     * @return HasMany<UserRequest, $this>
     */
    public function userRequests(): HasMany
    {
        return $this->hasMany(UserRequest::class, 'user_id');
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

    /**
     * Determine if the user has leader or admin role.
     * Both 'lider' and 'admin' share the same privilege level across the platform.
     */
    public function isLeaderOrAdmin(): bool
    {
        return $this->isAdmin() || $this->isLider();
    }

    /**
     * Register Spatie media collections for affiliation documents.
     */
    public function registerMediaCollections(): void
    {
        foreach (UserDocumentType::cases() as $documentType) {
            $this->addMediaCollection($documentType->value)
                ->useDisk('local')
                ->singleFile()
                ->acceptsMimeTypes(['application/pdf', 'application/x-empty']);
        }
    }

    /**
     * Get the media record for a specific document type.
     */
    public function getDocumentMedia(UserDocumentType|string $type): ?Media
    {
        $collection = $type instanceof UserDocumentType ? $type->value : $type;

        return $this->getFirstMedia($collection);
    }

    /**
     * Get the status of a specific document type.
     */
    public function getDocumentStatus(UserDocumentType|string $type): DocumentStatus|string
    {
        $media = $this->getDocumentMedia($type);

        if (! $media) {
            return 'sin_subir';
        }

        $statusValue = $media->getCustomProperty('status', DocumentStatus::Pending->value);

        return DocumentStatus::tryFrom($statusValue) ?? DocumentStatus::Pending;
    }

    /**
     * Get the rejection reason / observations of a specific document.
     */
    public function getDocumentRejectionReason(UserDocumentType|string $type): ?string
    {
        $media = $this->getDocumentMedia($type);

        return $media?->getCustomProperty('rejection_reason');
    }

    /**
     * Check if all 5 required documents have been uploaded and validated.
     */
    public function areAllDocumentsValid(): bool
    {
        foreach (UserDocumentType::cases() as $type) {
            $media = $this->getDocumentMedia($type);

            if (! $media) {
                return false;
            }

            if ($media->getCustomProperty('status') !== DocumentStatus::Valid->value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the overall document status for this user.
     * Returns DocumentStatus::Valid if all 5 documents are valid.
     * Returns DocumentStatus::Invalid if any document is invalid.
     * Returns DocumentStatus::Pending otherwise (pending review or missing documents).
     */
    public function getOverallDocumentStatus(): DocumentStatus
    {
        $hasInvalid = false;
        $allValid = true;

        foreach (UserDocumentType::cases() as $type) {
            $media = $this->getDocumentMedia($type);

            if (! $media) {
                $allValid = false;

                continue;
            }

            $status = $media->getCustomProperty('status', DocumentStatus::Pending->value);

            if ($status === DocumentStatus::Invalid->value) {
                $hasInvalid = true;
            }

            if ($status !== DocumentStatus::Valid->value) {
                $allValid = false;
            }
        }

        if ($hasInvalid) {
            return DocumentStatus::Invalid;
        }

        if ($allValid) {
            return DocumentStatus::Valid;
        }

        return DocumentStatus::Pending;
    }

    /**
     * Determine if the user is considered valid based on all documents.
     */
    public function isDocumentValid(): bool
    {
        return $this->areAllDocumentsValid();
    }
}
