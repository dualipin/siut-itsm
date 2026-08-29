<?php

namespace App\Models;

use App\Enums\InquiryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Inquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_name',
        'guest_email',
        'title',
        'slug',
        'body',
        'category',
        'is_public',
        'status',
        'views_count',
    ];

    /**
     * Boot model to automatically generate slug if not provided.
     */
    protected static function booted(): void
    {
        static::creating(function (Inquiry $inquiry) {
            if (empty($inquiry->slug)) {
                $baseSlug = Str::slug($inquiry->title);
                $slug = $baseSlug;
                $count = 1;

                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}-{$count}";
                    $count++;
                }

                $inquiry->slug = $slug;
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InquiryStatus::class,
            'is_public' => 'boolean',
            'views_count' => 'integer',
        ];
    }

    /**
     * Get the registered user who created the inquiry (if authenticated).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get all answers for this inquiry.
     *
     * @return HasMany<InquiryAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(InquiryAnswer::class, 'inquiry_id');
    }

    /**
     * Scope query to public inquiries.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Get the display name of the author.
     */
    public function getAuthorDisplayName(): string
    {
        if ($this->user) {
            return $this->user->full_name ?: $this->user->name;
        }

        return $this->guest_name ?: 'Anónimo';
    }

    /**
     * Increment views count safely.
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }
}
