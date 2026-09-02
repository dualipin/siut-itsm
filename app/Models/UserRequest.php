<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Database\Factories\UserRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class UserRequest extends Model implements HasMedia
{
    /** @use HasFactory<UserRequestFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'folio',
        'user_id',
        'request_type_id',
        'reason',
        'additional_data',
        'status',
        'reviewed_by',
        'resolution_notes',
        'reviewed_at',
        'completed_at',
    ];

    /**
     * Boot model to automatically generate unique folio if not provided.
     */
    protected static function booted(): void
    {
        static::creating(function (UserRequest $request) {
            if (empty($request->folio)) {
                $year = now()->format('Y');
                $lastId = (int) (static::withTrashed()->whereYear('created_at', $year)->max('id') ?? 0) + 1;
                $candidate = sprintf('SOL-%s-%05d', $year, $lastId);
                $count = 1;

                while (static::withTrashed()->where('folio', $candidate)->exists()) {
                    $candidate = sprintf('SOL-%s-%05d', $year, $lastId + $count);
                    $count++;
                }

                $request->folio = $candidate;
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
            'additional_data' => 'array',
            'status' => RequestStatus::class,
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that submitted the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the request type.
     *
     * @return BelongsTo<RequestType, $this>
     */
    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class, 'request_type_id');
    }

    /**
     * Get the user who reviewed the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the status history entries for this request.
     *
     * @return HasMany<UserRequestHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(UserRequestHistory::class, 'user_request_id')->latest();
    }

    /**
     * Register Spatie media collections for files and receipts.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
        $this->addMediaCollection('receipts')->singleFile();
    }

    /**
     * Record a new status history entry.
     */
    public function recordHistory(RequestStatus|string $toStatus, RequestStatus|string|null $fromStatus = null, ?string $notes = null, ?int $userId = null): UserRequestHistory
    {
        return $this->histories()->create([
            'user_id' => $userId ?? auth()->id(),
            'from_status' => $fromStatus instanceof RequestStatus ? $fromStatus->value : $fromStatus,
            'to_status' => $toStatus instanceof RequestStatus ? $toStatus->value : $toStatus,
            'notes' => $notes,
        ]);
    }

    /**
     * Scope a query to requests for a specific user.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
