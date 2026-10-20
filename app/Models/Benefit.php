<?php

namespace App\Models;

use Database\Factories\BenefitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Benefit extends Model
{
    /** @use HasFactory<BenefitFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'collection_day',
        'collection_month',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'collection_day' => 'integer',
            'collection_month' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active benefits.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Returns the collection date for the given year.
     * Uses the last day of February when collection_day exceeds the month's length.
     */
    public function collectionDateForYear(int $year): Carbon
    {
        $date = Carbon::create($year, $this->collection_month, 1);

        $maxDay = $date->daysInMonth;
        $day = min($this->collection_day, $maxDay);

        return $date->setDay($day);
    }
}
