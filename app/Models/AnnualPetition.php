<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AnnualPetition extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'year',
        'deadline',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline' => 'date',
        ];
    }

    /**
     * @return HasMany<PetitionRequest, covariant $this>
     */
    public function petitionRequests(): HasMany
    {
        return $this->hasMany(PetitionRequest::class);
    }

    /**
     * @return HasMany<AnnualPetitionConvocation, covariant $this>
     */
    public function convocations(): HasMany
    {
        return $this->hasMany(AnnualPetitionConvocation::class)->orderBy('sort_order');
    }

    public function getRouteKeyName(): string
    {
        return 'year';
    }
}
