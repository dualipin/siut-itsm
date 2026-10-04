<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PetitionRequest extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'annual_petition_id',
        'curp',
        'agremiado_name',
        'proposal',
    ];

    /**
     * @return BelongsTo<AnnualPetition, covariant $this>
     */
    public function annualPetition(): BelongsTo
    {
        return $this->belongsTo(AnnualPetition::class);
    }

    // /**
    //  * @return BelongsTo<User, covariant $this>
    //  */
    // public function member(): BelongsTo
    // {
    //     return $this->belongsTo(User::class);
    // }
}
