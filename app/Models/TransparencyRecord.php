<?php

namespace App\Models;

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use Database\Factories\TransparencyRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TransparencyRecord extends Model implements HasMedia
{
    /** @use HasFactory<TransparencyRecordFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'name',
        'summary',
        'fiscal_year',
        'period',
        'observations',
        'type',
        'status',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'type' => TransparencyRecordType::class,
            'status' => TransparencyRecordStatus::class,
        ];
    }

    /**
     * Get the user that created the record.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the documents associated with the record.
     *
     * @return HasMany<TransparencyDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(TransparencyDocument::class, 'transparency_record_id');
    }

    /**
     * Scope a query to only include published records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', TransparencyRecordStatus::Publicado);
    }

    /**
     * Scope a query to filter by fiscal year.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForFiscalYear(Builder $query, int $year): Builder
    {
        return $query->where('fiscal_year', $year);
    }

    /**
     * Determine if the record is published.
     */
    public function isPublished(): bool
    {
        return $this->status === TransparencyRecordStatus::Publicado;
    }

    /**
     * Register media collections for the model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('document')
            ->singleFile();
    }
}
