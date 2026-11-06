<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanBenefit extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'benefit_id',
        'requested_amount',
        'approved_amount',
        'collection_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'collection_date' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
    }
}
