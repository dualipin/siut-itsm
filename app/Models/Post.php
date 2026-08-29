<?php

namespace App\Models;

use App\Enums\PostType;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Post extends Model implements HasMedia
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'author_id',
        'type',
        'title',
        'slug',
        'content',
        'thumbnail',
        'expires_at',
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
     * Determine if the post has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Register media collections for the post.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('thumbnail')
            ->singleFile();

        $this->addMediaCollection('attachments');
    }

    /**
     * Get the thumbnail URL (from media library or fallback).
     */
    public function getThumbnailUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('thumbnail')
            ?: ($this->thumbnail ? Storage::url($this->thumbnail) : 'https://img.daisyui.com/images/stock/photo-1625726411847-8cbb60cc71e6.webp');
    }
}
