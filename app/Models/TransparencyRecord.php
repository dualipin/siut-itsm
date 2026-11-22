<?php

namespace App\Models;

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use Database\Factories\TransparencyRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TransparencyRecord extends Model implements HasMedia
{
    /** @use HasFactory<TransparencyRecordFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public const string DOCUMENTS_COLLECTION = 'documents';

    protected $attributes = [
        'status' => TransparencyRecordStatus::Interno->value,
    ];

    protected $fillable = [
        'name',
        'summary',
        'fiscal_year',
        'period',
        'document_date',
        'reference_number',
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
            'document_date' => 'date',
            'type' => TransparencyRecordType::class,
            'status' => TransparencyRecordStatus::class,
        ];
    }

    /**
     * Normalize empty reference numbers to null so the unique index allows multiple unnumbered records.
     *
     * @return Attribute<?string, ?string>
     */
    protected function referenceNumber(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => filled($value) ? trim($value) : null,
        );
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
     * Scope a query to only include public records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublico(Builder $query): Builder
    {
        return $query->where('status', TransparencyRecordStatus::Publico);
    }

    /**
     * Scope a query to only include internal consultation records.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInterno(Builder $query): Builder
    {
        return $query->where('status', TransparencyRecordStatus::Interno);
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
     * Determine if the record is publicly downloadable.
     */
    public function isPublico(): bool
    {
        return $this->status === TransparencyRecordStatus::Publico;
    }

    /**
     * Determine if the record is restricted to internal consultation.
     */
    public function isInterno(): bool
    {
        return $this->status === TransparencyRecordStatus::Interno;
    }

    /**
     * Determine if the given media item belongs to this record's documents collection.
     */
    public function ownsDocumentMedia(Media $media): bool
    {
        return $media->model_type === self::class
            && (int) $media->model_id === (int) $this->getKey()
            && $media->collection_name === self::DOCUMENTS_COLLECTION;
    }

    /**
     * Determine if the record currently has at least one attached document.
     */
    public function hasAttachedDocuments(): bool
    {
        return $this->getMedia(self::DOCUMENTS_COLLECTION)->isNotEmpty();
    }

    /**
     * Publish the record when at least one document is attached, clearing internal observations.
     */
    public function publish(): bool
    {
        if (! $this->hasAttachedDocuments()) {
            return false;
        }

        return $this->update(self::sanitizeAttributes([
            ...$this->only(['type', 'fiscal_year', 'period', 'document_date', 'reference_number', 'observations']),
            'status' => TransparencyRecordStatus::Publico,
        ]));
    }

    /**
     * Revert the record to internal consultation visibility.
     */
    public function unpublish(): bool
    {
        return $this->update([
            'status' => TransparencyRecordStatus::Interno,
        ]);
    }

    /**
     * Clear out-of-context attributes and derive fiscal_year from document_date outside Financiero.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizeAttributes(array $data): array
    {
        $rawType = $data['type'] ?? null;
        $type = $rawType instanceof TransparencyRecordType
            ? $rawType
            : (filled($rawType) ? TransparencyRecordType::tryFrom((string) $rawType) : null);

        $rawStatus = $data['status'] ?? null;
        $status = $rawStatus instanceof TransparencyRecordStatus
            ? $rawStatus
            : (filled($rawStatus) ? TransparencyRecordStatus::tryFrom((string) $rawStatus) : null);

        if ($type?->usesPeriod()) {
            $data['document_date'] = null;
        } else {
            $data['period'] = null;

            if (filled($data['document_date'] ?? null)) {
                $data['fiscal_year'] = Carbon::parse($data['document_date'])->year;
            }
        }

        if (! ($type?->usesFolio() ?? false)) {
            $data['reference_number'] = null;
        }

        if ($status !== TransparencyRecordStatus::Interno) {
            $data['observations'] = null;
        }

        return $data;
    }

    /**
     * Register media collections for the model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::DOCUMENTS_COLLECTION);
    }
}
