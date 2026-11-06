<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanAmortizationRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'period',
        'type',
        'label',
        'date',
        'opening_balance',
        'principal',
        'interest',
        'payment',
        'closing_balance',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => 'integer',
            'date' => 'date',
            'opening_balance' => 'decimal:2',
            'principal' => 'decimal:2',
            'interest' => 'decimal:2',
            'payment' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
