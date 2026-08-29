<?php

namespace App\Models;

use Database\Factories\FinancialReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class FinancialReport extends Model implements HasMedia
{
    /** @use HasFactory<FinancialReportFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'year',
        'created_by',
    ];

    /**
     * Register media collections for the model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('financial_reports')
            ->singleFile();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
