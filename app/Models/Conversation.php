<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'created_by',
        'status',
        'last_message_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * Get the user who initiated the conversation.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the participants of the conversation.
     *
     * @return HasMany<ConversationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id');
    }

    /**
     * Get the participating users.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['last_read_at'])
            ->withTimestamps();
    }

    /**
     * Get all messages in this conversation.
     *
     * @return HasMany<ConversationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class, 'conversation_id');
    }

    /**
     * Get the latest message of the conversation.
     *
     * @return HasOne<ConversationMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class, 'conversation_id')->latestOfMany();
    }

    /**
     * Check if user is a participant.
     */
    public function hasParticipant(int|User $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->participants()->where('user_id', $userId)->exists();
    }

    /**
     * Get other participant (useful for 1-to-1 conversation view).
     */
    public function getOtherParticipant(int|User $user): ?User
    {
        $userId = $user instanceof User ? $user->id : $user;

        $participant = $this->participants()->where('user_id', '!=', $userId)->with('user')->first();

        return $participant?->user;
    }

    /**
     * Mark conversation as read for a given user.
     */
    public function markAsReadFor(int|User $user): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        $this->participants()
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);
    }

    /**
     * Count unread messages for a given user.
     */
    public function unreadMessagesCountFor(int|User $user): int
    {
        $userId = $user instanceof User ? $user->id : $user;

        $participant = $this->participants()->where('user_id', $userId)->first();
        if (! $participant) {
            return 0;
        }

        $query = $this->messages()->where('sender_id', '!=', $userId);

        if ($participant->last_read_at) {
            $query->where('created_at', '>', $participant->last_read_at);
        }

        return $query->count();
    }
}
