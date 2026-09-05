<?php

namespace App\Models;

use Database\Factories\RequestTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class RequestType extends Model
{
    /** @use HasFactory<RequestTypeFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'requires_attachment',
        'custom_fields',
        'max_per_user_per_year',
        'is_active',
    ];

    /**
     * Boot model to automatically generate slug if not provided.
     */
    protected static function booted(): void
    {
        static::creating(function (RequestType $requestType) {
            if (empty($requestType->slug)) {
                $baseSlug = Str::slug($requestType->name);
                $slug = $baseSlug;
                $count = 1;

                while (static::withTrashed()->where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}-{$count}";
                    $count++;
                }

                $requestType->slug = $slug;
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
            'requires_attachment' => 'boolean',
            'custom_fields' => 'array',
            'max_per_user_per_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the requests associated with this request type.
     *
     * @return HasMany<UserRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(UserRequest::class, 'request_type_id');
    }

    /**
     * Scope a query to only include active request types.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
