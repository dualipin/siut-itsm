<?php

namespace App\Models;

use App\Enums\PostType;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $fillable = [
        'author_id',
        'type',
        'title',
        'slug',
        'content',
        'thumbnail',
        'expires_at',
        'post_attachments',
    ];

    protected $casts = [
        'type' => PostType::class,
        'expires_at' => 'date',
    ];

    /**
     * Get the author that owns the post.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the attachments for the post.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(PostAttachment::class, 'post_id');
    }

    /**
     * Determine if the post has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
