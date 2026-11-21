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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TransparencyRecord extends Model implements HasMedia
{
    /** @use HasFactory<TransparencyRecordFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

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
    public function isPublic(): bool
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
     * Register media collections for the model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents');
    }
}
