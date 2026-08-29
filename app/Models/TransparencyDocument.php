<?php

namespace App\Models;

use Database\Factories\TransparencyDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TransparencyDocument extends Model implements HasMedia
{
    /** @use HasFactory<TransparencyDocumentFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'transparency_record_id',
        'name',
        'published_at',
        'is_public',
        'uploaded_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'date',
            'is_public' => 'boolean',
        ];
    }

    /**
     * Get the transparency record that owns the document.
     *
     * @return BelongsTo<TransparencyRecord, $this>
     */
    public function transparencyRecord(): BelongsTo
    {
        return $this->belongsTo(TransparencyRecord::class, 'transparency_record_id');
    }

    /**
     * Alias relationship for transparency record.
     *
     * @return BelongsTo<TransparencyRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->transparencyRecord();
    }

    /**
     * Get the user who uploaded the document.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope a query to only include public documents.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * Register media collections for the model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')
            ->singleFile();
    }
}
