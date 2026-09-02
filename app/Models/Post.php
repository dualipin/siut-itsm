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
     * Scope a query to only include active (non-expired) posts.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()->toDateString());
        });
    }

    /**
     * Get the thumbnail URL (from media library, external URL, or local storage fallback).
     */
    public function getThumbnailUrlAttribute(): string
    {
        $mediaUrl = $this->getFirstMediaUrl('thumbnail');
        if ($mediaUrl) {
            return $mediaUrl;
        }

        if ($this->thumbnail) {
            if (str_starts_with($this->thumbnail, 'http://') || str_starts_with($this->thumbnail, 'https://')) {
                return $this->thumbnail;
            }

            return Storage::url($this->thumbnail);
        }

        return 'https://img.daisyui.com/images/stock/photo-1625726411847-8cbb60cc71e6.webp';
    }

    /**
     * Get estimated reading time in minutes based on content word count.
     */
    public function getReadingTimeAttribute(): int
    {
        $wordCount = str_word_count(strip_tags($this->content ?? ''));

        return max(1, (int) ceil($wordCount / 200));
    }

    /**
     * Get the slug for this post's type.
     */
    public function getTypeSlugAttribute(): string
    {
        return $this->type?->getSlug() ?? 'publicaciones';
    }
}
